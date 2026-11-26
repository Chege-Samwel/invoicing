<?php 
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2014 - 2017, British Columbia Institute of Technology
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package	CodeIgniter
 * @author	EllisLab Dev Team
 * @copyright	Copyright (c) 2008 - 2014, EllisLab, Inc. (https://ellislab.com/)
 * @copyright	Copyright (c) 2014 - 2017, British Columbia Institute of Technology (http://bcit.ca/)
 * @license	http://opensource.org/licenses/MIT	MIT License
 * @link	https://codeigniter.com
 * @since	Version 1.0.0
 * @filesource
 */

/*
 *---------------------------------------------------------------
 * APPLICATION ENVIRONMENT
 *---------------------------------------------------------------
 *
 * You can load different configurations depending on your
 * current environment. Setting the environment also influences
 * things like logging and error reporting.
 *
 * This can be set to anything, but default usage is:
 *
 *     development
 *     testing
 *     production
 *
 * NOTE: If you change these, also change the error_reporting() code below
 */

 /*
  * Hardcoded to 'production' in this repo: CI's production error page is the
  * generic one, so every boot failure looks identical from the outside. Set
  * ADOMIN_DEBUG=1 (Render > Environment) to see the real error on the page, or
  * name an environment explicitly with ADOMIN_ENVIRONMENT. Only the three CI
  * names are accepted; anything else stays production, so a stray value cannot
  * expose stack traces to customers.
  */
 $adomin_environment = 'production';
 if (filter_var(getenv('ADOMIN_DEBUG'), FILTER_VALIDATE_BOOLEAN)) {
	$adomin_environment = 'development';
 }
 $adomin_environment_forced = getenv('ADOMIN_ENVIRONMENT');
 if ($adomin_environment_forced !== FALSE && in_array($adomin_environment_forced, array('development', 'testing', 'production'), TRUE)) {
	$adomin_environment = $adomin_environment_forced;
 }
 define('ENVIRONMENT', $adomin_environment);

/*
 *---------------------------------------------------------------
 * ERROR REPORTING
 *---------------------------------------------------------------
 *
 * Different environments will require different levels of error reporting.
 * By default development will show errors but testing and live will hide them.
 */
switch (ENVIRONMENT)
{
	case 'development':
		error_reporting(-1);
		ini_set('display_errors', 1);
	break;

	case 'testing':
	case 'production':
		ini_set('display_errors', 0);
		if (version_compare(PHP_VERSION, '5.3', '>='))
		{
			error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_USER_NOTICE & ~E_USER_DEPRECATED);
		}
		else
		{
			error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_USER_NOTICE);
		}
	break;

	default:
		header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
		echo 'The application environment is not set correctly.';
		exit(1); // EXIT_ERROR
}

/*
 *---------------------------------------------------------------
 * SYSTEM DIRECTORY NAME
 *---------------------------------------------------------------
 *
 * This variable must contain the name of your "system" directory.
 * Set the path if it is not in the same directory as this file.
 */
	$system_path = 'system';

/*
 *---------------------------------------------------------------
 * APPLICATION DIRECTORY NAME
 *---------------------------------------------------------------
 *
 * If you want this front controller to use a different "application"
 * directory than the default one you can set its name here. The directory
 * can also be renamed or relocated anywhere on your server. If you do,
 * use an absolute (full) server path.
 * For more info please see the user guide:
 *
 * https://codeigniter.com/user_guide/general/managing_apps.html
 *
 * NO TRAILING SLASH!
 */
	$application_folder = 'application';

/*
 *---------------------------------------------------------------
 * VIEW DIRECTORY NAME
 *---------------------------------------------------------------
 *
 * If you want to move the view directory out of the application
 * directory, set the path to it here. The directory can be renamed
 * and relocated anywhere on your server. If blank, it will default
 * to the standard location inside your application directory.
 * If you do move this, use an absolute (full) server path.
 *
 * NO TRAILING SLASH!
 */
	$view_folder = '';


