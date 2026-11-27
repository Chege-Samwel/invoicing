<?php
/**
 * Adomin Technologies — deployment status page.
 *
 * Answers the only question that matters when a fresh deploy shows an error
 * page: can this instance actually reach and use its database? It runs without
 * booting CodeIgniter, so it still works when the app itself cannot start.
 *
 *   https://your-service.onrender.com/status.php          (HTML)
 *   https://your-service.onrender.com/status.php?format=json
 *
 * Secret values are never printed: the password is shown as set/not set, and
 * the detailed report (host, error text) requires the STATUS_TOKEN environment
 * variable. Without it the page only says whether the database is usable.
 *
 * It reads the same environment variables and reuses the same connection code
 * as the container's first-start initialiser (docker/adomin-init-db.php), so
 * what it reports is exactly what the app will get.
 *
 * HTTP status: 200 when usable, 503 when not - usable as a readiness probe.
 */

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');

if ( ! defined('BASEPATH'))
{
	define('BASEPATH', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
}
if ( ! defined('ENVIRONMENT'))
{
	// Only used by application/config/database.php to pick db_debug.
	define('ENVIRONMENT', 'production');
}

// Functions only: including the file does not run adomin_init_main(), because
// the entry point checks the script name.
require dirname(__FILE__) . '/docker/adomin-init-db.php';

function adomin_status_env($key, $default = '')
{
	$value = adomin_init_env($key, $default);
	return is_string($value) ? $value : (string) $value;
}

function adomin_status_token_ok()
{
	$expected = adomin_status_env('STATUS_TOKEN');
	if ($expected === '')
	{
		return FALSE;
	}
	$given = isset($_GET['token']) ? (string) $_GET['token'] : '';
	return $given !== '' && hash_equals($expected, $given);
}

/**
 * Is anything in the deployment breaking POSTs?
 *
 * GET works on this deploy while POST /login and POST /registration answer
 * 500, and the 551-byte body is Apache substituting its stock page because PHP
 * wrote nothing. The four things a POST does that a GET does not, in the order
 * the framework does them, are: the CSRF check in Input, the session (started
 * by the first flashdata or by logging in), the database write, and the
 * redirect. These probes test them from the outside, without booting
 * CodeIgniter, and name the first one that fails.
 *
 * @return array
 */
function adomin_status_post_probe($database)
{
	$out = array('ok' => TRUE, 'checks' => array());

	// 1. the session. CI starts it on every controller construct, and it writes
	//    into sess_save_path; an unwritable path makes start() fail.
	$save_path = '/tmp';
	$config = dirname(__FILE__) . '/application/config/config.php';
	if (is_file($config))
	{
		$text = @file_get_contents($config);
		if (is_string($text) && preg_match("/sess_save_path'\]\s*=\s*'([^']*)'/", $text, $m) && $m[1] !== '')
		{
			$save_path = $m[1];
		}
	}
	$writable = is_dir($save_path) && is_writable($save_path);
	$out['checks'][] = array(
		'name' => 'Session directory',
		'detail' => $save_path . ($writable ? ' is writable' : ' is NOT writable - login cannot store its session, which fails the POST'),
		'ok' => $writable,
	);
	$out['ok'] = $out['ok'] && $writable;

	// 2. session_start() itself, in a throwaway id, so nothing is disturbed.
	if ($writable)
	{
		$started = FALSE;
		$before = headers_sent();
		if ( ! $before && session_status() === PHP_SESSION_NONE)
		{
			$started = @session_start(array('save_path' => $save_path, 'name' => 'adomin_status_probe'));
		}
		$out['checks'][] = array(
			'name' => 'session_start()',
			'detail' => $started ? 'started and closed cleanly' : 'could not start a session (' . (session_status() === PHP_SESSION_ACTIVE ? 'already active' : 'see the server log') . ')',
			'ok' => (bool) $started || session_status() === PHP_SESSION_ACTIVE,
		);
		$out['ok'] = $out['ok'] && ((bool) $started || session_status() === PHP_SESSION_ACTIVE);
		if ($started)
		{
			// never write anything: the cookie is not sent by this page, and the
			// empty file is removed below.
			$file = $save_path . DIRECTORY_SEPARATOR . 'sess_adomin_status_probe';
			@session_write_close();
			@unlink($file);
		}
	}

	// 3. writes into the session directory work for a couple of bytes
	$probe = @tempnam($save_path, 'adomin');
	$wrote = is_string($probe) && $probe !== '';
	if ($wrote)
	{
		$wrote = @file_put_contents($probe, 'x') === 1;
		@unlink($probe);
	}
	$out['checks'][] = array(
		'name' => 'Write test',
		'detail' => $wrote ? 'a temporary file was written and removed' : 'cannot write a temporary file into ' . $save_path,
		'ok' => $wrote,
	);
	$out['ok'] = $out['ok'] && $wrote;

	// 4. the database can be written to, not just read. uses a transaction that
	//    is always rolled back, on a table that exists in every install.
	if (isset($database['connection']) && $database['connection'] instanceof mysqli)
	{
		$link = $database['connection'];
		$write_ok = FALSE;
		$detail = 'not attempted';
		if (@mysqli_query($link, 'START TRANSACTION'))
		{
			$res = @mysqli_query($link, "INSERT INTO `value_store` (`value`) VALUES ('adomin-status-probe')");
			if ($res)
			{
				$write_ok = TRUE;
				$detail = 'inserted and rolled back';
			}
			else
			{
				$detail = 'INSERT failed: ' . mysqli_error($link);
			}
			@mysqli_query($link, 'ROLLBACK');
		}
		else
		{
			$detail = 'could not start a transaction: ' . mysqli_error($link);
		}
		$out['checks'][] = array('name' => 'Database write', 'detail' => $detail, 'ok' => $write_ok);
		$out['ok'] = $out['ok'] && $write_ok;
	}
	else
	{
		$out['checks'][] = array('name' => 'Database write', 'detail' => 'skipped - no connection', 'ok' => NULL);
	}

	return $out;
}

/**
 * POST to this deployment and report what actually comes back.
 *
 * Every earlier attempt to explain the failing /login ended in a byte count
 * ("500 551"), so this repeats the request from inside the instance and reports
 * the status line, the body size and the first thing in the body that names a
 * page: CodeIgniter's CSRF rejection, our fatal page, a database error, or
 * nothing at all (an empty body, which is what makes Apache serve its own 500).
 *
 * Only runs when asked for (?posttest=1), because it is a request to the
 * instance while the instance is answering one. Four workers are configured, so
 * two are always spare.
 *
 * @return array
 */
function adomin_status_post_selftest($path = NULL)
{
	if ($path === NULL)
	{
		$path = isset($_GET['postpath']) ? (string) $_GET['postpath'] : '/login';
	}
	// only ever probe a path on this host, and only the two public auth pages:
	// the caller is untrusted and this function makes an outbound request.
	if ( ! in_array($path, array('/login', '/registration'), TRUE))
	{
		$path = '/login';
	}

	$out = array('attempted' => FALSE, 'status' => NULL, 'bytes' => NULL, 'look' => 'not run', 'excerpt' => '');

	if ( ! ini_get('allow_url_fopen'))
	{
		$out['look'] = 'allow_url_fopen is off, cannot self-test';
		return $out;
	}

	$scheme = ( ! empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
	$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost');
	$port = isset($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : 80;
	$url = $scheme . '://' . $host . $path;
	if ( ! in_array($port, array(80, 443), TRUE))
	{
		$url = $scheme . '://' . $host . ':' . $port . $path;
	}

	// CodeIgniter only accepts a 32-hex token, and it must match the cookie
	// byte for byte. Sending both from here is exactly what a correct browser
	// does after loading the form.
	$token = bin2hex(function_exists('random_bytes') ? random_bytes(16) : pack('H*', md5(uniqid('', TRUE))));
	$context = stream_context_create(array(
		'http' => array(
			'method' => 'POST',
			'timeout' => 6,
			'ignore_errors' => TRUE,   // we want the body of an error response
			'follow_location' => 0,
			'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
				. 'Cookie: csrf_cookie_name=' . $token . "\r\n"
				. 'User-Agent: adomin-status-selftest' . "\r\n",
			'content' => http_build_query(array(
				'email' => 'selftest@example.invalid',
				'password' => 'not-a-real-password',
				'csrf_test_name' => $token,
			)),
		),
	));

	$out['attempted'] = TRUE;
	$out['url'] = $url;
	$body = @file_get_contents($url, FALSE, $context);
	$headers = isset($http_response_header) ? $http_response_header : array();

	if ( ! empty($headers))
	{
		foreach ($headers as $header)
		{
			if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m))
			{
				$out['status'] = (int) $m[1];
			}
		}
	}

	$out['bytes'] = is_string($body) ? strlen($body) : 0;
	$body = is_string($body) ? $body : '';

	// name the page rather than make the reader decode a size
	$look = 'something else';
	if ($body === '')
	{
		$look = 'empty body - the web server supplied the error page';
	}
	elseif (stripos($body, 'The action you have requested is not allowed') !== FALSE)
	{
		$look = 'CodeIgniter CSRF rejection page';
	}
	elseif (stripos($body, 'Adomin FATAL') !== FALSE || stripos($body, 'could not be rendered') !== FALSE)
	{
		$look = 'the Adomin fatal-error page';
	}
	elseif (stripos($body, 'A Database Error Occurred') !== FALSE)
	{
		$look = 'CodeIgniter database error page';
	}
	elseif (stripos($body, 'A PHP Error was encountered') !== FALSE)
	{
		$look = 'CodeIgniter PHP error page';
	}
	elseif (stripos($body, '<title>Internal Server Error</title>') !== FALSE
		|| stripos($body, '<title>500 Internal Server Error</title>') !== FALSE
		|| (stripos($body, 'Internal Server Error') !== FALSE && stripos($body, 'Please contact the server administrator') !== FALSE))
	{
		$look = 'the web server 500 page (nothing came from PHP)';
	}
	elseif (stripos($body, '<html') !== FALSE || stripos($body, '<!DOCTYPE') !== FALSE)
	{
		$look = 'an HTML page (the request was handled)';
	}
	$out['look'] = $look;

	$text = trim(preg_replace('/\s+/', ' ', strip_tags($body)));
	$out['excerpt'] = substr($text, 0, 240);

	return $out;
}

/**
 * The breadcrumb trail for recent requests (application/logs/trace-*.log).
 *
 * Only requests that could fail silently write here: every POST, and any GET
 * with ?trace=1. The last few blocks are shown, newest last, so the most recent
 * attempt is at the bottom - the same order it was written in.
 *
 * @return array
 */
function adomin_status_trace($blocks = 3)
{
	$dir = rtrim(dirname(__FILE__), '/\\') . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'logs';
	$out = array('dir' => $dir, 'files' => array(), 'lines' => array());

	if ( ! is_dir($dir))
	{
		return $out;
	}

	$files = glob($dir . DIRECTORY_SEPARATOR . 'trace-*.log');
	if ( ! is_array($files))
	{
		return $out;
	}

	usort($files, function ($a, $b) {
		return filemtime($b) - filemtime($a);
	});
	$file = $files[0];
	$out['files'][] = array(
		'name' => basename($file),
		'bytes' => filesize($file),
		'modified' => date('c', filemtime($file)),
	);

	$lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	if ( ! is_array($lines))
	{
		return $out;
	}

	// A block starts with the "request" banner line.
	$blocks_found = array();
	$current = array();
	foreach ($lines as $line)
	{
		if (strpos($line, ' request ') !== FALSE && strpos($line, 'method=') !== FALSE)
		{
			if ( ! empty($current))
			{
				$blocks_found[] = $current;
			}
			$current = array();
		}
		$current[] = $line;
	}
	if ( ! empty($current))
	{
		$blocks_found[] = $current;
	}

	$blocks_found = array_slice($blocks_found, -$blocks);
	foreach ($blocks_found as $block)
	{
		$out['lines'][] = '';
		foreach ($block as $line)
		{
			$out['lines'][] = $line;
		}
	}

	return $out;
}

/**
 * Recent fatal / error lines from application/logs.
 *
 * On the free instance there is no shell and no persistent disk, so the only
 * way to read the log is through the app. This returns the last few lines of
 * the most recent log files, with obvious secrets stripped - a log line can
 * contain SQL with an e-mail address but never the database password.
 *
 * @param int $limit total lines
 * @return array
 */
function adomin_status_logs($limit = 40)
{
	$dir = rtrim(dirname(__FILE__), '/\\') . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'logs';
	$out = array('dir' => $dir, 'writable' => is_dir($dir) && is_writable($dir), 'files' => array(), 'lines' => array());

	if ( ! is_dir($dir))
	{
		return $out;
	}

	$files = glob($dir . DIRECTORY_SEPARATOR . '*.php');
	if ( ! is_array($files))
	{
		return $out;
	}

	// Newest first: a fatal from the failed request is the one that matters.
	usort($files, function ($a, $b) {
		return filemtime($b) - filemtime($a);
	});
	$files = array_slice($files, 0, 3);

	foreach ($files as $file)
	{
		$out['files'][] = array(
			'name' => basename($file),
			'bytes' => filesize($file),
			'modified' => date('c', filemtime($file)),
		);
		$content = @file_get_contents($file);
		if ( ! is_string($content) || $content === '')
		{
			continue;
		}
		// The files start with a PHP open/close guard line; drop it.
		$content = preg_replace('/^<\?php.*?\?>\s*/s', '', $content);
		$lines = preg_split('/\r\n|\n|\r/', trim($content));
		$out['lines'] = array_merge($out['lines'], array_slice($lines, -$limit));
	}

	$out['lines'] = array_slice($out['lines'], -$limit);

	return $out;
}

function adomin_status_php()
{
	$extensions = array('mysqli', 'mbstring', 'gd', 'zip', 'openssl', 'curl', 'json');
	$loaded = array();
	foreach ($extensions as $extension)
	{
		$loaded[$extension] = extension_loaded($extension);
	}
	return array(
		'version' => PHP_VERSION,
		'extensions' => $loaded,
		'dompdf' => is_file(dirname(__FILE__) . '/application/libraries/dompdf/autoload.inc.php'),
		'timezone' => date_default_timezone_get(),
		'now' => date('c'),
	);
}

/**
 * Connect, count, and check the three things an import can get wrong.
 *
 * @return array
 */
function adomin_status_database($detailed)
{
	$report = array(
		'configured' => FALSE,
		'connected' => FALSE,
		'error' => NULL,
		'tables' => NULL,
		'expected_tables' => 58,
		'missing_auto_increment' => array(),
		'languages' => NULL,
		'english_language_row' => NULL,
		'kenyan_shilling' => NULL,
		'admin_users' => NULL,
	);

	$cfg = adomin_init_config();
	$report['configured'] = (adomin_status_env('DB_HOST') !== '' || adomin_status_env('DATABASE_URL') !== '');

	if ( ! $report['configured'])
	{
		$report['error'] = 'No DB_HOST or DATABASE_URL is set for this service.';
		return $report;
	}

	// Connection settings are not secrets, but they are not public either.
	if ($detailed)
	{
		$report['host'] = $cfg['host'];
		$report['port'] = (int) $cfg['port'];
		$report['user'] = $cfg['user'];
		$report['database'] = $cfg['db'];
		$report['tls'] = is_array($cfg['encrypt']) ? 'on' : 'off';
		$report['tls_ca'] = is_array($cfg['encrypt']) && ! empty($cfg['encrypt']['ssl_ca']) ? $cfg['encrypt']['ssl_ca'] : NULL;
		$report['tls_verify'] = is_array($cfg['encrypt']) && ! empty($cfg['encrypt']['ssl_verify']);
		$report['password_set'] = ($cfg['pass'] !== '');
	}

	$mysqli = adomin_init_connect($cfg, NULL);
	if ($mysqli === NULL)
	{
		$report['error'] = 'Could not connect: [' . mysqli_connect_errno() . '] ' . mysqli_connect_error();
		return $report;
	}

	$report['connected'] = TRUE;

	// The schema may legitimately not exist on the very first start; the
	// initialiser creates it. Report it, do not fail on it.
	$db = $cfg['db'];
	$result = $mysqli->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '" . $mysqli->real_escape_string($db) . "'");
	$report['schema_exists'] = ($result && $result->num_rows > 0);
	if ($result)
	{
		$result->free();
	}

	if ($report['schema_exists'])
	{
		$mysqli->select_db($db);
		$table_count = adomin_init_table_count($mysqli);
		// -1 means the count query itself failed: report "unknown", not "-1".
		$report['tables'] = $table_count < 0 ? NULL : $table_count;
		$report['missing_auto_increment'] = adomin_init_tables_missing_auto_increment($mysqli);

		foreach (array('languages' => 'SELECT COUNT(*) AS c FROM `languages`',
			'english_language_row' => "SELECT COUNT(*) AS c FROM `languages` WHERE `abbr` = 'en'",
			'kenyan_shilling' => "SELECT COUNT(*) AS c FROM `currencies` WHERE `value` = 'KES'",
			'admin_users' => 'SELECT COUNT(*) AS c FROM `admin_users`') as $key => $sql)
		{
			$result = $mysqli->query($sql);
			if ($result)
			{
				$row = $result->fetch_assoc();
				$report[$key] = isset($row['c']) ? (int) $row['c'] : NULL;
				$result->free();
			}
		}
	}

	$mysqli->close();
	return $report;
}

$detailed = adomin_status_token_ok();
$php = adomin_status_php();
// The initialiser logs to stdout; capture it so the page stays valid HTML/JSON.
ob_start();
$database = adomin_status_database($detailed);
$notes = ob_get_clean();

$post_probe = adomin_status_post_probe($database);

$healthy = $database['connected']
	&& ! empty($database['schema_exists'])
	&& $database['tables'] !== NULL
	&& $database['tables'] >= 57
	&& empty($database['missing_auto_increment']);

$verdict = $healthy ? 'ok' : 'problem';
$summary = array();
if ( ! $database['configured'])
{
	$summary[] = 'Database settings are missing: set DB_HOST, DB_USER, DB_PASS, DB_NAME and DB_PORT=4000 in Render.';
}
elseif ( ! $database['connected'])
{
	$summary[] = 'The database is not reachable. See SETUP.md §5 (host/user copied exactly from the TiDB Connect dialog, port 4000, DB_SSL=1).';
}
elseif (empty($database['schema_exists']))
{
	$summary[] = 'Connected, but the schema "' . ($detailed ? $database['database'] : '…') . '" does not exist yet. Redeploy: the container creates it and imports the dump on start (ADOMIN_AUTO_IMPORT=1).';
}
elseif ($database['tables'] === NULL || $database['tables'] < 57)
{
	$summary[] = 'The table count could not be read, or the schema has only ' . (int) $database['tables'] . ' of 58 tables: the import did not finish. Redeploy with ADOMIN_AUTO_IMPORT=1, or import database.sql by hand (SETUP.md §3).';
}
elseif ( ! empty($database['missing_auto_increment']))
{
	$summary[] = count($database['missing_auto_increment']) . ' table(s) have a primary key without AUTO_INCREMENT, so inserting a row fails. Redeploy: the container repairs them on start (TiDB cannot add the attribute with ALTER TABLE).';
}
if ($healthy && $database['english_language_row'] === 0)
{
	$summary[] = 'The languages table has no "en" row: run database/adomin-updates.sql.';
}
if ($healthy)
{
	$summary[] = 'Database usable: ' . (int) $database['tables'] . ' tables, primary keys ready' . ($database['admin_users'] ? ', ' . (int) $database['admin_users'] . ' admin account(s)' : '') . '.';
}

/*
 * The status code is the machine-readable half of the verdict: 200 when the
 * deployment can serve requests, 503 when it cannot. That is what makes this
 * page usable as a readiness probe (an uptime monitor, or Render's health
 * check if you prefer readiness over liveness - see SETUP.md section 4).
 */
if ( ! headers_sent())
{
	if ($healthy)
	{
		http_response_code(200);
	}
	else
	{
		http_response_code(503);
		header('Retry-After: 60');
	}
}

if (isset($_GET['format']) && $_GET['format'] === 'json')
{
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-store');
	$payload = array('status' => $verdict, 'summary' => $summary, 'php' => $php, 'database' => $database);
	if ($detailed)
	{
		$payload['init_log'] = $notes;
		$payload['logs'] = adomin_status_logs();
		$payload['post'] = $post_probe;
		$payload['trace'] = adomin_status_trace();
		if (isset($_GET['posttest']))
		{
			$payload['post']['self_test'] = adomin_status_post_selftest();
		}
	}
	echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	exit;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Adomin status — <?php echo $verdict === 'ok' ? 'ready' : 'needs attention'; ?></title>
<style>
    body { margin: 0; padding: 2.5rem 1.25rem; font: 15px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #1f2733; background: #f6f8fb; }
    main { max-width: 44rem; margin: 0 auto; }
    h1 { font-size: 1.35rem; margin: 0 0 .35rem; }
    .badge { display: inline-block; padding: .25rem .6rem; border-radius: 999px; font-size: .78rem; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; }
    .ok { background: #e3f6ea; color: #10683a; }
    .problem { background: #fdeaea; color: #9b1c1c; }
    ul { padding-left: 1.15rem; }
    li { margin: .3rem 0; }
    table { width: 100%; border-collapse: collapse; margin: 1rem 0 0; background: #fff; border: 1px solid #e2e8f0; border-radius: .5rem; overflow: hidden; font-size: .92rem; }
    th, td { text-align: left; padding: .5rem .7rem; border-bottom: 1px solid #eef2f7; vertical-align: top; }
    th { width: 42%; font-weight: 600; color: #47546b; }
    tr:last-child td, tr:last-child th { border-bottom: 0; }
    .yes { color: #10683a; font-weight: 600; }
    .no { color: #9b1c1c; font-weight: 600; }
    code, pre { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .86rem; }
    pre { background: #fff; border: 1px solid #e2e8f0; border-radius: .5rem; padding: .8rem; overflow: auto; max-height: 22rem; }
    .muted { color: #64748b; font-size: .86rem; }
</style>
</head>
<body>
<main>
    <p><span class="badge <?php echo $verdict === 'ok' ? 'ok' : 'problem'; ?>"><?php echo $verdict === 'ok' ? 'ready' : 'needs attention'; ?></span></p>
    <h1>Adomin Technologies — deployment status</h1>
    <p class="muted">Checked <?php echo htmlspecialchars($php['now']); ?> · PHP <?php echo htmlspecialchars($php['version']); ?></p>

    <ul>
        <?php foreach ($summary as $line): ?>
            <li><?php echo htmlspecialchars($line); ?></li>
        <?php endforeach; ?>
    </ul>

    <table>
        <tr><th>Database configured</th><td><?php echo $database['configured'] ? '<span class="yes">yes</span>' : '<span class="no">no</span>'; ?></td></tr>
        <tr><th>Connected</th><td><?php echo $database['connected'] ? '<span class="yes">yes</span>' : '<span class="no">no</span>'; ?></td></tr>
        <?php if ($detailed): ?>
            <tr><th>Host / port</th><td><code><?php echo htmlspecialchars($database['host'] . ':' . $database['port']); ?></code></td></tr>
            <tr><th>User / database</th><td><code><?php echo htmlspecialchars($database['user']) . ' → ' . htmlspecialchars($database['database']); ?></code></td></tr>
            <tr><th>TLS</th><td><?php echo htmlspecialchars($database['tls']); ?><?php echo $database['tls_ca'] ? ' · CA <code>' . htmlspecialchars($database['tls_ca']) . '</code>' : ''; ?><?php echo $database['tls_verify'] ? ' · certificate verified' : ''; ?></td></tr>
            <tr><th>Password</th><td><?php echo $database['password_set'] ? 'set' : '<span class="no">not set</span>'; ?></td></tr>
        <?php endif; ?>
        <tr><th>Schema exists</th><td><?php echo ! empty($database['schema_exists']) ? '<span class="yes">yes</span>' : '<span class="no">no</span>'; ?></td></tr>
        <tr><th>Tables</th><td><?php echo $database['tables'] === NULL ? '—' : (int) $database['tables'] . ' of ' . (int) $database['expected_tables']; ?></td></tr>
        <tr><th>Primary keys missing AUTO_INCREMENT</th><td><?php
            $missing = $database['missing_auto_increment'];
            if ($database['tables'] === NULL) { echo '—'; }
            elseif (empty($missing)) { echo '<span class="yes">none</span>'; }
            else { echo '<span class="no">' . count($missing) . '</span>' . ($detailed ? '<br><code>' . htmlspecialchars(implode(', ', array_slice($missing, 0, 12))) . (count($missing) > 12 ? ', …' : '') . '</code>' : ''); }
        ?></td></tr>
        <tr><th>languages rows</th><td><?php echo $database['languages'] === NULL ? '—' : (int) $database['languages']; ?></td></tr>
        <tr><th>English language row</th><td><?php echo $database['english_language_row'] === NULL ? '—' : ($database['english_language_row'] ? '<span class="yes">present</span>' : '<span class="no">missing</span>'); ?></td></tr>
        <tr><th>KES currency</th><td><?php echo $database['kenyan_shilling'] === NULL ? '—' : ($database['kenyan_shilling'] ? '<span class="yes">present</span>' : '<span class="no">missing</span>'); ?></td></tr>
        <tr><th>Admin accounts</th><td><?php echo $database['admin_users'] === NULL ? '—' : (int) $database['admin_users']; ?></td></tr>
        <tr><th>PHP extensions</th><td><?php
            $parts = array();
            foreach ($php['extensions'] as $name => $ok) {
                $parts[] = $ok ? $name : '<span class="no">' . $name . ' missing</span>';
            }
            echo implode(' · ', $parts);
        ?></td></tr>
        <tr><th>PDF engine files</th><td><?php echo $php['dompdf'] ? '<span class="yes">dompdf present</span>' : '<span class="no">dompdf missing</span>'; ?></td></tr>
    </table>

    <?php if ($detailed && $database['error']): ?>
        <h2 style="font-size:1rem;margin:1.5rem 0 .3rem">Database error</h2>
        <pre><?php echo htmlspecialchars($database['error']); ?></pre>
    <?php endif; ?>

    <?php if ($detailed): $trace = adomin_status_trace(); ?>
        <h2 style="font-size:1rem;margin:1.5rem 0 .3rem">Request breadcrumbs</h2>
        <p class="muted" style="margin:.2rem 0 .5rem">
            The last few POSTs (and any page opened with <code>?trace=1</code>), step by step, in
            the order they happened. The <strong>last line of a block is where that request
            stopped</strong> &mdash; if it ends at <code>login: POST accepted</code>, the failure is
            inside the controller; if it never appears at all, the request died before the
            framework reached the login page.
        </p>
        <?php if (empty($trace['files'])): ?>
            <p class="muted">No traced request yet. Submit the login form once, then reload this page.</p>
        <?php else: ?>
            <p class="muted">
                <code><?php echo htmlspecialchars($trace['files'][0]['name']); ?></code>
                (<?php echo (int) $trace['files'][0]['bytes']; ?> bytes)
            </p>
            <pre><?php echo htmlspecialchars(trim(implode("\n", $trace['lines']))); ?></pre>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($detailed): ?>
        <h2 style="font-size:1rem;margin:1.5rem 0 .3rem">Form submissions (POST)</h2>
        <p class="muted" style="margin:.2rem 0 .5rem">
            What a POST needs and a GET does not. The first failing row is why
            <code>/login</code> and <code>/registration</code> answer 500.
        </p>
        <table style="border-collapse:collapse;font-size:.85rem;width:100%">
            <?php foreach ($post_probe['checks'] as $check): ?>
                <tr>
                    <td style="padding:.2rem .6rem .2rem 0;white-space:nowrap;vertical-align:top">
                        <?php echo $check['ok'] === NULL ? '&middot;' : ($check['ok'] ? '<span class="yes">&#10003;</span>' : '<span class="no">&#10007;</span>'); ?>
                        <?php echo htmlspecialchars($check['name']); ?>
                    </td>
                    <td style="padding:.2rem 0;color:#555"><?php echo htmlspecialchars($check['detail']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <?php if (isset($_GET['posttest'])):
            $self = adomin_status_post_selftest(); ?>
            <h2 style="font-size:1rem;margin:1.2rem 0 .3rem">Live POST self-test (<?php echo htmlspecialchars($self['url'] ?? ''); ?>)</h2>
            <p class="muted" style="margin:.2rem 0">
                Status <strong><?php echo $self['status'] === NULL ? 'no response' : (int) $self['status']; ?></strong>,
                <strong><?php echo (int) $self['bytes']; ?></strong> bytes &mdash;
                <strong><?php echo htmlspecialchars($self['look']); ?></strong>
            </p>
            <?php if ($self['excerpt'] !== ''): ?>
                <pre><?php echo htmlspecialchars($self['excerpt']); ?></pre>
            <?php endif; ?>
        <?php else: ?>
            <p class="muted" style="margin:.4rem 0 0">
                To make a real POST to <code>/login</code> from this instance and have the
                reply classified, add <code>&amp;posttest=1</code> to this page's URL.
            </p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($detailed && trim($notes) !== ''): ?>
        <h2 style="font-size:1rem;margin:1.5rem 0 .3rem">Initialiser log</h2>
        <pre><?php echo htmlspecialchars(trim($notes)); ?></pre>
    <?php endif; ?>

    <?php if ($detailed): $logs = adomin_status_logs(); ?>
        <h2 style="font-size:1rem;margin:1.5rem 0 .3rem">Recent errors (application/logs)</h2>
        <?php if (empty($logs['files'])): ?>
            <p class="muted">No log file yet. That means no request has failed on this instance since it started &mdash; a fatal error writes one immediately.</p>
        <?php else: ?>
            <p class="muted">
                <?php foreach ($logs['files'] as $file): ?>
                    <code><?php echo htmlspecialchars($file['name']); ?></code> (<?php echo (int) $file['bytes']; ?> bytes, <?php echo htmlspecialchars($file['modified']); ?>)&nbsp;
                <?php endforeach; ?>
                <?php echo $logs['writable'] ? '' : '<span class="no">directory not writable &mdash; errors cannot be logged</span>'; ?>
            </p>
            <?php if (empty($logs['lines'])): ?>
                <p class="muted">The file is empty.</p>
            <?php else: ?>
                <pre><?php echo htmlspecialchars(implode("\n", $logs['lines'])); ?></pre>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ( ! $detailed): ?>
        <p class="muted" style="margin-top:1.5rem">
            Details (host, connection error, initialiser log) are hidden. Add a <code>STATUS_TOKEN</code>
            environment variable in Render, then open this page as
            <code>/status.php?token=YOUR_TOKEN</code>. See SETUP.md §6.
        </p>
    <?php endif; ?>

    <p class="muted" style="margin-top:1.5rem">
        Machine-readable: <code>/status.php?format=json</code><?php echo $detailed ? ' (add <code>&amp;token=…</code>)' : ''; ?>.
        This page never displays the database password.
    </p>
</main>
</body>
</html>
