<?php
/**
 * Adomin Technologies — first-start database initialiser.
 *
 * Runs inside the container BEFORE Apache starts (see docker/adomin-start.sh).
 * Its job is to remove the one step that breaks most deployments: importing
 * database.sql by hand through a browser SQL console.
 *
 * What it does, in order:
 *   1. If ADOMIN_AUTO_IMPORT=0, do nothing (manual import only).
 *   2. If no DB_HOST is configured, do nothing (local install / not yet set up).
 *   3. Connect to the MySQL-compatible host (TiDB Cloud, Aiven, ...).
 *   4. CREATE DATABASE IF NOT EXISTS <DB_NAME>  (TiDB never creates it for you).
 *   5. Count tables in the schema:
 *        >= 57  -> already installed: check the primary keys (step 6) and stop.
 *        0      -> import database.sql + database/adomin-updates.sql.
 *        1..56  -> a previous browser import stopped part-way (the TiDB SQL
 *                  editor is known to run only some statements and still
 *                  report "Query OK"). Drop the partial tables and import
 *                  fresh, so a half-imported schema heals itself.
 *   6. Repair AUTO_INCREMENT. The dump sets each table's counter with
 *      `ALTER TABLE ... MODIFY ... AUTO_INCREMENT`, which TiDB refuses (error
 *      8200: the attribute can only be given in CREATE TABLE). Every table is
 *      still created, so the install looks complete, but the first INSERT into
 *      any of them fails with "Field 'id' doesn't have a default value" - which
 *      is what stops registration and login. Tables whose id has no counter are
 *      rebuilt (CREATE + copy + rename); a healthy schema costs one query.
 *   7. Verify the table count and log the result.
 *
 * It NEVER blocks Apache from starting: every failure is logged to stdout
 * (Render -> Logs) with the exact MySQL error, and the script exits 0.
 * The app will then answer 500 until the database is fixed, but the log
 * will say why. Set APP_ENV=development on Render to see the same error
 * in the browser, or open /status.php, which performs the same checks and
 * reports them in the browser without depending on the app booting.
 *
 * The connection logic below mirrors application/config/database.php
 * (DB_* variables, DATABASE_URL, DB_SSL / DB_SSL_CA / DB_SSL_VERIFY) so the
 * app and this script always connect the same way. Keep them in sync.
 *
 * Usage: php docker/adomin-init-db.php   (no arguments, no input)
 */

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');

function adomin_init_log($message)
{
	echo 'Adomin DB: ' . $message . PHP_EOL;
}

function adomin_init_env($key, $default = '')
{
	foreach (array(getenv($key), isset($_SERVER[$key]) ? $_SERVER[$key] : NULL, isset($_ENV[$key]) ? $_ENV[$key] : NULL) as $value)
	{
		if ($value !== FALSE && $value !== NULL && $value !== '')
		{
			return $value;
		}
	}
	return $default;
}

function adomin_init_bool($value)
{
	$value = strtolower(trim((string) $value));
	if ($value === '')
	{
		return FALSE;
	}
	return ! in_array($value, array('0', 'false', 'no', 'off', 'disabled', 'disable'), TRUE);
}

/**
 * Resolve the connection exactly like application/config/database.php.
 * Returns array(host, user, pass, db, port, encrypt) where encrypt is
 * FALSE or array(ssl_ca => ..., ssl_verify => ...).
 */
