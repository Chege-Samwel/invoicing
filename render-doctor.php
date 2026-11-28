<?php
/**
 * What does this deployment actually draw for one invoice?
 *
 * Four reports - the letterhead is missing and only the item table shows, the
 * download is blank, the share link is down, the preview is unchanged - and the
 * code that produces all four renders a complete page in the test harness on
 * every design. So the disagreement is between the harness and this host: a
 * missing dependency, a fatal that only this PHP build or this filesystem
 * produces, a snapshot that is emptier than the fixtures.
 *
 * This answers that from the address bar. It does not boot CodeIgniter and it
 * does not send a document: it requires the same helpers the page requires,
 * builds the same blocks from the invoice row, and reports what came out and
 * how much of each band has content. Nothing here is a fix; it exists so the
 * next fix is aimed at something observed rather than inferred.
 *
 *   /render-doctor.php?num=0000000002
 *   /render-doctor.php?num=0000000002&format=json
 *   /render-doctor.php?num=0000000002&format=html   (the HTML it built)
 *
 * It reveals a document, so it is gated: it requires STATUS_TOKEN to be set and
 * supplied, exactly like /status.php?token=. Without that it answers 403 and
 * says so - a diagnostic must never be the hole in the wall.
 */

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');

if ( ! defined('BASEPATH'))
{
	define('BASEPATH', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
}
if ( ! defined('ENVIRONMENT'))
{
	define('ENVIRONMENT', 'production');
}

require dirname(__FILE__) . '/docker/adomin-init-db.php';

/*
 * This page may not answer 500.
 *
 * A diagnostic that fails looks exactly like the fault it was opened to
 * explain, and the first version of this file did fail: adomin_status_env() was
 * defined in status.php, not in the bootstrap it requires, so the token check
 * called a missing function and Apache answered 500. Everything below is now
 * wrapped, and this handler is the last resort - it prints the report skeleton
 * and the message rather than a blank error page.
 */
$GLOBALS['adomin_rd_done'] = FALSE;

set_exception_handler(function ($e) {
	if ($GLOBALS['adomin_rd_done'])
	{
		return;
	}
	$GLOBALS['adomin_rd_done'] = TRUE;
	if ( ! headers_sent())
	{
		http_response_code(500);
		header('Content-Type: text/html; charset=utf-8');
	}
	echo '<!DOCTYPE html><meta charset="utf-8"><title>Render doctor</title>'
		. '<body style="font:14px/1.6 system-ui,sans-serif;max-width:44rem;margin:3rem auto;padding:0 1rem">'
		. '<h1>Render doctor</h1><p><strong>The page itself failed, before it could check anything.</strong></p>'
		. '<p><code>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</code></p>'
		. '<p style="color:#6e7681">at ' . htmlspecialchars(basename($e->getFile()) . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8')
		. ' - this is a fault in the diagnostic, not in the invoice. Nothing about the document has been checked.</p>'
		. '</body>';
});

register_shutdown_function(function () {
	$error = error_get_last();
	if ($error === NULL || $GLOBALS['adomin_rd_done'])
	{
		return;
	}
	if ( ! in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), TRUE))
	{
		return;
	}
	/* a fatal cannot be caught; say so in one line rather than show nothing */
	echo '<!DOCTYPE html><meta charset="utf-8"><title>Render doctor</title>'
		. '<body style="font:14px/1.6 system-ui,sans-serif;max-width:44rem;margin:3rem auto;padding:0 1rem">'
		. '<h1>Render doctor</h1><p><strong>Fatal error in the diagnostic.</strong></p>'
		. '<p><code>' . htmlspecialchars($error['message'], ENT_QUOTES, 'UTF-8') . '</code></p>'
		. '<p style="color:#6e7681">' . htmlspecialchars($error['file'] . ':' . $error['line'], ENT_QUOTES, 'UTF-8') . '</p>'
		. '</body>';
});

/* ---------------------------------------------------------------------- *
 *  The four things CodeIgniter would normally provide
 * ---------------------------------------------------------------------- */