/*
 * --------------------------------------------------------------------
 * DEFAULT CONTROLLER
 * --------------------------------------------------------------------
 *
 * Normally you will set your default controller in the routes.php file.
 * You can, however, force a custom routing by hard-coding a
 * specific controller class/function here. For most applications, you
 * WILL NOT set your routing here, but it's an option for those
 * special instances where you might want to override the standard
 * routing in a specific front controller that shares a common CI installation.
 *
 * IMPORTANT: If you set the routing here, NO OTHER controller will be
 * callable. In essence, this preference limits your application to ONE
 * specific controller. Leave the function name blank if you need
 * to call functions dynamically via the URI.
 *
 * Un-comment the $routing array below to use this feature
 */
	// The directory name, relative to the "controllers" directory.  Leave blank
	// if your controller is not in a sub-directory within the "controllers" one
	// $routing['directory'] = '';

	// The controller class file name.  Example:  mycontroller
	// $routing['controller'] = '';

	// The controller function you wish to be called.
	// $routing['function']	= '';


/*
 * -------------------------------------------------------------------
 *  CUSTOM CONFIG VALUES
 * -------------------------------------------------------------------
 *
 * The $assign_to_config array below will be passed dynamically to the
 * config class when initialized. This allows you to set custom config
 * items or override any default config values found in the config.php file.
 * This can be handy as it permits you to share one application between
 * multiple front controller files, with each file containing different
 * config values.
 *
 * Un-comment the $assign_to_config array below to use this feature
 */
	// $assign_to_config['name_of_config_item'] = 'value of config item';



// --------------------------------------------------------------------
// END OF USER CONFIGURABLE SETTINGS.  DO NOT EDIT BELOW THIS LINE
// --------------------------------------------------------------------

/*
 * ---------------------------------------------------------------
 *  Resolve the system path for increased reliability
 * ---------------------------------------------------------------
 */

	// Set the current directory correctly for CLI requests
	if (defined('STDIN'))
	{
		chdir(dirname(__FILE__));
	}

	if (($_temp = realpath($system_path)) !== FALSE)
	{
		$system_path = $_temp.DIRECTORY_SEPARATOR;
	}
	else
	{
		// Ensure there's a trailing slash
		$system_path = strtr(
			rtrim($system_path, '/\\'),
			'/\\',
			DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR
		).DIRECTORY_SEPARATOR;
	}

	// Is the system path correct?
	if ( ! is_dir($system_path))
	{
		header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
		echo 'Your system folder path does not appear to be set correctly. Please open the following file and correct this: '.pathinfo(__FILE__, PATHINFO_BASENAME);
		exit(3); // EXIT_CONFIG
	}

/*
 * -------------------------------------------------------------------
 *  Now that we know the path, set the main path constants
 * -------------------------------------------------------------------
 */
	// The name of THIS file
	define('SELF', pathinfo(__FILE__, PATHINFO_BASENAME));

	// Path to the system directory
	define('BASEPATH', $system_path);

	// Path to the front controller (this file) directory
	define('FCPATH', dirname(__FILE__).DIRECTORY_SEPARATOR);

	// Name of the "system" directory
	define('SYSDIR', basename(BASEPATH));

	// The path to the "application" directory
	if (is_dir($application_folder))
	{
		if (($_temp = realpath($application_folder)) !== FALSE)
		{
			$application_folder = $_temp;
		}
		else
		{
			$application_folder = strtr(
				rtrim($application_folder, '/\\'),
				'/\\',
				DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR
			);
		}
	}
	elseif (is_dir(BASEPATH.$application_folder.DIRECTORY_SEPARATOR))
	{
		$application_folder = BASEPATH.strtr(
			trim($application_folder, '/\\'),
			'/\\',
			DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR
		);
	}
	else
	{
		header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
		echo 'Your application folder path does not appear to be set correctly. Please open the following file and correct this: '.SELF;
		exit(3); // EXIT_CONFIG
	}

	define('APPPATH', $application_folder.DIRECTORY_SEPARATOR);

	// The path to the "views" directory
	if ( ! isset($view_folder[0]) && is_dir(APPPATH.'views'.DIRECTORY_SEPARATOR))
	{
		$view_folder = APPPATH.'views';
	}
	elseif (is_dir($view_folder))
	{
		if (($_temp = realpath($view_folder)) !== FALSE)
		{
			$view_folder = $_temp;
		}
		else
		{
			$view_folder = strtr(
				rtrim($view_folder, '/\\'),
				'/\\',
				DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR
			);
		}
	}
	elseif (is_dir(APPPATH.$view_folder.DIRECTORY_SEPARATOR))
	{
		$view_folder = APPPATH.strtr(
			trim($view_folder, '/\\'),
			'/\\',
			DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR
		);
	}
	else
	{
		header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
		echo 'Your view folder path does not appear to be set correctly. Please open the following file and correct this: '.SELF;
		exit(3); // EXIT_CONFIG
	}

	define('VIEWPATH', $view_folder.DIRECTORY_SEPARATOR);