function adomin_init_config()
{
	$out = array(
		'host' => trim(adomin_init_env('DB_HOST')),
		'user' => trim(adomin_init_env('DB_USER')),
		'pass' => adomin_init_env('DB_PASS'),
		'db'   => trim(adomin_init_env('DB_NAME')),
		'port' => trim(adomin_init_env('DB_PORT')),
		'encrypt' => FALSE,
	);

	$url = '';
	foreach (array('DB_URL', 'MYSQL_URL', 'CLEARDB_DATABASE_URL', 'DATABASE_URL') as $key)
	{
		$candidate = trim(adomin_init_env($key));
		if ($candidate !== '')
		{
			$url = $candidate;
			break;
		}
	}

	$pass_is_set = getenv('DB_PASS') !== FALSE || isset($_SERVER['DB_PASS']) || isset($_ENV['DB_PASS']);
	if ($url === '' && ! $pass_is_set)
	{
		$out['pass'] = 'toor';
	}

	if ($url !== '')
	{
		$parts = parse_url($url);
		if (is_array($parts) && ! empty($parts['host']))
		{
			$out['host'] = $out['host'] !== '' ? $out['host'] : $parts['host'];
			$out['user'] = $out['user'] !== '' ? $out['user'] : (isset($parts['user']) ? rawurldecode($parts['user']) : '');
			$out['pass'] = $out['pass'] !== '' ? $out['pass'] : (isset($parts['pass']) ? rawurldecode($parts['pass']) : '');
			if ($out['db'] === '' && ! empty($parts['path']))
			{
				$out['db'] = ltrim($parts['path'], '/');
			}
			if ($out['port'] === '' && ! empty($parts['port']))
			{
				$out['port'] = (int) $parts['port'];
			}
			if ( ! empty($parts['query']))
			{
				parse_str($parts['query'], $qs);
				foreach (array('ssl-mode', 'sslmode', 'ssl_mode', 'tls') as $qs_key)
				{
					if (isset($qs[$qs_key]) && $qs[$qs_key] !== '')
					{
						$mode = strtolower($qs[$qs_key]);
						if (strpos($mode, 'disable') === FALSE && strpos($mode, 'skip') === FALSE && $mode !== 'false')
						{
							$out['encrypt'] = array(
								'ssl_verify' => (strpos($mode, 'verify-ca') !== FALSE || strpos($mode, 'verify-full') !== FALSE)
							);
						}
					}
				}
				foreach (array('sslca', 'ssl-ca', 'ssl_ca', 'sslrootcert') as $ca_key)
				{
					if (isset($qs[$ca_key]) && $qs[$ca_key] !== '')
					{
						$out['encrypt'] = is_array($out['encrypt']) ? $out['encrypt'] : array('ssl_verify' => FALSE);
						$out['encrypt']['ssl_ca'] = $qs[$ca_key];
					}
				}
			}
		}
	}

	$ssl = trim(adomin_init_env('DB_SSL'));
	if ($ssl !== '')
	{
		$out['encrypt'] = adomin_init_bool($ssl)
			? (is_array($out['encrypt']) ? $out['encrypt'] : array('ssl_verify' => FALSE))
			: FALSE;
	}

	if (is_array($out['encrypt']))
	{
		$ca = trim(adomin_init_env('DB_SSL_CA'));
		if ($ca === '')
		{
			foreach (array(
				'/etc/ssl/certs/ca-certificates.crt',
				'/etc/ssl/cert.pem',
				'/etc/pki/tls/certs/ca-bundle.crt'
			) as $candidate)
			{
				if (is_file($candidate))
				{
					$ca = $candidate;
					break;
				}
			}
		}
		if ($ca !== '' && is_file($ca))
		{
			$out['encrypt']['ssl_ca'] = $ca;
		}
		$out['encrypt']['ssl_verify'] = adomin_init_bool(adomin_init_env('DB_SSL_VERIFY', '0'));
	}

	if ($out['host'] === '') { $out['host'] = 'localhost'; }
	if ($out['user'] === '') { $out['user'] = 'root'; }
	if ($out['db'] === '')   { $out['db'] = 'test'; }
	if ($out['port'] === '') { $out['port'] = 3306; }

	return $out;
}

/**
 * Open a mysqli connection with the same TLS behaviour as the app.
 * $database === NULL connects without selecting a schema (for CREATE DATABASE).
 */
function adomin_init_connect($cfg, $database)
{
	if (function_exists('mysqli_report'))
	{
		mysqli_report(MYSQLI_REPORT_OFF);
	}
	$mysqli = mysqli_init();
	$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);

	$client_flags = 0;
	if (is_array($cfg['encrypt']))
	{
		$client_flags |= MYSQLI_CLIENT_SSL;
		if ( ! empty($cfg['encrypt']['ssl_verify']))
		{
			if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT'))
			{
				$mysqli->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, TRUE);
			}
		}
		elseif (defined('MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT'))
		{
			$client_flags |= MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
		}
		if ( ! empty($cfg['encrypt']['ssl_ca']))
		{
			$mysqli->ssl_set(NULL, NULL, $cfg['encrypt']['ssl_ca'], NULL, NULL);
		}
	}

	$ok = $mysqli->real_connect(
		$cfg['host'],
		$cfg['user'],
		$cfg['pass'],
		$database,
		(int) $cfg['port'],
		NULL,
		$client_flags
	);
	if ( ! $ok)
	{
		return NULL;
	}
	return $mysqli;
}