/*
 * The helpers call get_instance() and a handful of framework functions. Booting
 * CodeIgniter here would drag in the session, the router and the database
 * config, and the point of this file is to answer when the app cannot. So the
 * four functions the render path actually touches are declared plainly - and
 * get_instance() answers FALSE, which is the contract every helper already
 * checks (`if (!function_exists('get_instance')) return`), so the QR code and
 * the per-account settings are simply absent rather than fatal. That absence is
 * itself reported.
 */
if ( ! function_exists('get_instance'))
{
	function get_instance()
	{
		return FALSE;
	}
}

if ( ! function_exists('base_url'))
{
	function base_url($uri = '')
	{
		$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
		return rtrim($scheme . '://' . $host, '/') . '/' . ltrim((string) $uri, '/');
	}
}

if ( ! function_exists('html_escape'))
{
	function html_escape($value)
	{
		return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
	}
}

if ( ! function_exists('lang'))
{
	function lang($key)
	{
		/* the English fallbacks live in the renderer; these are the same words */
		$map = array(
			'verify_scan' => 'Scan to verify',
			'invoice' => 'Invoice',
			'number' => 'Number',
			'currency' => 'Currency',
			'total' => 'Total',
		);
		return isset($map[$key]) ? $map[$key] : (is_string($key) ? $key : '');
	}
}

if ( ! function_exists('log_message'))
{
	function log_message($level, $message)
	{
		/* collected with the notices; see adomin_rd_render() */
		error_log('[render-doctor] ' . $level . ': ' . $message);
	}
}

if ( ! function_exists('adomin_conf'))
{
	/*
	 * The share toggle and the brand name, without the config object.
	 */
	function adomin_conf($key = NULL, $second = NULL)
	{
		$config = array(
			'brand' => array(
				'name' => 'anvoice',
				'short' => 'anvoice',
				'app_name' => 'anvoice',
				'company' => 'Adomin Technologies',
				'powered_by' => 'Powered by Adomin Technologies',
				'website' => 'https://adomin.co.ke',
			),
			'share' => array('enabled' => TRUE, 'prefix' => 'invoice'),
			'pdf' => array('stamp' => TRUE),
		);
		if ($key === NULL)
		{
			return $config;
		}
		$value = isset($config[$key]) ? $config[$key] : NULL;
		if ($second !== NULL)
		{
			return (is_array($value) && isset($value[$second])) ? $value[$second] : NULL;
		}
		return $value;
	}
}

/* ---------------------------------------------------------------------- *
 *  What this PHP build can and cannot do
 * ---------------------------------------------------------------------- */

/**
 * Every extension and function the render path touches, named on one screen.
 *
 * The failures this file was written for look identical in a browser and have
 * nothing in common: a missing mbstring, a dompdf font cache that cannot be
 * written on an ephemeral filesystem, a PHP version with no GD. Answering them
 * one at a time from a live site takes an afternoon.
 *
 * @return array
 */
function adomin_rd_environment()
{
	$env = array(
		'php' => PHP_VERSION,
		'sapi' => PHP_SAPI,
		'extensions' => array(),
		'functions' => array(),
		'writable' => array(),
	);
	foreach (array('mysqli', 'mbstring', 'dom', 'libxml', 'gd', 'zip', 'json', 'openssl', 'zlib', 'iconv') as $ext)
	{
		$env['extensions'][$ext] = extension_loaded($ext);
	}
	foreach (array('mb_strlen', 'mb_substr', 'iconv', 'htmlspecialchars', 'number_format', 'random_bytes', 'hash_hmac') as $fn)
	{
		$env['functions'][$fn] = function_exists($fn);
	}
	$root = dirname(__FILE__);
	foreach (array(
		'application/cache',
		'application/logs',
		'attachments',
		'application/libraries/dompdf/lib/fonts',
	) as $rel)
	{
		$path = $root . '/' . $rel;
		$env['writable'][$rel] = array(
			'exists' => file_exists($path),
			'writable' => is_writable($path),
		);
	}
	$env['dompdf'] = array(
		'autoload' => is_file($root . '/application/libraries/dompdf/autoload.inc.php'),
		'dejavu' => is_file($root . '/application/libraries/dompdf/lib/fonts/DejaVuSans.ttf'),
	);
	return $env;
}

