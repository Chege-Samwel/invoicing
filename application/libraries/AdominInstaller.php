<?php
/**
 * AdominInstaller - first-run schema import, for hosts that give you no shell.
 *
 * Why this exists: application/config/database.sql is the app's whole schema and
 * seed data, and the usual way to load it is a MySQL client. On Render's free tier
 * there is no exec, and managed MySQL (TiDB Cloud) is driven from a browser, where
 * pasting 50 KB / 194 statements into a SQL editor is the step that fails - silently
 * if the schema was not selected, partly if the editor splits statements on its own.
 * The deploy then reports "connected, 0 tables" and every page 500s.
 *
 * The app can already reach the database (that is what /status proved), so it can run
 * its own DDL. This class does exactly that, and nothing else:
 *
 *   - it only runs when the key in ADOMIN_SETUP_KEY matches, and refuses a schema that
 *     already has the app's tables (it will not touch a live install's data);
 *   - statements come from the two .sql files in this repository, split with a
 *     quote-aware splitter - no multi_query, so one bad statement cannot desynchronise
 *     the rest of the stream;
 *   - CREATE TABLE is rewritten to CREATE TABLE IF NOT EXISTS and an INSERT into a
 *     table that already has rows is skipped, so a batch that died half way (a proxy
 *     timeout, a 0.1-CPU instance) can simply be continued;
 *   - work is capped per request (statements and wall-clock), with a cursor in
 *     application/cache/adomin-setup-state.json, because PHP's max_execution_time and
 *     Render's own limits would otherwise truncate the import mid-file.
 *
 * It is deliberately not a CodeIgniter controller: the framework cannot boot here (the
 * database library is autoloaded and the tables it expects are the thing that is
 * missing), which is the same reason /status lives in index.php. Guarded by BASEPATH,
 * and .htaccess denies application/ over HTTP, so it is reachable only through
 * index.php's /setup route.
 */
defined('BASEPATH') OR exit('No direct script access allowed');

class AdominInstaller {

	/** Files to run, in order. */
	public static function files()
	{
		return array(
			'schema' => array('label' => 'database.sql (the app schema and its seed data)', 'path' => FCPATH.'database.sql'),
			'updates' => array('label' => 'database/adomin-updates.sql (Kenyan defaults, share links)', 'path' => FCPATH.'database/adomin-updates.sql'),
		);
	}

	public static function state_file()
	{
		$dir = FCPATH.'application/cache';
		if ( ! is_dir($dir) OR ! is_writable($dir))
		{
			$dir = sys_get_temp_dir();
		}
		return rtrim($dir, '/').'/adomin-setup-state.json';
	}

	public static function read_state()
	{
		$raw = @file_get_contents(self::state_file());
		$state = $raw ? json_decode($raw, TRUE) : NULL;
		if ( ! is_array($state) OR ! isset($state['file'], $state['offset']))
		{
			$state = array('file' => 'schema', 'offset' => 0, 'done' => FALSE, 'created' => 0, 'skipped' => 0, 'errors' => 0);
		}
		return $state;
	}

	public static function write_state($state)
	{
		@file_put_contents(self::state_file(), json_encode($state), LOCK_EX);
	}