/**
 * Split a .sql file into statements on ';', respecting single/double/back
 * quotes, backslash escapes, -- and # line comments and /* *\/ block
 * comments (including MySQL conditional comments /*!...*\/).
 * Returns an array of statement strings (without the trailing ';').
 */
function adomin_init_split_sql($sql)
{
	$statements = array();
	$len = strlen($sql);
	$buf = '';
	$in_single = FALSE;
	$in_double = FALSE;
	$in_backtick = FALSE;
	$in_line_comment = FALSE;
	$in_block_comment = FALSE;

	for ($i = 0; $i < $len; $i++)
	{
		$ch = $sql[$i];
		$next = ($i + 1 < $len) ? $sql[$i + 1] : '';

		if ($in_line_comment)
		{
			$buf .= $ch;
			if ($ch === "\n")
			{
				$in_line_comment = FALSE;
			}
			continue;
		}
		if ($in_block_comment)
		{
			$buf .= $ch;
			if ($ch === '*' && $next === '/')
			{
				$buf .= $next;
				$i++;
				$in_block_comment = FALSE;
			}
			continue;
		}
		if ($in_single)
		{
			$buf .= $ch;
			if ($ch === '\\' && $next !== '')
			{
				$buf .= $next;
				$i++;
			}
			elseif ($ch === "'" && $next === "'")
			{
				$buf .= $next;
				$i++;
			}
			elseif ($ch === "'")
			{
				$in_single = FALSE;
			}
			continue;
		}
		if ($in_double)
		{
			$buf .= $ch;
			if ($ch === '\\' && $next !== '')
			{
				$buf .= $next;
				$i++;
			}
			elseif ($ch === '"' && $next === '"')
			{
				$buf .= $next;
				$i++;
			}
			elseif ($ch === '"')
			{
				$in_double = FALSE;
			}
			continue;
		}
		if ($in_backtick)
		{
			$buf .= $ch;
			if ($ch === '`' && $next === '`')
			{
				$buf .= $next;
				$i++;
			}
			elseif ($ch === '`')
			{
				$in_backtick = FALSE;
			}
			continue;
		}

		// Not inside any string/comment.
		if ($ch === '-' && $next === '-' && ($i + 2 >= $len || $sql[$i + 2] === ' ' || $sql[$i + 2] === "\t" || $sql[$i + 2] === "\n" || $sql[$i + 2] === "\r"))
		{
			$in_line_comment = TRUE;
			$buf .= $ch;
			continue;
		}
		if ($ch === '#')
		{
			$in_line_comment = TRUE;
			$buf .= $ch;
			continue;
		}
		if ($ch === '/' && $next === '*')
		{
			$in_block_comment = TRUE;
			$buf .= $ch;
			continue;
		}
		if ($ch === "'") { $in_single = TRUE; $buf .= $ch; continue; }
		if ($ch === '"') { $in_double = TRUE; $buf .= $ch; continue; }
		if ($ch === '`') { $in_backtick = TRUE; $buf .= $ch; continue; }

		if ($ch === ';')
		{
			$statement = trim($buf);
			if ($statement !== '')
			{
				$statements[] = $statement;
			}
			$buf = '';
			continue;
		}
		$buf .= $ch;
	}

	$statement = trim($buf);
	if ($statement !== '')
	{
		$statements[] = $statement;
	}
	return $statements;
}

/**
 * Run one .sql file on an open connection. Logs per-statement errors but
 * keeps going (the dump's last two ALTERs legitimately answer "Duplicate
 * column name" on a fresh install).
 *
 * $auto_increment_tables collects the tables whose `ALTER TABLE ... MODIFY ...
 * AUTO_INCREMENT` statement this server refused (TiDB, error 8200) so the
 * caller can repair them by rebuilding the table.
 *
 * Returns array(ok, failed).
 */
