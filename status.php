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

    <?php if ($detailed && trim($notes) !== ''): ?>
        <h2 style="font-size:1rem;margin:1.5rem 0 .3rem">Initialiser log</h2>
        <pre><?php echo htmlspecialchars(trim($notes)); ?></pre>
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