	/**
	 * Split a MySQL dump into statements. Quote-aware, and it ignores line comments
	 * (double dash and hash) and block comments while looking for the terminator,
	 * because a comment may contain one - this repo's own migration does, on
	 * purpose: several GUI consoles split on ';' without parsing, which cut its
	 * guard queries in half.
	 */
	public static function split_statements($sql)
	{
		$out = array();
		$buf = '';
		$len = strlen($sql);
		$quote = NULL;
		$i = 0;
		while ($i < $len)
		{
			$c = $sql[$i];

			if ($quote === NULL && ($c === '-' && isset($sql[$i + 1]) && $sql[$i + 1] === '-')
				|| $quote === NULL && ($c === '#'))
			{
				// to end of line; the backslash-escaped form (\-) is not comment syntax
				$eol = strpos($sql, "\n", $i);
				$i = $eol === FALSE ? $len : $eol + 1;
				continue;
			}
			if ($quote === NULL && $c === '/' && isset($sql[$i + 1]) && $sql[$i + 1] === '*')
			{
				$close = strpos($sql, '*/', $i + 2);
				if ($close === FALSE)
				{
					$i = $len;
					continue;
				}
				// /*!40101 ... */ is an executable conditional comment: keep it
				$snippet = substr($sql, $i, ($close + 2) - $i);
				if (isset($snippet[2]) && $snippet[2] === '!')
				{
					$buf .= $snippet;
				}
				$i = $close + 2;
				continue;
			}

			if ($quote === NULL && ($c === "'" || $c === '"' || $c === '`'))
			{
				$quote = $c;
				$buf .= $c;
				$i++;
				continue;
			}
			if ($quote !== NULL)
			{
				if ($c === '\\' && $quote !== '`')
				{
					// escaped char inside a string literal: take it verbatim
					$buf .= $c.(isset($sql[$i + 1]) ? $sql[$i + 1] : '');
					$i += 2;
					continue;
				}
				if ($c === $quote)
				{
					// doubled quote is an escaped quote, not the end
					if (isset($sql[$i + 1]) && $sql[$i + 1] === $quote)
					{
						$buf .= $c.$c;
						$i += 2;
						continue;
					}
					$quote = NULL;
				}
				$buf .= $c;
				$i++;
				continue;
			}
			if ($c === ';')
			{
				$trimmed = trim($buf);
				if ($trimmed !== '')
				{
					$out[] = $trimmed;
				}
				$buf = '';
				$i++;
				continue;
			}
			$buf .= $c;
			$i++;
		}
		if (trim($buf) !== '')
		{
			$out[] = trim($buf);
		}
		return $out;
	}

	/**
	 * Make a statement re-runnable. Returns array(sql, action) where action is
	 * 'run', 'skip-populated' or the reason a statement is reported without failing.
	 */
	public static function prepare($statement)
	{
		// keep the original whitespace after the keyword: replacing the run of
		// whitespace itself glues the new clause to the table name
		// (`CREATE TABLE IF NOT EXISTS`clients``), which is a syntax error on the
		// first statement of the file.
		$rewritten = preg_replace('/^(CREATE\s+TABLE)(\s+)(?!IF\s+NOT\s+EXISTS)/i', '$1 IF NOT EXISTS$2', $statement, 1);
		if (preg_match('/^INSERT\s+INTO\s+`?(\w+)`?/i', $statement, $m))
		{
			return array('sql' => $rewritten, 'action' => 'check-rows', 'table' => $m[1]);
		}
		return array('sql' => $rewritten, 'action' => 'run', 'table' => '');
	}

	/**
	 * Error codes that mean "already in place", i.e. a resume working as intended.
	 * 1050 table exists, 1061 duplicate key name, 1091 can't drop (absent),
	 * 1826/3819 duplicate FK constraint, 3730/3822 constraint references/bad state
	 * (TiDB reports FK bookkeeping differently from MySQL).
	 */
	public static function is_benign($errno)
	{
		return in_array((int) $errno, array(1050, 1061, 1091, 1826, 1829, 3730, 3819, 3822, 3823), TRUE);
	}