function adomin_init_import_file($mysqli, $path, $label, &$auto_increment_tables)
{
	$auto_increment_tables = array();
	if ( ! is_file($path))
	{
		adomin_init_log($label . ': file not found at ' . $path . ' - skipped.');
		return array(0, 0);
	}
	$sql = file_get_contents($path);
	if ($sql === FALSE)
	{
		adomin_init_log($label . ': could not read ' . $path . ' - skipped.');
		return array(0, 0);
	}
	$statements = adomin_init_split_sql($sql);
	$total = count($statements);
	adomin_init_log($label . ': ' . $total . ' statements to run.');
	$ok = 0;
	$failed = 0;
	foreach ($statements as $idx => $statement)
	{
		// Skip comment-only fragments (e.g. a file that is only comments).
		$stripped = trim(preg_replace('/^--.*$/m', '', $statement));
		$stripped = trim(preg_replace('#/\*.*?\*/#s', '', $stripped));
		if ($stripped === '')
		{
			continue;
		}
		if ( ! $mysqli->query($statement))
		{
			$errno = $mysqli->errno;
			// 1060 duplicate column, 1061 duplicate key, 1050 table exists:
			// already applied - expected on re-runs and for the dump's own
			// migration block. Count as ok so the summary is not alarming.
			if ($errno === 1060 || $errno === 1061 || $errno === 1050)
			{
				$ok++;
				continue;
			}

			/*
			 * TiDB error 8200: "Unsupported modify column: can't set
			 * auto_increment". TiDB only accepts the AUTO_INCREMENT attribute in
			 * CREATE TABLE; it can never be added by ALTER TABLE. Every one of the
			 * dump's 57 `ALTER TABLE ... MODIFY id ... AUTO_INCREMENT` statements
			 * fails there, which leaves 57 tables whose primary key has no
			 * counter: the first INSERT into each of them fails with "Field 'id'
			 * doesn't have a default value", so login and invoice creation are
			 * impossible even though the table count looks perfect.
			 *
			 * These statements are repairs, not failures: they are run again
			 * after the import by adomin_init_repair_auto_increment(), which
			 * rebuilds only the tables that really lack the attribute. So they
			 * are counted separately instead of as errors.
			 */
			if ($errno === 8200 && preg_match('/^\s*ALTER\s+TABLE\s+`?([A-Za-z0-9_]+)`?[^;]*\bAUTO_INCREMENT\b/i', $statement, $m))
			{
				$auto_increment_tables[] = $m[1];
				$ok++;
				continue;
			}

			$failed++;
			adomin_init_log($label . ': statement ' . ($idx + 1) . '/' . $total . ' failed [' . $errno . '] ' . $mysqli->error);
			// Keep going: one bad statement must not abort the other 185.
			continue;
		}
		$ok++;
		if (($idx + 1) % 25 === 0)
		{
			adomin_init_log($label . ': ' . ($idx + 1) . '/' . $total . ' done.');
		}
	}
	adomin_init_log($label . ': finished - ' . $ok . ' ok, ' . $failed . ' failed.');
	return array($ok, $failed);
}