/*
 * --------------------------------------------------------------------
 * DATABASE: which hosts refuse to talk without TLS
 * --------------------------------------------------------------------
 *
 * Defined here, before the framework loads, because application/config/
 * database.php needs the same answer and /status must not report a connection
 * plan that differs from the one the app will use. Only providers that refuse an
 * unencrypted connection outright belong on this list - adding a host that
 * happens to speak plain MySQL would break installs instead of fixing them.
 */
if ( ! function_exists('adomin_db_tls_required_host')) {
	function adomin_db_tls_required_host($host)
	{
		$host = strtolower(trim((string) $host));
		if ($host === '') {
			return FALSE;
		}
		// a hostname may arrive with a port, or as an IPv6 literal in brackets;
		// neither should change which zone it belongs to
		if (substr($host, 0, 1) === '[') {
			$close = strpos($host, ']');
			if ($close !== FALSE) {
				$host = substr($host, 1, $close - 1);
			}
		} elseif (substr_count($host, ':') === 1) {
			$host = substr($host, 0, strpos($host, ':'));
		}
		$zones = array(
			'tidbcloud.com',      // TiDB Cloud - "insecure transport are prohibited"
			'planetscale.com',    // PlanetScale
			'aivencloud.com',     // Aiven for MySQL / PgBouncer-style gateways
			'mysql.database.azure.com',
			'mysql.database.azure.cn',
		);
		foreach ($zones as $zone) {
			// boundary-aware: `nottidbcloud.com` is somebody else's domain and must
			// not inherit another provider's TLS requirement
			if ($host === $zone || substr($host, -(strlen($zone) + 1)) === '.' . $zone) {
				return TRUE;
			}
		}
		return FALSE;
	}
}

/*
 * --------------------------------------------------------------------
 * GET /status - infrastructure self-check
 * --------------------------------------------------------------------
 *
 * This runs before CodeIgniter boots, and it has to: application/config/
 * autoload.php loads the 'database' library on every request, so when the
 * database is unreachable (no DB_* variables, schema not imported yet, TLS
 * refused, a PostgreSQL URL) every URL - including any controller action -
 * dies as CodeIgniter's generic production error page, and a free-tier
 * container has no shell to read application/logs from.
 *
 * So /status answers with facts instead: extensions, which env vars are set,
 * whether a socket to the DB opens, whether the schema has been imported, and
 * what to do next. It never prints a password. Disable with ADOMIN_STATUS=0.
 *
 * render.yaml points Render's healthCheckPath here deliberately: the service can
 * be up and diagnosable while the database still needs setting up.
 */