	/**
	 * The gate. Every rule is a separate answer so the page can say which one stopped
	 * it, rather than a bare 403.
	 */
	public static function decide($facts)
	{
		if ($facts['disabled'])
		{
			return array(FALSE, 'ADOMIN_STATUS=0 also switches /setup off, since both are diagnostic surfaces.');
		}
		if ($facts['key_env'] === '')
		{
			return array(FALSE, 'Setup mode is off: set ADOMIN_SETUP_KEY to any private string under Render > Environment (Apply reboots), open /setup again, and type that value in. This page cannot run without it, and there is no way to guess one.');
		}
		if (strlen($facts['key_env']) < 8)
		{
			// a 3-character "key" is not a key; refuse rather than pretend to gate
			return array(FALSE, 'ADOMIN_SETUP_KEY is set but shorter than 8 characters, so it is not treated as a key at all - lengthen it in Render > Environment, press Apply, and come back.');
		}
		if ( ! $facts['key_ok'])
		{
			return array(FALSE, 'The key does not match ADOMIN_SETUP_KEY.');
		}
		if ($facts['reserved_schema'])
		{
			return array(FALSE, 'DB_NAME points at a schema the server owns (`'.$facts['database'].'`). Create your own first: CREATE DATABASE `adomin`; then set DB_NAME to it. The app will not create tables in a system schema.');
		}
		if ( ! $facts['connected'])
		{
			return array(FALSE, 'The database is not reachable, so nothing can be imported: '.$facts['error'].' . Fix the connection first (`database` block in /status).');
		}
		if ($facts['invoices_exists'])
		{
			return array(FALSE, 'This schema already has the app\'s `invoices` table, so it is not a fresh install - refusing to run a schema import over live data. If a previous attempt was interrupted, fix the named tables by hand, or point DB_NAME at a new empty schema.');
		}
		if ($facts['tables'] >= 40)
		{
			return array(FALSE, 'This schema has '.$facts['tables'].' tables and none of them is `invoices`, which is not a state this tool understands. Not touching it.');
		}
		if ( ! $facts['files_readable'])
		{
			return array(FALSE, 'database.sql is not readable inside the container, so the import has nothing to run. Check the image layout (`COPY . /var/www/html`).');
		}
		return array(TRUE, 'Fresh schema, reachable database, key accepted.');
	}

	/**
	 * Run one batch. Returns the per-statement log for this batch plus the new state.
	 */
	public static function run_batch(mysqli $link, $max_statements = 25, $budget_seconds = 8)
	{
		$started = microtime(TRUE);
		$state = self::read_state();
		$files = self::files();
		$log = array();
		$ran = 0;

		while (isset($files[$state['file']]))
		{
			$path = $files[$state['file']]['path'];
			$statements = self::split_statements((string) @file_get_contents($path));
			if ( ! isset($statements[$state['offset']]))
			{
				// this file is finished: move to the next one
				$next = ($state['file'] === 'schema') ? 'updates' : NULL;
				$state['offset'] = 0;
				if ($next === NULL)
				{
					$state['done'] = TRUE;
					self::write_state($state);
					$log[] = array('file' => $state['file'], 'n' => '-', 'sql' => '(import complete)', 'note' => 'ok');
					return array('log' => $log, 'state' => $state, 'ran' => $ran);
				}
				$state['file'] = $next;
				self::write_state($state);
				continue;
			}

			$prepared = self::prepare($statements[$state['offset']]);
			$sql = $prepared['sql'];
			$note = 'ok';
			$failed = FALSE;

			if ($prepared['action'] === 'check-rows' && $prepared['table'] !== '')
			{
				$check = @$link->query('SELECT COUNT(*) AS c FROM `'.$prepared['table'].'`');
				if ($check)
				{
					$row = $check->fetch_object();
					if ($row && (int) $row->c > 0)
					{
						$note = 'skipped - `'.$prepared['table'].'` already has rows';
						$state['skipped']++;
					}
				}
			}
			if ($note === 'ok')
			{
				mysqli_report(MYSQLI_REPORT_OFF);
				if (FALSE === @$link->query($sql))
				{
					$errno = $link->errno;
					if (self::is_benign($errno))
					{
						$note = 'already in place (errno '.$errno.')';
						$state['skipped']++;
					}
					else
					{
						$note = 'ERROR '.$errno.': '.$link->error;
						$failed = TRUE;
						$state['errors']++;
					}
				}
				elseif (preg_match('/^CREATE\s+TABLE/i', $sql))
				{
					$state['created']++;
				}
			}

			$log[] = array(
				'file' => $state['file'],
				'n' => $state['offset'] + 1,
				'sql' => (strlen($sql) > 140 ? substr($sql, 0, 137).'...' : $sql),
				'note' => $note,
			);
			$state['offset']++;
			$ran++;
			self::write_state($state);

			// stop on a hard error: the next batch starts at the same statement, so the
			// operator sees it before anything downstream depends on it
			if ($failed)
			{
				$state['offset']--;
				self::write_state($state);
				$log[] = array('file' => $state['file'], 'n' => $state['offset'] + 1, 'sql' => '(stopped)', 'note' => 'fix this statement, or re-run to retry it');
				return array('log' => $log, 'state' => $state, 'ran' => $ran);
			}
			if ($ran >= $max_statements || (microtime(TRUE) - $started) > $budget_seconds)
			{
				break;
			}
		}

		self::write_state($state);
		return array('log' => $log, 'state' => $state, 'ran' => $ran);
	}