function adomin_init_table_count($mysqli)
{
	$result = $mysqli->query("SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
	if ( ! $result)
	{
		return -1;
	}
	$row = $result->fetch_assoc();
	$result->free();
	return (int) $row['c'];
}

/**
 * Tables whose `id` is an AUTO_INCREMENT primary key attribute that is simply
 * missing. Read from information_schema, so MySQL and TiDB answer the same way.
 *
 * A table that already has the attribute is never touched.
 *
 * @return array table names
 */
function adomin_init_tables_missing_auto_increment($mysqli)
{
	$missing = array();
	$sql = "SELECT TABLE_NAME FROM information_schema.COLUMNS "
		. "WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'id' "
		. "AND EXTRA NOT LIKE '%auto_increment%'";
	$result = $mysqli->query($sql);
	if ( ! $result)
	{
		adomin_init_log('Could not read information_schema.COLUMNS [' . $mysqli->errno . '] ' . $mysqli->error);
		return $missing;
	}
	while ($row = $result->fetch_assoc())
	{
		$missing[] = $row['TABLE_NAME'];
	}
	$result->free();
	return $missing;
}

/**
 * Give a table its AUTO_INCREMENT counter back by rebuilding it.
 *
 * TiDB (Cloud Serverless/Starter, which is where this app is documented to run)
 * refuses `ALTER TABLE ... MODIFY id ... AUTO_INCREMENT` with
 *   8200 Unsupported modify column: can't set auto_increment
 * for a column that exists, and offers no other way to add the attribute. The
 * supported path is the one a person would do by hand: create a new table that
 * carries the attribute, copy the rows over, swap the names. On MySQL the same
 * code is unnecessary - but it is harmless, and using one path on both engines
 * means the hosted TiDB install and a local XAMPP install end up identical.
 */
function adomin_init_rebuild_with_auto_increment($mysqli, $table)
{
	if ( ! preg_match('/^[A-Za-z0-9_]+$/', $table))
	{
		return FALSE;
	}

	$result = $mysqli->query('SHOW CREATE TABLE `' . $table . '`');
	if ( ! $result)
	{
		adomin_init_log('  ' . $table . ': SHOW CREATE TABLE failed [' . $mysqli->errno . '] ' . $mysqli->error);
		$result = NULL;
		return FALSE;
	}
	$row = $result->fetch_assoc();
	$result->free();
	$create = isset($row['Create Table']) ? $row['Create Table'] : (isset($row['Create View']) ? $row['Create View'] : '');

	if ($create === '' || preg_match('/\bAUTO_INCREMENT\b/i', $create))
	{
		// Nothing to do, or nothing this function knows how to rebuild.
		return (bool) preg_match('/\bAUTO_INCREMENT\b/i', $create);
	}

	// `id` int(11) NOT NULL  ->  `id` int(11) NOT NULL AUTO_INCREMENT
	$new_create = preg_replace(
		'/^(\s*`id`\s+[^\r\n,]*?)\s*(,?)$/mi',
		'$1 AUTO_INCREMENT$2',
		$create
	);
	if ($new_create === NULL || $new_create === $create || ! preg_match('/`id`[^\r\n]*AUTO_INCREMENT/i', $new_create))
	{
		adomin_init_log('  ' . $table . ': could not derive an AUTO_INCREMENT definition - left unchanged.');
		return FALSE;
	}

	$backup = $table . '_adomin_old';
	$steps = array(
		'DROP TABLE IF EXISTS `' . $backup . '`',
		str_replace('CREATE TABLE `' . $table . '`', 'CREATE TABLE `' . $backup . '`', $new_create),
		'INSERT INTO `' . $backup . '` SELECT * FROM `' . $table . '`',
		'DROP TABLE `' . $table . '`',
		'RENAME TABLE `' . $backup . '` TO `' . $table . '`',
	);

	foreach ($steps as $step)
	{
		if ( ! $mysqli->query($step))
		{
			adomin_init_log('  ' . $table . ': rebuild failed [' . $mysqli->errno . '] ' . $mysqli->error);
			// Never leave the half-built copy behind for the next start to trip over.
			$mysqli->query('DROP TABLE IF EXISTS `' . $backup . '`');
			return FALSE;
		}
	}

	return TRUE;
}

/**
 * Repair every table whose `id` lost its AUTO_INCREMENT attribute - the normal
 * outcome of importing this dump into TiDB. Idempotent: a repaired table is
 * skipped on the next start, so the check costs one query.
 *
 * @return array(fixed, still_missing)
 */
function adomin_init_repair_auto_increment($mysqli, $candidates = array())
{
	// ADOMIN_FIX_AUTO_INCREMENT=0 leaves the schema untouched (someone may be
	// repairing it by hand, or running against a MySQL server where the import
	// already set every counter).
	if ( ! adomin_init_bool(adomin_init_env('ADOMIN_FIX_AUTO_INCREMENT', '1')))
	{
		adomin_init_log('ADOMIN_FIX_AUTO_INCREMENT=0 - not checking primary keys.');
		return array(0, 0);
	}

	$missing = adomin_init_tables_missing_auto_increment($mysqli);
	$fixed = 0;

	if ( ! empty($missing))
	{
		adomin_init_log(count($missing) . ' table(s) have a primary key without AUTO_INCREMENT (TiDB cannot add it with ALTER TABLE) - rebuilding them...');
	}

	$total = count($missing);
	$index = 0;
	foreach ($missing as $table)
	{
		$index++;
		if (adomin_init_rebuild_with_auto_increment($mysqli, $table))
		{
			$fixed++;
			// One line per table: this is the slowest part of a first start
			// (~1-2 min for all 57 on TiDB Serverless) and a silent log looks
			// like a hung deploy.
			adomin_init_log('  [' . $index . '/' . $total . '] ' . $table . ': AUTO_INCREMENT restored.');
		}
	}

	$still = adomin_init_tables_missing_auto_increment($mysqli);
	if ($fixed > 0)
	{
		adomin_init_log('AUTO_INCREMENT repaired on ' . $fixed . ' table(s).');
	}
	if ( ! empty($still))
	{
		adomin_init_log('WARNING: ' . count($still) . ' table(s) still have no AUTO_INCREMENT: ' . implode(', ', array_slice($still, 0, 10)) . (count($still) > 10 ? ', ...' : ''));
		adomin_init_log('Inserts into those tables will fail with "Field \'id\' doesn\'t have a default value". Run database/tidb-fix-increments.php against the database to repair them.');
	}

	return array($fixed, count($still));
}

function adomin_init_main()
{
	$auto = adomin_init_bool(adomin_init_env('ADOMIN_AUTO_IMPORT', '1'));
	if ( ! $auto)
	{
		adomin_init_log('ADOMIN_AUTO_IMPORT=0 - skipping (manual import only).');
		return;
	}

	// No host configured at all -> local install or not yet set up. Skip
	// quietly; the app falls back to localhost/root/toor/test.
	$host_env = trim(adomin_init_env('DB_HOST'));
	$url_env = trim(adomin_init_env('DATABASE_URL'));
	if ($host_env === '' && $url_env === '')
	{
		adomin_init_log('No DB_HOST / DATABASE_URL set - skipping (app uses local defaults).');
		return;
	}

	$cfg = adomin_init_config();
	$ssl_on = is_array($cfg['encrypt']) ? 'on' : 'off';
	adomin_init_log('Connecting to ' . $cfg['host'] . ':' . $cfg['port'] . ' as ' . $cfg['user'] . ' / db ' . $cfg['db'] . ' (TLS ' . $ssl_on . ')...');

	// Schema name goes into SQL as an identifier; only allow safe names.
	if ( ! preg_match('/^[A-Za-z0-9_]+$/', $cfg['db']))
	{
		adomin_init_log('ERROR: DB_NAME "' . $cfg['db'] . '" contains characters other than letters, digits and underscore - refusing to run.');
		return;
	}

	// 1) Connect without a schema and create it if needed.
	$mysqli = adomin_init_connect($cfg, NULL);
	if ($mysqli === NULL)
	{
		adomin_init_log('ERROR: could not connect to ' . $cfg['host'] . ':' . $cfg['port'] . ' - ' . mysqli_connect_error() . ' (errno ' . mysqli_connect_errno() . ').');
		adomin_init_log('Check on Render: DB_HOST, DB_USER (copy exactly from TiDB Connect dialog), DB_PASS, DB_PORT=4000, DB_SSL=1. See SETUP.md section 5.');
		return;
	}
	$create = 'CREATE DATABASE IF NOT EXISTS `' . $cfg['db'] . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci';
	if ( ! $mysqli->query($create))
	{
		adomin_init_log('ERROR: CREATE DATABASE failed [' . $mysqli->errno . '] ' . $mysqli->error . ' - the user may lack CREATE privilege.');
		$mysqli->close();
		return;
	}
	$mysqli->close();

	// 2) Connect with the schema selected.
	$mysqli = adomin_init_connect($cfg, $cfg['db']);
	if ($mysqli === NULL)
	{
		adomin_init_log('ERROR: could not select database ' . $cfg['db'] . ' - ' . mysqli_connect_error() . ' (errno ' . mysqli_connect_errno() . ').');
		return;
	}
	$mysqli->set_charset('utf8mb4');

	// 3) Decide: skip, import, or heal a partial import.
	$count = adomin_init_table_count($mysqli);
	adomin_init_log('Schema ' . $cfg['db'] . ' currently has ' . $count . ' tables.');

	if ($count >= 57)
	{
		adomin_init_log('Already installed (' . $count . ' tables) - checking the primary keys.');
		/*
		 * The tables can all exist and the install still be unusable: on TiDB
		 * the dump's AUTO_INCREMENT block fails, and a primary key without a
		 * counter makes every insert fail. One information_schema query
		 * decides, and a healthy schema costs exactly that one query.
		 */
		adomin_init_repair_auto_increment($mysqli);
		adomin_init_repair_user_defaults($mysqli);
		$mysqli->close();
		return;
	}

	if ($count > 0)
	{
		adomin_init_log('Partial install (' . $count . ' tables) - a previous import stopped early. Dropping partial tables and importing fresh...');
		$result = $mysqli->query('SHOW TABLES');
		if ($result)
		{
			while ($row = $result->fetch_row())
			{
				$table = $row[0];
				$mysqli->query('DROP TABLE IF EXISTS `' . str_replace('`', '', $table) . '`');
			}
			$result->free();
		}
		$count = adomin_init_table_count($mysqli);
		adomin_init_log('After cleanup: ' . $count . ' tables.');
	}

	// 4) Import. The dump ends with one ALTER per table that gives `id` its
	// AUTO_INCREMENT; TiDB cannot run any of them, so the tables it names are
	// repaired in step 5 instead of being reported as errors.
	$base = dirname(__DIR__);
	$schema_auto_increment = array();
	$unused = array();
	list($main_ok, $main_failed) = adomin_init_import_file($mysqli, $base . '/database.sql', 'database.sql', $schema_auto_increment);
	adomin_init_import_file($mysqli, $base . '/database/adomin-updates.sql', 'adomin-updates.sql', $unused);

	if ( ! empty($schema_auto_increment))
	{
		adomin_init_log('This server refuses ALTER TABLE ... AUTO_INCREMENT (' . count($schema_auto_increment) . ' statements); rebuilding those tables instead.');
	}

	// 5) Verify and repair.
	adomin_init_repair_auto_increment($mysqli, $schema_auto_increment);
	adomin_init_repair_user_defaults($mysqli);
	$final = adomin_init_table_count($mysqli);
	adomin_init_log('Done. Schema ' . $cfg['db'] . ' now has ' . $final . ' tables (expected 58).');
	if ($final < 57)
	{
		adomin_init_log('ERROR: import incomplete - only ' . $final . ' tables. See the per-statement errors above, or set APP_ENV=development and reload the site to see the MySQL error in the browser.');
	}
	else
	{
		adomin_init_log('Database ready. The app should now answer on /. Register an account, then change the admin password at /admin.');
	}
	$mysqli->close();
}


/*
 * Registration inserts only email/password/ip_address/time_registered into
 * `users`, but the dump makes `name`, `phone` and `schiffer` NOT NULL with no
 * default. MySQL strict mode and TiDB both reject that INSERT, CodeIgniter turns
 * the rejection into a 500, and /registration can never succeed - which is
 * exactly what a fresh deployment was doing. One information_schema query
 * decides, and a healthy schema costs only that query.
 *
 * @param mysqli $mysqli
 * @return array columns that were repaired
 */
function adomin_init_repair_user_defaults($mysqli)
{
	$repaired = array();

	$res = $mysqli->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
		WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
		  AND COLUMN_NAME IN ('name','phone','schiffer')
		  AND IS_NULLABLE = 'NO' AND COLUMN_DEFAULT IS NULL");
	if (!$res)
	{
		return $repaired;
	}

	$columns = array();
	while ($row = $res->fetch_row())
	{
		$columns[] = $row[0];
	}
	$res->free();

	$definitions = array(
		'name' => "varchar(255) NOT NULL DEFAULT ''",
		'phone' => "varchar(100) NOT NULL DEFAULT ''",
		'schiffer' => "varchar(100) NOT NULL DEFAULT ''",
	);

	foreach ($columns as $column)
	{
		if (!isset($definitions[$column]))
		{
			continue;
		}
		if ($mysqli->query('ALTER TABLE `users` MODIFY `' . $column . '` ' . $definitions[$column]))
		{
			$repaired[] = $column;
		}
		else
		{
			adomin_init_log('WARNING: could not give users.' . $column . ' a default: ' . $mysqli->error);
		}
	}

	if (!empty($repaired))
	{
		adomin_init_log('Repaired users defaults so registration can insert: ' . implode(', ', $repaired));
	}

	return $repaired;
}

/*
 * Only run when executed (`php docker/adomin-init-db.php`), not when the file is
 * included - which is how the rebuild routines are unit tested offline.
 */
$adomin_init_entry = isset($_SERVER['SCRIPT_FILENAME']) ? basename($_SERVER['SCRIPT_FILENAME']) : '';
if ($adomin_init_entry === '' || $adomin_init_entry === basename(__FILE__))
{
	adomin_init_main();
}
unset($adomin_init_entry);