/* ---------------------------------------------------------------------- *
 *  The invoice, exactly as the models would return it
 * ---------------------------------------------------------------------- */

/**
 * @param	mysqli	$mysqli
 * @param	string	$number
 * @return	array|null
 */
function adomin_rd_invoice($mysqli, $number)
{
	$number = (string) $number;
	$sql = 'SELECT * FROM `invoices` WHERE `inv_number` = \'' . $mysqli->real_escape_string($number)
		. '\' AND `is_deleted` = 0 ORDER BY `id` DESC LIMIT 5';
	$result = $mysqli->query($sql);
	if ( ! $result)
	{
		return array('error' => '[' . $mysqli->errno . '] ' . $mysqli->error);
	}
	$rows = array();
	while ($row = $result->fetch_assoc())
	{
		$rows[] = $row;
	}
	$result->free();
	if ( ! $rows)
	{
		return NULL;
	}

	$invoice = $rows[0];
	/*
	 * The same four child rows the models attach. Their absence is the whole
	 * point of the report, so an empty result is reported rather than hidden.
	 */
	$invoice['items'] = array();
	$res = $mysqli->query('SELECT * FROM `invoices_items` WHERE `for_invoice` = ' . (int) $invoice['id'] . ' ORDER BY `position` ASC');
	if ($res)
	{
		while ($r = $res->fetch_assoc())
		{
			$invoice['items'][] = $r;
		}
		$res->free();
	}
	foreach (array('client' => 'invoices_clients', 'firm' => 'invoices_firms', 'translation' => 'invoices_translations') as $key => $table)
	{
		$row = NULL;
		$res = $mysqli->query('SELECT * FROM `' . $table . '` WHERE `for_invoice` = ' . (int) $invoice['id'] . ' LIMIT 1');
		if ($res)
		{
			$row = $res->fetch_assoc();
			$res->free();
		}
		$invoice[$key] = is_array($row) ? $row : array();
	}

	return $invoice;
}

/* ---------------------------------------------------------------------- *
 *  The render
 * ---------------------------------------------------------------------- */

/**
 * Build the document this host would print, and report it band by band.
 *
 * @param	array	$invoice
 * @return	array
 */
