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

	if ( ! function_exists('adomin_db_target')) {
		/**
		 * The connection the app will actually use, resolved from the environment,
		 * with the same rules as application/config/database.php: individual DB_*
		 * values win, a pasted URL fills the gaps, and a host that refuses plaintext
		 * decides TLS unless DB_SSL or the URL's ssl-mode already said. /status, /setup
		 * and the app have to agree, so this is the only place that reads them.
		 * The password lives in the returned array and is never printed by a caller.
		 */
		function adomin_db_target()
		{
			$target = array(
				'host' => '', 'user' => '', 'pass' => '', 'name' => '', 'port' => 3306,
				'url' => '', 'scheme' => '', 'tls' => 'off', 'tls_wanted' => FALSE,
				'tls_verify' => FALSE, 'configured' => FALSE, 'reserved' => FALSE,
			);
			$target['host'] = trim((string) adomin_status_env('DB_HOST'));
			$target['user'] = trim((string) adomin_status_env('DB_USER'));
			$target['pass'] = (string) adomin_status_env('DB_PASS');
			$target['name'] = trim((string) adomin_status_env('DB_NAME'));
			$target['port'] = (int) (adomin_status_env('DB_PORT', '3306') ?: 3306);
			$target['url'] = trim((string) adomin_status_env('DATABASE_URL', adomin_status_env('MYSQL_URL', adomin_status_env('DB_URL', adomin_status_env('CLEARDB_DATABASE_URL')))));
			$url = $target['url'];

			if ($url !== '') {
				$target['scheme'] = strtolower((string) parse_url($url, PHP_URL_SCHEME));
				if (strpos($target['scheme'], 'mysql') === 0) {
					if ($target['host'] === '') { $target['host'] = (string) parse_url($url, PHP_URL_HOST); }
					if (parse_url($url, PHP_URL_PORT)) { $target['port'] = (int) parse_url($url, PHP_URL_PORT); }
					if ($target['user'] === '' && parse_url($url, PHP_URL_USER) !== NULL) { $target['user'] = rawurldecode((string) parse_url($url, PHP_URL_USER)); }
					if ($target['pass'] === '' && parse_url($url, PHP_URL_PASS) !== NULL) { $target['pass'] = rawurldecode((string) parse_url($url, PHP_URL_PASS)); }
					if ($target['name'] === '' && parse_url($url, PHP_URL_PATH)) { $target['name'] = ltrim((string) parse_url($url, PHP_URL_PATH), '/'); }
				}
			}

			// TLS precedence: DB_SSL, then the URL's ssl-mode, then the host's zone
			$db_ssl_flag = trim((string) adomin_status_env('DB_SSL'));
			$url_mode = '';
			$url_on = $url_off = FALSE;
			if ($url !== '') {
				$url_query = parse_url($url, PHP_URL_QUERY);
				if (is_string($url_query) && $url_query !== '') {
					parse_str($url_query, $url_qs);
					foreach (array('ssl-mode', 'sslmode', 'ssl_mode', 'tls') as $qs_key) {
						if (isset($url_qs[$qs_key]) && $url_qs[$qs_key] !== '') {
							$url_mode = strtolower((string) $url_qs[$qs_key]);
						}
					}
				}
				$url_off = ($url_mode !== '' && (strpos($url_mode, 'disabled') !== FALSE || $url_mode === 'false' || $url_mode === 'skip-verify'));
				$url_on = ($url_mode !== '' && ! $url_off);
			}
			if ($db_ssl_flag !== '') {
				$target['tls_wanted'] = filter_var($db_ssl_flag, FILTER_VALIDATE_BOOLEAN);
				$target['tls'] = $target['tls_wanted'] ? 'on (DB_SSL)' : 'off (DB_SSL=0)';
			} elseif ($url_on) {
				$target['tls_wanted'] = TRUE;
				$target['tls'] = 'on (' . $url_mode . ')';
			} elseif ($url_off) {
				$target['tls'] = 'off (url ssl-mode)';
			} elseif (adomin_db_tls_required_host($target['host'])) {
				$target['tls_wanted'] = TRUE;
				$target['tls'] = 'on (required by ' . $target['host'] . ')';
			}
			$target['tls_verify'] = filter_var(adomin_status_env('DB_SSL_VERIFY', '0'), FILTER_VALIDATE_BOOLEAN);
			$target['configured'] = ($target['host'] !== '' && $target['name'] !== '');
			$target['reserved'] = in_array(strtolower($target['name']), array('sys', 'mysql', 'information_schema', 'performance_schema'), TRUE);
			return $target;
		}
	}

	if ( ! function_exists('adomin_db_open')) {
		/**
		 * mysqli link for a resolved target. Returns link|FALSE plus how long the
		 * connect took and the driver's own error text - the two facts that turn a
		 * silent 500 into an explanation.
		 */
		function adomin_db_open($target, $timeout_seconds = 5)
		{
			$out = array('link' => FALSE, 'ms' => 0, 'error' => '');
			if ( ! extension_loaded('mysqli') || ! function_exists('mysqli_init')) {
				$out['error'] = 'the mysqli extension is not loaded';
				return $out;
			}
			mysqli_report(MYSQLI_REPORT_OFF);
			$link = @mysqli_init();
			if ( ! $link) {
				$out['error'] = 'mysqli_init() failed';
				return $out;
			}
			$link->options(MYSQLI_OPT_CONNECT_TIMEOUT, (int) $timeout_seconds);
			if ( ! empty($target['tls_wanted'])) {
				if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
					@$link->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, ! empty($target['tls_verify']) ? 1 : 0);
				}
				if (defined('MYSQLI_CLIENT_SSL')) {
					$ca = adomin_status_env('DB_SSL_CA', '/etc/ssl/certs/ca-certificates.crt');
					@mysqli_ssl_set($link, NULL, NULL, is_file($ca) ? $ca : NULL, NULL, NULL);
				}
			}
			$started = microtime(TRUE);
			$connected = @mysqli_real_connect($link, $target['host'], $target['user'], $target['pass'], $target['name'], $target['port']);
			$out['ms'] = (int) round((microtime(TRUE) - $started) * 1000);
			if ($connected) {
				$out['link'] = $link;
			} else {
				$out['error'] = mysqli_connect_error() ? mysqli_connect_error() : 'connect failed';
				@mysqli_close($link);
			}
			return $out;
		}
	}

	if ( ! function_exists('adomin_status_hints')) {
		/**
		 * The "what to do next" list, from the facts the probe collected. A separate
		 * function because these rules are the part a reader actually acts on, and
		 * being callable with a synthetic array means they can be tested without a
		 * database - see BRIEF.md 11.1.
		 */
		function adomin_status_hints($ctx)
		{
			$db = $ctx['db'];
			$host = $ctx['host'];
			$name = $ctx['name'];
			$hints = array();

			if ($ctx['url'] !== '' && strpos($ctx['scheme'], 'mysql') !== 0) {
				$hints[] = 'The connection URL uses the ' . $ctx['scheme'] . ':// scheme. This app is MySQL-only (PHP mysqli); a PostgreSQL link (Neon, Supabase, Render Postgres) cannot be used - see BRIEF.md 5.2.';
			}
			if ($host === '') {
				$hints[] = 'DB_HOST is empty. Set DB_HOST/DB_USER/DB_PASS/DB_NAME (or one DATABASE_URL) under Render > your service > Environment, then reboot. Without them the app falls back to localhost, which does not exist on Render.';
			}
			if (in_array(strtolower($name), array('sys', 'mysql', 'information_schema', 'performance_schema'), TRUE)) {
				$hints[] = 'DB_NAME is `' . $name . '`, a schema the server owns: the app cannot create its tables there, so every page would fail even with a good connection. Create your own instead - in the TiDB SQL editor run CREATE DATABASE `adomin` and use that name - then import database.sql into it. `defaultdb` exists only on some clusters; do not assume it.';
			}
			if ( ! $ctx['tls_wanted'] && $host !== '' && $db['error'] !== '' && (stripos($db['error'], 'insecure transport') !== FALSE || stripos($db['error'], 'ssl') !== FALSE)) {
				$hints[] = 'The server refuses unencrypted connections but TLS is off here: ' . $ctx['tls'] . '. Add ?ssl-mode=REQUIRED to DATABASE_URL or set DB_SSL=1.';
			}
			if ($db['configured'] && ! $db['connected'] && $name !== '' && stripos($db['error'], 'unknown database') !== FALSE) {
				$hints[] = 'There is no schema called `' . $name . '` on that server (MySQL: Unknown database). Create it first - CREATE DATABASE `' . $name . '` - then import database.sql and database/adomin-updates.sql into it, then reload this page. TiDB Cloud does not create an application schema for you; the console also has a Create Database button.';
			}
			elseif ($db['configured'] && ! $db['connected']) {
				$hints[] = 'The database refused the connection: ' . $db['error'] . ' . Check that the host allows this app (TiDB/Aiven require TLS - add ?ssl-mode=REQUIRED to the URL or set DB_SSL=1), that the TiDB username is user@cluster, and that the database name is correct.';
			}
			if ($db['connected'] && $db['tables'] < 40) {
				$hints[] = 'Connected, but the schema looks empty (' . $db['tables'] . ' tables). Import database.sql with that database selected - it contains no USE statement - then database/adomin-updates.sql.';
			}
			if ($db['connected'] && ! empty($ctx['state']['invoices']) && empty($ctx['state']['firms_users.logo_url'])) {
				$hints[] = 'database.sql is in, but database/adomin-updates.sql is not: run it for the KES currency, firms_users.logo_url, the share-link index and the view log.';
			}
			if ($ctx['missing']) {
				$hints[] = 'Missing PHP extensions: ' . implode(', ', $ctx['missing']) . '. Rebuild the image; mysqli is compiled in the Dockerfile and the rest ship with php:7.4-apache.';
			}

			return $hints;
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
		$target = adomin_db_target();
		$host = $target['host'];
		$user = $target['user'];
		$name = $target['name'];
		$url = $target['url'];
		$scheme = $target['scheme'];

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

		$appTables = array('invoices', 'currencies', 'firms_users', 'invoice_share_views');
		// the user name is reported on purpose - TiDB and PlanetScale require the
		// `user@cluster` form, so "which user did it actually try" is the fact that
		// makes that hint checkable. The password is not part of $db and never is.
		$db = array('configured' => $target['configured'], 'host' => $host, 'port' => $target['port'], 'database' => $name, 'username' => $user, 'connected' => FALSE, 'error' => '', 'tls' => $target['tls'], 'tables' => -1);
		$state = array();
		if ($db['configured']) {
			$opened = adomin_db_open($target);
			$db['connect_ms'] = $opened['ms'];
			if ($opened['link']) {
				$link = $opened['link'];
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
				foreach ($appTables as $app_table) {
					$state[$app_table] = isset($found[$app_table]);
				}
				$state['invoices_exists'] = isset($found['invoices']);
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
				@mysqli_close($link);
			} else {
				$db['error'] = $opened['error'];
			}
		} else {
			$db['error'] = $host === '' ? 'not configured' : 'no database name (DB_NAME)';
		}

		$healthy = ( ! $missing && $db['configured'] && $db['connected'] && $db['tables'] >= 40);
		$hints = adomin_status_hints(array(
			'url' => $url, 'scheme' => $scheme, 'host' => $host, 'name' => $name,
			'db' => $db, 'state' => $state, 'missing' => $missing,
			'tls' => $target['tls'], 'tls_wanted' => $target['tls_wanted'], 'healthy' => $healthy,
		));
		if ( ! $healthy && ENVIRONMENT === 'production') {
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
			'notes' => 'No password is printed here (the DB user name is, because TiDB and PlanetScale require the user@cluster form). Set ADOMIN_STATUS=0 to switch this endpoint off.',
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
 * GET|POST /setup - first-run schema import
 * --------------------------------------------------------------------
 *
 * Reaching a MySQL server without a MySQL client is the last hard part of this
 * deploy: Render's free tier gives no shell, and a managed cluster is driven from
 * a browser. The app can already connect - /status proved it - so it can create
 * its own tables, from the two .sql files in this repository.
 *
 * It is inert unless ADOMIN_SETUP_KEY is set, it refuses a schema that already has
 * the app's tables, and it runs a bounded batch per request so a proxy timeout
 * cannot leave a half-imported file: the cursor lives in application/cache and the
 * next request continues from it. See AdominInstaller for the rules.
 */
if ( ! function_exists('adomin_setup_requested')) {
	function adomin_setup_requested()
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
		if (($semi = strpos($path, ';')) !== FALSE) {
			$path = substr($path, 0, $semi);
		}
		$path = rtrim($path, '/');
		return (bool) preg_match('#(?:^|/)setup$#', $path) && !preg_match('#/(?:user|admin)/#', $path);
	}
}

if (adomin_setup_requested()) {
	require_once dirname(__FILE__).'/application/libraries/AdominInstaller.php';

	$adomin_params = adomin_status_params();
	$adomin_target = adomin_db_target();
	$adomin_facts = adomin_status_payload();
	$adomin_key_env = (string) adomin_status_env('ADOMIN_SETUP_KEY');
	$adomin_key_given = isset($_POST['adomin_key']) ? (string) $_POST['adomin_key'] : '';
	$adomin_key_ok = strlen($adomin_key_env) >= 8 && $adomin_key_given !== '' && hash_equals($adomin_key_env, $adomin_key_given);
	// subsequent batches authenticate with a single-use-ish random token instead of
	// the key, so the key is never echoed back into HTML or repeated per request
	$adomin_token_given = isset($_POST['adomin_token']) ? (string) $_POST['adomin_token'] : '';
	$adomin_token_ok = AdominInstaller::check_token($adomin_token_given);
	$adomin_allowed = $adomin_key_ok || $adomin_token_ok;
	$adomin_token = $adomin_key_ok ? AdominInstaller::issue_token() : ($adomin_token_ok ? $adomin_token_given : '');
	$adomin_allowed = $adomin_allowed && strlen($adomin_key_env) >= 8;
	$adomin_decision = AdominInstaller::decide(array(
		'disabled' => filter_var(adomin_status_env('ADOMIN_STATUS', '1'), FILTER_VALIDATE_BOOLEAN) === FALSE,
		'key_env' => $adomin_key_env,
		'key_ok' => $adomin_allowed,
		'reserved_schema' => $adomin_target['reserved'],
		'connected' => ! empty($adomin_facts['database']['connected']),
		'error' => $adomin_facts['database']['error'],
		'invoices_exists' => ! empty($adomin_facts['tables']['invoices_exists']),
		'tables' => (int) (isset($adomin_facts['database']['tables']) ? $adomin_facts['database']['tables'] : 0),
		'database' => $adomin_facts['database']['database'],
		'files_readable' => is_readable(FCPATH.'database.sql'),
	));
	$adomin_state = AdominInstaller::read_state();
	$adomin_log = array();
	$adomin_ran = 0;

	$adomin_action = isset($_POST['adomin_action']) ? $_POST['adomin_action'] : '';
	if ($adomin_action === 'reset' && $adomin_allowed) {
		AdominInstaller::reset();
		$adomin_state = AdominInstaller::read_state();
	} elseif ($adomin_action === 'run' && $adomin_allowed && $adomin_decision[0]) {
		$adomin_link = adomin_db_open($adomin_target);
		if ( ! empty($adomin_link['link'])) {
			$adomin_run = AdominInstaller::run_batch($adomin_link['link']);
			$adomin_log = $adomin_run['log'];
			$adomin_state = $adomin_run['state'];
			$adomin_ran = $adomin_run['ran'];
			@mysqli_close($adomin_link['link']);
			// the fresh table count, so the page reflects what just happened
			$adomin_facts = adomin_status_payload();
		}
	}

	$adomin_e = function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
	$adomin_files = AdominInstaller::files();
	$adomin_rows = '';
	foreach ($adomin_log as $entry) {
		$class = substr((string) $entry['note'], 0, 5) === 'ERROR' ? 'err' : ((strpos((string) $entry['note'], 'skipped') === 0 || strpos((string) $entry['note'], 'already') === 0) ? 'muted' : 'ok');
		$adomin_rows .= '<tr><td>'.$adomin_e($entry['file']).' #'.(int) $entry['n'].'</td><td><code>'.$adomin_e($entry['sql']).'</code></td><td class="'.$class.'">'.$adomin_e($entry['note']).'</td></tr>';
	}
	$adomin_progress = '';
	$adomin_current = isset($adomin_files[$adomin_state['file']]) ? $adomin_files[$adomin_state['file']] : NULL;
	if ( ! $adomin_state['done'] && $adomin_current && is_readable($adomin_current['path'])) {
		$adomin_total = count(AdominInstaller::split_statements((string) @file_get_contents($adomin_current['path'])));
		$adomin_progress = ' currently in <code>'.$adomin_e($adomin_current['label']).'</code>, statement '
			.((int) $adomin_state['offset'] + 1).' of '.(int) $adomin_total;
	}

	$adomin_body = '<p class="muted">Import the app\'s schema into <code>'.$adomin_e(isset($adomin_facts['database']['database']) ? $adomin_facts['database']['database'] : '') ?: '(none)'.'</code> on <code>'
		.$adomin_e(isset($adomin_facts['database']['host']) ? $adomin_facts['database']['host'] : '').'</code> as <code>'.$adomin_e(isset($adomin_facts['database']['username']) ? $adomin_facts['database']['username'] : '').'</code> &middot; TLS: '
		.$adomin_e(isset($adomin_facts['database']['tls']) ? $adomin_facts['database']['tls'] : '').' &middot; tables now: '.(int) $adomin_facts['database']['tables'].$adomin_progress.'</p>';

	$adomin_body .= '<div class="card '.($adomin_decision[0] ? 'good' : 'warn').'"><strong>'.$adomin_e($adomin_decision[1]).'</strong></div>';

	if ($adomin_key_env === '') {
		$adomin_body .= '<div class="card"><strong>Nothing is enabled yet.</strong> In Render &rarr; your service &rarr; Environment, add '
			.'<code>ADOMIN_SETUP_KEY</code> with any private string of 8 characters or more, press Apply (it reboots), '
			.'then reopen this page and type that value in. The key is the only thing standing between this page and a stranger, '
			.'so do not reuse a password and delete the variable when the import is done.</div>';
	} elseif ( ! $adomin_allowed) {
		$adomin_body .= '<div class="card bad">This needs the key: type the value of <code>ADOMIN_SETUP_KEY</code> below. '
			.(strlen($adomin_key_env) < 8 ? '<br><span class="err">ADOMIN_SETUP_KEY is shorter than 8 characters - lengthen it first.</span>' : '')
			.'</div>';
	}

	if ($adomin_state['done']) {
		// the run is over: the batch token stops working immediately
		AdominInstaller::clear_token();
	}
	if ($adomin_allowed && $adomin_decision[0]) {
		$adomin_auto = ( ! $adomin_state['done'] && $adomin_ran > 0) ? '<script>document.addEventListener("DOMContentLoaded",function(){document.getElementById("adomin-go").click();});</script>' : '';
		$adomin_body .= '<form method="post" action="/setup" class="card" id="adomin-run" autocomplete="off">'
			.($adomin_token !== '' ? '<input type="hidden" name="adomin_token" value="'.$adomin_e($adomin_token).'">' : '')
			.'<p><label>'.($adomin_token !== '' ? 'Key (already accepted for this run - leave this empty to continue with the token)' : 'Setup key')
			.'<br><input type="password" name="adomin_key" autocomplete="off" value=""></label></p>'
			.'<p><button type="submit" name="adomin_action" value="run" id="adomin-go">'.($adomin_state['done'] ? 'Re-check (skip what is already in place)' : 'Import the next batch').'</button> '
			.'<button type="submit" name="adomin_action" value="reset" class="muted">Start over from statement 1</button></p>'
			.'<p class="muted">Each request runs at most 25 statements or 8 seconds, then continues where it stopped; the page re-submits itself until the files are done. '
			.'Statements that are already in place are skipped rather than repeated, so a mid-file timeout is recovered by pressing the button again.</p>'
			.'</form>'.$adomin_auto;
	} elseif ($adomin_allowed && $adomin_state['done']) {
		$adomin_body .= '<div class="card good">Both files have been run. <a href="/">Open the app</a> and <a href="/status">/status</a> should read <code>state: ok</code> with the tables counted. Remove <code>ADOMIN_SETUP_KEY</code> from Render afterwards.</div>';
	}

	$adomin_body .= '<p class="muted">Prefer to do it yourself? <code>CREATE DATABASE '.$adomin_e($adomin_facts['database']['database'] ?: 'adomin').'</code> then '
		.'<code>mysql --ssl-mode=REQUIRED -h HOST -P PORT -u USER -p --database=NAME &lt; database.sql</code>, '
		.'and the same for <code>database/adomin-updates.sql</code>. Both files are in the repository root.</p>';

	if ($adomin_rows !== '') {
		$adomin_body .= '<h2 style="font-size:1rem;margin-top:1.6rem">This batch</h2><table><tr><th>#</th><th>statement</th><th>result</th></tr>'.$adomin_rows.'</table>';
	}
	$adomin_body .= '<p class="muted">created '.(int) $adomin_state['created'].' tables &middot; skipped '.(int) $adomin_state['skipped'].' &middot; errors '.(int) $adomin_state['errors'].'</p>';

	AdominInstaller::render('Adomin Invoices &mdash; first-run setup', $adomin_body, ($adomin_allowed && $adomin_decision[0]) || $adomin_action === 'run' ? 200 : 403);
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