if ( ! function_exists('adomin_status_payload')) {

	if ( ! function_exists('adomin_status_env')) {
		function adomin_status_env($key, $default = '')
		{
			foreach (array(getenv($key), isset($_SERVER[$key]) ? $_SERVER[$key] : NULL, isset($_ENV[$key]) ? $_ENV[$key] : NULL) as $value) {
				if ($value !== FALSE && $value !== NULL && $value !== '') {
					return $value;
				}
			}
			return $default;
		}
	}

	if ( ! function_exists('adomin_status_params')) {
		/**
		 * Query parameters of the incoming request. The .htaccess rewrite sends the
		 * path as index.php?/status and drops the original query string, so read the
		 * REQUEST_URI itself - otherwise ?refresh=1 would never arrive.
		 */
		function adomin_status_params()
		{
			$params = array();
			$uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
			if ($uri !== '') {
				$query = parse_url($uri, PHP_URL_QUERY);
				if (is_string($query) && $query !== '') {
					parse_str($query, $params);
				}
			}
			return array_merge($params, $_GET);
		}
	}

	if ( ! function_exists('adomin_status_requested')) {
		function adomin_status_requested()
		{
			if (filter_var(adomin_status_env('ADOMIN_STATUS', '1'), FILTER_VALIDATE_BOOLEAN) === FALSE) {
				return FALSE;
			}
			$uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
			if ($uri === '') {
				return FALSE;
			}
			$path = parse_url($uri, PHP_URL_PATH);
			if ( ! is_string($path)) {
				return FALSE;
			}
			// tolerate ';param' style path info so the endpoint is reachable under
			// any accept-path-info configuration, but never for a page that merely
			// ends in the word "status"
			if (($semi = strpos($path, ';')) !== FALSE) {
				$path = substr($path, 0, $semi);
			}
			$path = rtrim($path, '/');
			// /status or /index.php/status (also under a sub-folder install), but
			// never an app page that merely ends in the word "status"
			return (bool) preg_match('#(?:^|/)status$#', $path) && !preg_match('#/(?:user|admin)/#', $path);
		}
	}

	function adomin_status_payload()
	{
		$root = __DIR__ . DIRECTORY_SEPARATOR;
		$host = adomin_status_env('DB_HOST');
		$user = adomin_status_env('DB_USER');
		$pass = adomin_status_env('DB_PASS');
		$name = adomin_status_env('DB_NAME');
		$port = (int) (adomin_status_env('DB_PORT', '3306') ?: 3306);
		$url = adomin_status_env('DATABASE_URL', adomin_status_env('MYSQL_URL', adomin_status_env('DB_URL', adomin_status_env('CLEARDB_DATABASE_URL'))));

		// a pasted connection URL fills whatever the individual variables left
		// unset, which is what application/config/database.php does too
		$scheme = '';
		if ($url !== '') {
			$scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
			if (strpos($scheme, 'mysql') === 0) {
				if ($host === '') { $host = (string) parse_url($url, PHP_URL_HOST); }
				if (parse_url($url, PHP_URL_PORT)) { $port = (int) parse_url($url, PHP_URL_PORT); }
				if ($user === '' && parse_url($url, PHP_URL_USER) !== NULL) { $user = rawurldecode((string) parse_url($url, PHP_URL_USER)); }
				if ($pass === '' && parse_url($url, PHP_URL_PASS) !== NULL) { $pass = rawurldecode((string) parse_url($url, PHP_URL_PASS)); }
				if ($name === '' && parse_url($url, PHP_URL_PATH)) { $name = ltrim((string) parse_url($url, PHP_URL_PATH), '/'); }
			}
		}

		$required = array('mysqli', 'dom', 'libxml', 'mbstring', 'json', 'iconv', 'zlib');
		$optional = array('gd', 'zip', 'fileinfo', 'openssl');
		$missing = array();
		$optionalMissing = array();
		foreach ($required as $ext) {
			if ( ! extension_loaded($ext)) { $missing[] = $ext; }
		}
		foreach ($optional as $ext) {
			if ( ! extension_loaded($ext)) { $optionalMissing[] = $ext; }
		}

		// same precedence as application/config/database.php, so /status can never
		// report a plan the app will not use: DB_SSL, then what the URL said, then
		// hosts that refuse an unencrypted connection at all
		$tls = 'off';
		$tls_wanted = FALSE;
		$db_ssl_flag = trim((string) adomin_status_env('DB_SSL'));
		if ($url !== '' && $host !== '') {
			$url_mode = '';
			$url_query = parse_url($url, PHP_URL_QUERY);
			if (is_string($url_query) && $url_query !== '') {
				parse_str($url_query, $url_qs);
				foreach (array('ssl-mode', 'sslmode', 'ssl_mode', 'tls') as $qs_key) {
					if (isset($url_qs[$qs_key]) && $url_qs[$qs_key] !== '') {
						$url_mode = strtolower($url_qs[$qs_key]);
					}
				}
			}
			$url_off = ($url_mode !== '' && (strpos($url_mode, 'disabled') !== FALSE || $url_mode === 'false' || $url_mode === 'skip-verify'));
			$url_on = ($url_mode !== '' && ! $url_off);
		} else {
			$url_off = $url_on = FALSE;
		}
		if ($db_ssl_flag !== '') {
			$tls_wanted = filter_var($db_ssl_flag, FILTER_VALIDATE_BOOLEAN);
			$tls = $tls_wanted ? 'on (DB_SSL)' : 'off (DB_SSL=0)';
		} elseif ($url_on) {
			$tls_wanted = TRUE;
			$tls = 'on (' . $url_mode . ')';
		} elseif ( ! empty($url_off)) {
			$tls = 'off (url ssl-mode)';
		} elseif (adomin_db_tls_required_host($host)) {
			$tls_wanted = TRUE;
			$tls = 'on (required by ' . $host . ')';
		}
		$tls_verify = filter_var(adomin_status_env('DB_SSL_VERIFY', '0'), FILTER_VALIDATE_BOOLEAN);

		$appTables = array('invoices', 'currencies', 'firms_users', 'invoice_share_views');
		$db = array('configured' => ($host !== '' && $name !== ''), 'host' => $host, 'port' => $port, 'database' => $name, 'connected' => FALSE, 'error' => '', 'tls' => $tls, 'tables' => -1);
		$state = array();
		if ($db['configured'] && extension_loaded('mysqli') && function_exists('mysqli_init')) {
			mysqli_report(MYSQLI_REPORT_OFF);
			$link = @mysqli_init();
			if ($link) {
				$link->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
				if ($tls_wanted) {
					if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
						@$link->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, $tls_verify ? 1 : 0);
					}
					if (defined('MYSQLI_CLIENT_SSL')) {
						$ca = adomin_status_env('DB_SSL_CA', '/etc/ssl/certs/ca-certificates.crt');
						@mysqli_ssl_set($link, NULL, NULL, is_file($ca) ? $ca : NULL, NULL, NULL);
					}
				}
				$started = microtime(TRUE);
				$connected = @mysqli_real_connect($link, $host, $user, $pass, $name, $port);
				$db['connect_ms'] = (int) round((microtime(TRUE) - $started) * 1000);
				if ($connected) {
					$db['connected'] = TRUE;
					$res = @mysqli_query($link, 'SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
					if ($res) {
						$row = mysqli_fetch_assoc($res);
						$db['tables'] = isset($row['c']) ? (int) $row['c'] : 0;
					}
					$found = array();
					$res = @mysqli_query($link, 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
					if ($res) {
						while ($row = mysqli_fetch_assoc($res)) {
							$found[reset($row)] = TRUE;
						}
					}
					foreach ($appTables as $t) {
						$state[$t] = isset($found[$t]);
					}
					$res = @mysqli_query($link, "SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'firms_users' AND COLUMN_NAME = 'logo_url'");
					if ($res) {
						$row = mysqli_fetch_assoc($res);
						$state['firms_users.logo_url'] = isset($row['c']) ? (int) $row['c'] > 0 : FALSE;
					}
					$res = @mysqli_query($link, "SELECT COUNT(*) AS c FROM currencies WHERE value = 'KES'");
					if ($res) {
						$row = mysqli_fetch_assoc($res);
						$state['currency KES'] = isset($row['c']) ? (int) $row['c'] > 0 : FALSE;
					}
				} else {
					$db['error'] = mysqli_connect_error() ? mysqli_connect_error() : 'connect failed';
				}
				@mysqli_close($link);
			}
		} else {
			$db['error'] = $host === '' ? 'not configured' : 'mysqli unavailable';
		}

		$hints = array();
		if ($url !== '' && strpos($scheme, 'mysql') !== 0) {
			$hints[] = 'The connection URL uses the ' . $scheme . ':// scheme. This app is MySQL-only (PHP mysqli); a PostgreSQL link (Neon, Supabase, Render Postgres) cannot be used - see BRIEF.md 5.2.';
		}
		if ($host === '') {
			$hints[] = 'DB_HOST is empty. Set DB_HOST/DB_USER/DB_PASS/DB_NAME (or one DATABASE_URL) under Render > your service > Environment, then reboot. Without them the app falls back to localhost, which does not exist on Render.';
		}
		if (in_array(strtolower($name), array('sys', 'mysql', 'information_schema', 'performance_schema'), TRUE)) {
			$hints[] = 'DB_NAME is `' . $name . '`, a server-owned schema the app must not use. Point it at your own schema - TiDB Cloud Starter creates `defaultdb`, and its `sys` schema is empty read-only metadata, so invoices would 500 even with a good connection. Import database.sql into that schema.';
		}
		if ( ! $tls_wanted && $host !== '' && ($db['error'] !== '' && (stripos($db['error'], 'insecure transport') !== FALSE || stripos($db['error'], 'ssl') !== FALSE))) {
			$hints[] = 'The server refuses unencrypted connections but TLS is off here: ' . $tls . '. Add ?ssl-mode=REQUIRED to DATABASE_URL or set DB_SSL=1.';
		}
		if ($db['configured'] && ! $db['connected']) {
			$hints[] = 'The database refused the connection: ' . $db['error'] . ' . Check that the host allows this app (TiDB/Aiven require TLS - add ?ssl-mode=REQUIRED to the URL or set DB_SSL=1), that the TiDB username is user@cluster, and that the database name is correct.';
		}
		if ($db['connected'] && $db['tables'] < 40) {
			$hints[] = 'Connected, but the schema looks empty (' . $db['tables'] . ' tables). Import database.sql with that database selected - it contains no USE statement - then database/adomin-updates.sql.';
		}
		if ($db['connected'] && ! empty($state['invoices']) && empty($state['firms_users.logo_url'])) {
			$hints[] = 'database.sql is in, but database/adomin-updates.sql is not: run it for the KES currency, firms_users.logo_url, the share-link index and the view log.';
		}
		if ($missing) {
			$hints[] = 'Missing PHP extensions: ' . implode(', ', $missing) . '. Rebuild the image; mysqli is compiled in the Dockerfile and the rest ship with php:7.4-apache.';
		}

		$healthy = ( ! $missing && $db['configured'] && $db['connected'] && $db['tables'] >= 40);
		if ( ! $healthy && ! defined('ADOMIN_DEBUG_ON') && ENVIRONMENT === 'production') {
			$hints[] = "Every page is currently CodeIgniter's generic production error page, which is why this endpoint exists. Set ADOMIN_DEBUG=1 under Render > Environment to get the real error text on the page itself, and remove it afterwards.";
		}
		return array(
			'service' => 'adomin-invoices',
			'time_utc' => gmdate('c'),
			'probed_at' => gmdate('c'),
			'state' => $healthy ? 'ok' : ($db['connected'] ? 'degraded' : 'broken'),
			'environment' => defined('ENVIRONMENT') ? ENVIRONMENT : 'unknown',
			'php' => array('version' => PHP_VERSION, 'memory_limit' => (string) ini_get('memory_limit'), 'max_execution_time' => (string) ini_get('max_execution_time'), 'date_timezone' => (string) ini_get('date.timezone'), 'effective_timezone' => date_default_timezone_get()),
			'extensions' => array('required_missing' => $missing, 'optional_missing' => $optionalMissing, 'loaded' => array_values(array_intersect(array_merge($required, $optional), get_loaded_extensions()))),
			'database' => $db,
			'tables' => $state,
			'config' => array(
				'adomin_config_present' => is_file($root.'application/config/adomin.php'),
				// BASE_URL is defined by application/config/constants.php, which
				// CodeIgniter loads after this block, so derive it the same way
				'scheme' => (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]) === 'https') ? 'https' : (( ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'),
				'host_header' => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '',
				'base_url_after_bootstrap' => defined('BASE_URL') ? rtrim(BASE_URL, '/') : '(not yet defined)',
			),
			'writable' => array(
				'application/logs' => is_writable($root.'application/logs'),
				'application/cache' => is_writable($root.'application/cache'),
				'attachments' => is_writable($root.'attachments'),
				'dompdf_font_dir' => is_writable($root.'application/libraries/dompdf/lib/fonts'),
			),
			'hints' => $hints,
			'notes' => 'Credentials are never printed here. Set ADOMIN_STATUS=0 to switch this endpoint off.',
		);
	}
}