	/** Reset the cursor, e.g. after fixing a statement. */
	public static function reset()
	{
		self::write_state(array('file' => 'schema', 'offset' => 0, 'done' => FALSE, 'created' => 0, 'skipped' => 0, 'errors' => 0));
	}

	/*
	 * ---------------------------------------------------------------------
	 * A short-lived run token, so the key itself is not re-sent per batch
	 * ---------------------------------------------------------------------
	 *
	 * The import needs about nine batches, and asking for the key nine times is a
	 * page that trains people to paste secrets into the wrong box. So the accepted
	 * key mints a random token that is hashed before it is stored, expires on its
	 * own, and is deleted when the import finishes or is refused. The HTML never
	 * contains ADOMIN_SETUP_KEY.
	 */
	public static function token_file()
	{
		$dir = FCPATH.'application/cache';
		if ( ! is_dir($dir) OR ! is_writable($dir))
		{
			$dir = sys_get_temp_dir();
		}
		return rtrim($dir, '/').'/adomin-setup-token.json';
	}

	public static function issue_token()
	{
		$token = bin2hex(function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16));
		@file_put_contents(self::token_file(), json_encode(array(
			'hash' => hash('sha256', $token),
			'expires' => time() + 900,
		)), LOCK_EX);
		@chmod(self::token_file(), 0600);
		return $token;
	}

	public static function check_token($token)
	{
		if ($token === '')
		{
			return FALSE;
		}
		$raw = @file_get_contents(self::token_file());
		$row = $raw ? json_decode($raw, TRUE) : NULL;
		if ( ! is_array($row) OR empty($row['hash']) OR empty($row['expires']) OR $row['expires'] < time())
		{
			return FALSE;
		}
		return hash_equals($row['hash'], hash('sha256', $token));
	}

	public static function clear_token()
	{
		@unlink(self::token_file());
	}

	public static function render($title, $body, $status = 200)
	{
		if ( ! headers_sent())
		{
			header('HTTP/1.1 '.$status);
			header('Content-Type: text/html; charset=utf-8');
			header('X-robots-tag: noindex');
		}
		echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
			.'<title>'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</title>'
			.'<style>body{font:15px/1.6 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:0;background:#0f1720;color:#e6edf3}'
			.'main{max-width:52rem;margin:0 auto;padding:2.5rem 1.25rem}h1{font-size:1.4rem;margin:0 0 .35rem}a{color:#7fd1ae}'
			.'code,pre{font:13px/1.55 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;background:#151f2b;border-radius:6px}'
			.'code{padding:.1rem .3rem}pre{padding:.75rem .85rem;overflow:auto;border:1px solid #22303f}'
			.'.card{background:#131c26;border:1px solid #22303f;border-radius:10px;padding:1rem 1.1rem;margin:1rem 0}'
			.'.warn{border-left:3px solid #e0a33e}.bad{border-left:3px solid #e06a5f}.good{border-left:3px solid #4fbf7f}'
			.'table{border-collapse:collapse;width:100%;font-size:13px}td,th{text-align:left;padding:.3rem .4rem;border-bottom:1px solid #22303f;vertical-align:top}'
			.'button{background:#2f8f5b;color:#fff;border:0;border-radius:7px;padding:.6rem 1rem;font:inherit;cursor:pointer}'
			.'input{background:#0c141c;color:#e6edf3;border:1px solid #22303f;border-radius:7px;padding:.55rem .7rem;font:13px ui-monospace,monospace;width:16rem}'
			.'.muted{color:#8ba0b3}.ok{color:#7ee2a8}.err{color:#ffb4a2}</style></head><body><main>'
			.'<h1>'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</h1>'
			.$body.
			'<p class="muted">Adomin Technologies &middot; this page only exists to finish a first install, and it does nothing without
			<code>ADOMIN_SETUP_KEY</code>. <a href="/status">/status</a> shows the underlying facts.</p></main></body></html>';
	}
}