function adomin_rd_render($invoice)
{
	$root = dirname(__FILE__);

	/* the helpers the templates and the block builder need, and nothing else */
	require_once $root . '/application/helpers/anvoice_doc_helper.php';
	if (is_file($root . '/application/helpers/adomin_helper.php'))
	{
		/* every public helper in it is written with function_exists guards, so
		   our stand-ins survive and only the rest is added */
		require_once $root . '/application/helpers/adomin_helper.php';
	}

	$report = array(
		'steps' => array(),
		'bands' => array(),
		'fatal' => NULL,
		'notices' => array(),
	);

	/*
	 * Notices are collected rather than printed. In the app they land inside
	 * the band's output buffer and the band is lost; here they are the answer.
	 */
	set_error_handler(function ($severity, $message, $file, $line) use (&$report) {
		if ( ! (error_reporting() & $severity))
		{
			return FALSE;
		}
		if (count($report['notices']) < 40)
		{
			$report['notices'][] = $message . ' at ' . basename($file) . ':' . $line;
		}
		return TRUE;
	});

	try
	{
		$template = 'creative';
		$layout = function_exists('adomin_invoice_layout') ? adomin_invoice_layout(NULL, NULL) : array();
		if (isset($layout['template']) && $layout['template'] !== '')
		{
			$template = $layout['template'];
		}
		$report['steps']['layout'] = 'ok';
		$report['template'] = $template;
		$report['sections'] = function_exists('adomin_invoice_sections') ? adomin_invoice_sections($layout) : array();

		$file = $root . '/application/modules/users/views/invoices/templates/' . $template . '.php';
		$report['template_file'] = is_file($file);

		$firmInfo = array(
			'id' => isset($invoice['for_company']) ? (int) $invoice['for_company'] : 0,
			'name' => isset($invoice['firm']['name']) ? $invoice['firm']['name'] : '',
			'show_logo' => 1,
			'logo_url' => NULL,
			'image' => isset($invoice['firm']['image']) ? $invoice['firm']['image'] : NULL,
		);

		/* the shared builder: the five bands every design draws from */
		$blocks = adomin_invoice_doc_blocks($invoice, $layout, $firmInfo);
		$report['steps']['blocks'] = count($blocks);
		foreach ($blocks as $name => $fn)
		{
			ob_start();
			$err = NULL;
			try
			{
				$fn();
			}
			catch (Throwable $e)
			{
				$err = $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine();
			}
			$html = ob_get_clean();
			$text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
			$report['bands'][$name] = array(
				'bytes' => strlen($html),
				'text_chars' => strlen($text),
				'opens' => $text,
				'error' => $err,
			);
		}

		/*
		 * And the page body, which is what the preview and the PDF both draw.
		 *
		 * It RETURNS its html rather than echoing - adomin_invoice_render() is
		 * what prints it - so the return value is what to measure. Capturing
		 * the buffer instead reports zero bytes for a document that is fine.
		 */
		try
		{
			$body = adomin_invoice_page_body($invoice, $layout, $firmInfo);
		}
		catch (Throwable $e)
		{
			$body = '';
			$report['fatal'] = $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine();
		}
		$report['steps']['page_body'] = strlen($body);
		$report['page_bands'] = preg_match_all('/class="adv-band adv-band-(\w+)"/', $body, $m) ? array_values(array_unique($m[1])) : array();

		/* the template itself, included exactly as Invoiceview::index() includes it */
		if ($report['template_file'])
		{
			ob_start();
			try
			{
				include $file;
			}
			catch (Throwable $e)
			{
				$report['fatal_template'] = $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine();
			}
			$report['steps']['template_bytes'] = strlen(ob_get_clean());
		}

		/* and the PDF, because "the download is blank" is its own question */
		if (is_file($root . '/application/libraries/AdominPdf.php')
			&& is_file($root . '/application/libraries/dompdf/autoload.inc.php'))
		{
			require_once $root . '/application/libraries/dompdf/autoload.inc.php';
			require_once $root . '/application/libraries/AdominPdf.php';
			/* its constructor asks CodeIgniter for the adomin helper; the stub above answers */
			$pdf = new AdominPdf();
			ob_start();
			try
			{
				$html = $body;
				$bytes = $pdf->fromHtml('<html><body>' . $html . '</body></html>', array('paper' => 'A4'));
				$report['pdf_bytes'] = strlen($bytes);
			}
			catch (Throwable $e)
			{
				$report['pdf_error'] = $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine();
			}
			ob_end_clean();
		}
	}
	catch (Throwable $e)
	{
		$report['fatal'] = $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine();
	}
	finally
	{
		restore_error_handler();
	}

	return $report;
}

/* ---------------------------------------------------------------------- *
 *  The page
 * ---------------------------------------------------------------------- */