if (adomin_status_requested()) {
	/*
	 * Render polls healthCheckPath every few seconds. Each poll must not open a
	 * database connection: on 0.1 CPU with four Apache workers, a 5-second connect
	 * timeout repeating every 5 seconds is enough to sit on the whole instance. So
	 * the payload is cached for 30 seconds and the health probe itself is answered
	 * from that cache (or with a bare 200) instead of by probing.
	 */
	$adomin_cache_dir = dirname(__FILE__).'/application/cache';
	if ( ! is_dir($adomin_cache_dir) || ! is_writable($adomin_cache_dir)) {
		$adomin_cache_dir = sys_get_temp_dir();
	}
	$adomin_cache_file = rtrim($adomin_cache_dir, '/').'/adomin-status.json';
	$adomin_ttl = 30;
	$adomin_params = adomin_status_params();
	$adomin_force = isset($adomin_params['refresh']) && $adomin_params['refresh'] !== '0';
	$adomin_health = isset($_SERVER['HTTP_USER_AGENT']) && (stripos($_SERVER['HTTP_USER_AGENT'], 'render/') !== FALSE || stripos($_SERVER['HTTP_USER_AGENT'], 'health') !== FALSE);
	$adomin_cached = FALSE;
	if ( ! $adomin_force && is_file($adomin_cache_file) && (time() - (int) @filemtime($adomin_cache_file)) < $adomin_ttl) {
		$adomin_raw = @file_get_contents($adomin_cache_file);
		$adomin_cached = $adomin_raw ? json_decode($adomin_raw, TRUE) : FALSE;
	}

	if (is_array($adomin_cached) && isset($adomin_cached['service'], $adomin_cached['state'])) {
		// that pair is the shape check: a truncated or hand-edited cache file must not
		// be served as if it were a probe result - fall through and probe again
		$adomin_status = $adomin_cached;
		$adomin_status['cached'] = TRUE;
		$adomin_status['cache_age_seconds'] = time() - (int) @filemtime($adomin_cache_file);
	} elseif ($adomin_health) {
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store');
		header('X-Adomin-State: up');
		echo json_encode(array('service' => 'adomin-invoices', 'state' => 'up', 'note' => 'health probe answered without probing the database; open /status for the full self-check', 'time_utc' => gmdate('c')));
		exit;
	} else {
		// A notice or warning printed by ini_set/mysqli during the probe would land
		// inside the JSON body and break every parser reading it - and with
		// ADOMIN_DEBUG=1 display_errors is on. Capture and discard stray output.
		ob_start();
		$adomin_status = adomin_status_payload();
		$adomin_stray = ob_get_contents();
		ob_end_clean();
		if ($adomin_stray !== '' && $adomin_stray !== FALSE) {
			$adomin_status['stray_output'] = trim($adomin_stray);
		}
		$adomin_status['cached'] = FALSE;
		$adomin_status['cache_ttl_seconds'] = $adomin_ttl;
		@file_put_contents($adomin_cache_file, json_encode($adomin_status), LOCK_EX);
	}

	header('Cache-Control: no-store');
	header('X-Adomin-State: ' . (isset($adomin_status['state']) ? $adomin_status['state'] : 'unknown'));
	$asHtml = (isset($adomin_params['text']) && $adomin_params['text'] === 'html')
		|| (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/html') !== FALSE);
	if ($asHtml) {
		header('Content-Type: text/html; charset=utf-8');
		echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Adomin status</title></head>'
			. '<body style="font:13px/1.6 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;margin:2rem;background:#0f1720;color:#e6edf3">'
			. '<h2 style="margin:0 0 .5rem;font-family:system-ui,sans-serif">Adomin Invoices &mdash; ' . htmlspecialchars(isset($adomin_status['state']) ? $adomin_status['state'] : 'unknown', ENT_QUOTES, 'UTF-8') . '</h2>'
			. '<pre style="white-space:pre-wrap;word-break:break-word">' . htmlspecialchars(json_encode($adomin_status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') . '</pre>'
			. '</body></html>';
	} else {
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($adomin_status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	}
	exit;
}

/*
 * --------------------------------------------------------------------
 * LOAD THE BOOTSTRAP FILE
 * --------------------------------------------------------------------
 *
 * And away we go...
 */
require_once BASEPATH.'core/CodeIgniter.php';