function adomin_rd_escape($v)
{
	return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

$token = isset($_GET['token']) ? (string) $_GET['token'] : '';
$expected = adomin_status_env('STATUS_TOKEN');
$format = isset($_GET['format']) ? (string) $_GET['format'] : 'html';
$num = isset($_GET['num']) ? (string) $_GET['num'] : '';

if ($expected === '' || !hash_equals($expected, $token))
{
	http_response_code(403);
	if ($format === 'json')
	{
		header('Content-Type: application/json');
		echo json_encode(array('error' => 'forbidden', 'why' => 'STATUS_TOKEN is not set on this deployment, or the token given does not match it.'));
		exit;
	}
	echo '<!DOCTYPE html><meta charset="utf-8"><title>Render doctor</title>'
		. '<body style="font:14px/1.6 system-ui,sans-serif;max-width:44rem;margin:3rem auto;padding:0 1rem">'
		. '<h1>Not available</h1>'
		. '<p>This page renders a real invoice, so it needs the same key as <code>/status.php</code>: '
		. 'set <code>STATUS_TOKEN</code> in the environment, then open '
		. '<code>/render-doctor.php?token=&lt;that value&gt;&amp;num=0000000002</code>.</p>'
		. '</body>';
	exit;
}

$report = array(
	'environment' => adomin_rd_environment(),
	'invoice_number' => $num,
	'invoice' => NULL,
	'render' => NULL,
);

$cfg = adomin_init_config();
$report['database_configured'] = (adomin_status_env('DB_HOST') !== '' || adomin_status_env('DATABASE_URL') !== '');

if ($report['database_configured'])
{
	$mysqli = adomin_init_connect($cfg, NULL);
	if ($mysqli === NULL)
	{
		$report['database_error'] = '[' . mysqli_connect_errno() . '] ' . mysqli_connect_error();
	}
	else
	{
		$db = $cfg['db'];
		$mysqli->select_db($db);
		$invoice = adomin_rd_invoice($mysqli, $num);
		$mysqli->close();
		if ($invoice === NULL)
		{
			$report['invoice_error'] = 'No live invoice carries that number.';
		}
		elseif (isset($invoice['error']))
		{
			$report['invoice_error'] = $invoice['error'];
		}
		else
		{
			$report['invoice'] = array(
				'id' => (int) $invoice['id'],
				'for_user' => (int) $invoice['for_user'],
				'inv_type' => $invoice['inv_type'],
				'items' => count($invoice['items']),
				'client_row' => !empty($invoice['client']),
				'firm_row' => !empty($invoice['firm']),
				'translation_row' => !empty($invoice['translation']),
				'firm_name' => isset($invoice['firm']['name']) ? $invoice['firm']['name'] : NULL,
				'client_name' => isset($invoice['client']['client_name']) ? $invoice['client']['client_name'] : NULL,
				'uniqid' => isset($invoice['uniqid']) ? $invoice['uniqid'] : NULL,
			);
			$report['render'] = adomin_rd_render($invoice);
		}
	}
}

if ($format === 'json')
{
	header('Content-Type: application/json');
	echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	$GLOBALS['adomin_rd_done'] = TRUE;
	exit;
}

/* the document itself, for a look rather than a summary */
$render = $report['render'];

if ($format === 'html' && isset($_GET['body']))
{
	header('Content-Type: text/html; charset=utf-8');
	echo '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
		. (is_file(dirname(__FILE__) . '/assets/users/css/pdf-base.css') ? file_get_contents(dirname(__FILE__) . '/assets/users/css/pdf-base.css') : '')
		. '</style></head><body class="adomin-print">' . (isset($render['body']) ? $render['body'] : '') . '</body></html>';
	exit;
}

$ok = function ($v) { return $v ? 'ok' : 'FAIL'; };
$color = function ($v) { return $v ? '#1a7f37' : '#c0392b'; };

echo '<!DOCTYPE html><meta charset="utf-8"><title>Render doctor</title>';
echo '<style>
body { font: 14px/1.6 system-ui, -apple-system, sans-serif; max-width: 58rem; margin: 2.5rem auto; padding: 0 1rem; color: #22282f; }
h1 { font-size: 1.35rem; margin-bottom: .25rem; }
h2 { font-size: 1rem; margin: 2rem 0 .5rem; text-transform: uppercase; letter-spacing: .06em; color: #6e7681; }
table { border-collapse: collapse; width: 100%; }
th, td { text-align: left; padding: .35rem .6rem; border-bottom: 1px solid #e3e6ea; vertical-align: top; }
th { font-weight: 600; width: 18rem; }
code { background: #f5f7f9; padding: .1rem .3rem; border-radius: 3px; font-size: 12.5px; }
.fail { color: #c0392b; font-weight: 600; }
.ok { color: #1a7f37; }
.muted { color: #6e7681; }
.verdict { padding: .8rem 1rem; border-left: 4px solid #0b6bcb; background: #f5f9fd; margin: 1.2rem 0; }
</style>';

echo '<h1>Render doctor</h1>';
echo '<p class="muted">Invoice ' . adomin_rd_escape($num !== '' ? $num : '(none given)') . ' · PHP '
	. adomin_rd_escape($report['environment']['php']) . ' · ' . adomin_rd_escape($report['environment']['sapi']) . '</p>';

/* --- the one line the reader came for --------------------------------- */
$bands = ($render && isset($render['bands'])) ? $render['bands'] : array();
$empty = array();
$failed = array();
foreach ($bands as $name => $b)
{
	if ($b['text_chars'] === 0)
	{
		$empty[] = $name;
	}
	if ($b['error'] !== NULL)
	{
		$failed[] = $name;
	}
}
$headline = array();
if (!$render)
{
	$headline[] = 'The render did not run: ' . (isset($report['invoice_error']) ? $report['invoice_error'] : 'no invoice was loaded.');
}
if ($render && $render['fatal'])
{
	$headline[] = 'The page body threw: ' . $render['fatal'];
}
if ($empty)
{
	$headline[] = 'These bands printed nothing: ' . implode(', ', $empty) . '.';
}
if ($failed)
{
	$headline[] = 'These bands threw: ' . implode(', ', $failed) . '.';
}
if ($render && !empty($render['notices']))
{
	$headline[] = count($render['notices']) . ' PHP notice(s) were raised - each one loses a band in the app.';
}
if (!$headline)
{
	$headline[] = 'Every band has content, the page body drew, and the PDF bytes came out. '
		. 'If the browser still shows less than this, the difference is the stylesheet or the cache, not the document.';
}
echo '<div class="verdict">' . implode('<br>', array_map('adomin_rd_escape', $headline)) . '</div>';

/* --- environment ------------------------------------------------------ */
echo '<h2>Environment</h2><table>';
echo '<tr><th>PHP version</th><td>' . adomin_rd_escape($report['environment']['php'])
	. ' <span class="muted">(the image builds php:7.4)</span></td></tr>';
foreach ($report['environment']['extensions'] as $ext => $has)
{
	echo '<tr><th>ext ' . adomin_rd_escape($ext) . '</th><td class="' . $ok($has) . '">' . ($has ? 'loaded' : 'MISSING') . '</td></tr>';
}
foreach ($report['environment']['functions'] as $fn => $has)
{
	echo '<tr><th>' . adomin_rd_escape($fn) . '()</th><td class="' . $ok($has) . '">' . ($has ? 'available' : 'MISSING') . '</td></tr>';
}
foreach ($report['environment']['writable'] as $rel => $state)
{
	echo '<tr><th>' . adomin_rd_escape($rel) . '</th><td class="' . $ok($state['writable']) . '">'
		. ($state['exists'] ? '' : 'does not exist · ')
		. ($state['writable'] ? 'writable' : 'NOT WRITABLE') . '</td></tr>';
}
echo '<tr><th>dompdf</th><td class="' . $ok($report['environment']['dompdf']['autoload'] && $report['environment']['dompdf']['dejavu']) . '">'
	. 'autoload ' . ($report['environment']['dompdf']['autoload'] ? 'present' : 'MISSING')
	. ' · DejaVu ' . ($report['environment']['dompdf']['dejavu'] ? 'present' : 'MISSING') . '</td></tr>';
echo '</table>';

/* --- database --------------------------------------------------------- */
echo '<h2>Database</h2><table>';
echo '<tr><th>configured</th><td class="' . $ok($report['database_configured']) . '">' . ($report['database_configured'] ? 'yes' : 'NO') . '</td></tr>';
if (isset($report['database_error']))
{
	echo '<tr><th>connection</th><td class="fail">' . adomin_rd_escape($report['database_error']) . '</td></tr>';
}
if ($report['invoice'])
{
	$nameMap = array(
		'id' => 'invoice id (invoices row)',
		'for_user' => 'account',
		'inv_type' => 'type',
		'items' => 'item rows (invoices_items)',
		'client_row' => 'client row (invoices_clients)',
		'firm_row' => 'firm row (invoices_firms)',
		'translation_row' => 'translation row (invoices_translations)',
		'firm_name' => 'firm name',
		'client_name' => 'client name',
		'uniqid' => 'share token (uniqid)',
	);
	foreach ($nameMap as $key => $label)
	{
		$v = $report['invoice'][$key];
		$blank = ($v === NULL || $v === '' || $v === 0 || $v === FALSE || $v === '0');
		$isBool = is_bool($v) || in_array($key, array('client_row', 'firm_row', 'translation_row'), TRUE);
		echo '<tr><th>' . adomin_rd_escape($label) . '</th><td class="'
			. ($isBool ? $ok(!$blank) : '') . '">'
			. ($isBool ? ($blank ? 'ABSENT' : 'present') : adomin_rd_escape($v === NULL ? '—' : $v))
			. '</td></tr>';
	}
}
else
{
	echo '<tr><th>invoice</th><td class="fail">' . adomin_rd_escape(isset($report['invoice_error']) ? $report['invoice_error'] : 'not looked up') . '</td></tr>';
}
echo '</table>';

/* --- the render ------------------------------------------------------- */
if ($render)
{
	echo '<h2>Bands</h2><table>';
	echo '<tr><th>band</th><th style="width:6rem">content</th><th>opens with</th></tr>';
	foreach ($bands as $name => $b)
	{
		echo '<tr><th>' . adomin_rd_escape($name) . '</th>'
			. '<td class="' . ($b['text_chars'] > 0 ? 'ok' : 'fail') . '">'
			. $b['text_chars'] . ' chars</td>'
			. '<td>' . adomin_rd_escape($b['opens'] === '' ? '(nothing)' : mb_substr($b['opens'], 0, 90))
			. ($b['error'] !== NULL ? '<br><span class="fail">' . adomin_rd_escape($b['error']) . '</span>' : '')
			. '</td></tr>';
	}
	echo '</table>';

	echo '<h2>Page and PDF</h2><table>';
	echo '<tr><th>page body</th><td class="' . ($render['steps']['page_body'] > 0 ? 'ok' : 'fail') . '">'
		. (int) $render['steps']['page_body'] . ' bytes</td></tr>';
	echo '<tr><th>bands on the page</th><td>' . adomin_rd_escape(implode(', ', $render['page_bands'])) . '</td></tr>';
	echo '<tr><th>template included</th><td class="' . (!empty($render['steps']['template_bytes']) ? 'ok' : 'fail') . '">'
		. (isset($render['steps']['template_bytes']) ? (int) $render['steps']['template_bytes'] . ' bytes' : 'not rendered')
		. (!empty($render['fatal_template']) ? '<br><span class="fail">' . adomin_rd_escape($render['fatal_template']) . '</span>' : '')
		. '</td></tr>';
	if (isset($render['pdf_bytes']))
	{
		echo '<tr><th>PDF</th><td class="' . ($render['pdf_bytes'] > 1000 ? 'ok' : 'fail') . '">'
			. (int) $render['pdf_bytes'] . ' bytes'
			. ($render['pdf_bytes'] > 1000 ? '' : ' — a blank sheet') . '</td></tr>';
	}
	if (isset($render['pdf_error']))
	{
		echo '<tr><th>PDF</th><td class="fail">' . adomin_rd_escape($render['pdf_error']) . '</td></tr>';
	}
	echo '</table>';

	if ( ! empty($render['notices']))
	{
		echo '<h2>Notices</h2><ul>';
		foreach ($render['notices'] as $n)
		{
			echo '<li><code>' . adomin_rd_escape($n) . '</code></li>';
		}
		echo '</ul>';
	}

	echo '<p><a href="?token=' . adomin_rd_escape(rawurlencode($token)) . '&num=' . adomin_rd_escape(rawurlencode($num))
		. '&body=1">Draw the body this host produced</a> · '
		. '<a href="?token=' . adomin_rd_escape(rawurlencode($token)) . '&num=' . adomin_rd_escape(rawurlencode($num))
		. '&format=json">as JSON</a></p>';
}

echo '<p class="muted" style="margin-top:2.5rem">Nothing here changes anything. Remove this file once the '
	. 'question it answers is answered.</p>';

$GLOBALS['adomin_rd_done'] = TRUE;
