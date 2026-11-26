<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------
| DATABASE CONNECTIVITY SETTINGS
| -------------------------------------------------------------------
| This file will contain the settings needed to access your database.
|
| For complete instructions please consult the 'Database Connection'
| page of the User Guide.
|
| -------------------------------------------------------------------
| EXPLANATION OF VARIABLES
| -------------------------------------------------------------------
|
|	['dsn']      The full DSN string describe a connection to the database.
|	['hostname'] The hostname of your database server.
|	['username'] The username used to connect to the database
|	['password'] The password used to connect to the database
|	['database'] The name of the database you want to connect to
|	['dbdriver'] The database driver. e.g.: mysqli.
|			Currently supported:
|				 cubrid, ibase, mssql, mysql, mysqli, oci8,
|				 odbc, pdo, postgre, sqlite, sqlite3, sqlsrv
|	['dbprefix'] You can add an optional prefix, which will be added
|				 to the table name when using the  Query Builder class
|	['pconnect'] TRUE/FALSE - Whether to use a persistent connection
|	['db_debug'] TRUE/FALSE - Whether database errors should be displayed.
|	['cache_on'] TRUE/FALSE - Enables/disables query caching
|	['cachedir'] The path to the folder where cache files should be stored
|	['char_set'] The character set used in communicating with the database
|	['dbcollat'] The character collation used in communicating with the database
|				 NOTE: For MySQL and MySQLi databases, this setting is only used
| 				 as a backup if your server is running PHP < 5.2.3 or MySQL < 5.0.7
|				 (and in table creation queries made with DB Forge).
| 				 There is an incompatibility in PHP with mysql_real_escape_string() which
| 				 can make your site vulnerable to SQL injection if you are using a
| 				 multi-byte character set and are running versions lower than these.
| 				 Sites using Latin-1 or UTF-8 database character set and collation are unaffected.
|	['swap_pre'] A default table prefix that should be swapped with the dbprefix
|	['encrypt']  Whether or not to use an encrypted connection.
|
|			'mysql' (deprecated), 'sqlsrv' and 'pdo/sqlsrv' drivers accept TRUE/FALSE
|			'mysqli' and 'pdo/mysql' drivers accept an array with the following options:
|
|				'ssl_key'    - Path to the private key file
|				'ssl_cert'   - Path to the public key certificate file
|				'ssl_ca'     - Path to the certificate authority file
|				'ssl_capath' - Path to a directory containing trusted CA certificats in PEM format
|				'ssl_cipher' - List of *allowed* ciphers to be used for the encryption, separated by colons (':')
|				'ssl_verify' - TRUE/FALSE; Whether verify the server certificate or not ('mysqli' only)
|
|	['compress'] Whether or not to use client compression (MySQL only)
|	['stricton'] TRUE/FALSE - forces 'Strict Mode' connections
|							- good for ensuring strict SQL while developing
|	['ssl_options']	Used to set various SSL options that can be used when making SSL connections.
|	['failover'] array - A array with 0 or more data for connections if the main should fail.
|	['save_queries'] TRUE/FALSE - Whether to "save" all executed queries.
| 				NOTE: Disabling this will also effectively disable both
| 				$this->db->last_query() and profiling of DB queries.
| 				When you run a query, with this setting set to TRUE (default),
| 				CodeIgniter will store the SQL statement for debugging purposes.
| 				However, this may cause high memory usage, especially if you run
| 				a lot of SQL queries ... disable this to avoid that problem.
|
| The $active_group variable lets you choose which connection group to
| make active.  By default there is only one group (the 'default' group).
|
| The $query_builder variables lets you determine whether or not to load
| the query builder class.
*/
$active_group = 'default';
$query_builder = TRUE;

/*
| -------------------------------------------------------------------
| Adomin: where the credentials come from
| -------------------------------------------------------------------
| Two styles are supported, and the individual variables win when both
| are present:
|
|   1. individual variables   DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT
|   2. one connection URL     DATABASE_URL, MYSQL_URL, DB_URL or
|                             CLEARDB_DATABASE_URL, in the form
|                             mysql://user:pass@host:4000/db?ssl-mode=REQUIRED
|
| Style 2 is what most hosts hand you (TiDB Cloud, Aiven, PlanetScale,
| Railway, ClearDB, Hostinger), so it can be pasted straight into the
| Render dashboard without splitting it into four fields. Reserved characters
| in the password must be percent-encoded, as in any URL.
|
| TLS is switched on by the URL (?ssl-mode=REQUIRED / ?sslmode=require) or by
| DB_SSL=1, using DB_SSL_CA if given and the system bundle
| (/etc/ssl/certs/ca-certificates.crt) otherwise. Certificate verification is
| off by default - like mysql's ssl-mode=REQUIRED - so a host whose
| certificate does not match its hostname still connects; set DB_SSL_VERIFY=1
| when you want the strict, pinning behaviour.
*/

if ( ! function_exists('adomin_db_env'))
{
	function adomin_db_env($key, $default = '')
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
}

if ( ! function_exists('adomin_db_url'))
{
	/**
	 * The first connection URL that is actually set, or an empty string.
	 */
	function adomin_db_url()
	{
		foreach (array('DB_URL', 'MYSQL_URL', 'CLEARDB_DATABASE_URL', 'DATABASE_URL') as $key)
		{
			$value = trim(adomin_db_env($key));
			if ($value !== '')
			{
				return $value;
			}
		}

		return '';
	}
}

if ( ! function_exists('adomin_db_config'))
{
	/**
	 * Turns the environment into the pieces CodeIgniter needs. Never echoes a
	 * credential: a wrong engine or a malformed URL is reported, not dumped.
	 */
	function adomin_db_config()
	{
		$out = array(
			'hostname' => trim(adomin_db_env('DB_HOST')),
			'username' => adomin_db_env('DB_USER'),
			'password' => adomin_db_env('DB_PASS'),
			'database' => adomin_db_env('DB_NAME'),
			'port'     => trim(adomin_db_env('DB_PORT')),
			'encrypt'  => FALSE
		);

		$url = adomin_db_url();
		$url_declared_tls = FALSE;
		if ($url !== '')
		{
			if (preg_match('#^(postgres(ql)?|pgsql|redshift)(\+\w+)?://#i', $url))
			{
				// This app talks MySQL through mysqli: 58 tables of MySQL DDL,
				// MySQL date functions and the CI mysqli driver. A PostgreSQL
				// link (Neon, Supabase, Render's own Postgres) cannot be used
				// here, and a mysqli connect attempt against port 5432 fails as
				// a blank screen. Fail loudly, with the fix, instead.
				if ( ! headers_sent())
				{
					header('HTTP/1.1 503 Service Unavailable');
					header('Content-Type: text/plain; charset=utf-8');
				}
				exit(
					"Adomin Invoices: the configured database URL is a PostgreSQL link, and this app is MySQL-only (PHP 'mysqli').\n\n".
					"Use a MySQL 5.7/8.0-compatible server and paste its mysql:// URL into DATABASE_URL\n".
					"(free options: TiDB Cloud Starter, Aiven for MySQL, PlanetScale paid, or your own\n".
					"MySQL/MariaDB container), or set DB_HOST/DB_USER/DB_PASS/DB_NAME separately.\n".
					"Import database.sql and database/adomin-updates.sql into it before the first request.\n\n".
					"The PostgreSQL link itself was not printed, so its password stays private.\n"
				);
			}

			$parts = parse_url($url);
			if ($parts !== FALSE && ! empty($parts['host']))
			{
				$out['hostname'] = $out['hostname'] !== '' ? $out['hostname'] : $parts['host'];
				$out['username'] = $out['username'] !== '' ? $out['username'] : (isset($parts['user']) ? rawurldecode($parts['user']) : '');
				$out['password'] = $out['password'] !== '' ? $out['password'] : (isset($parts['pass']) ? rawurldecode($parts['pass']) : '');
				if ($out['database'] === '' && ! empty($parts['path']))
				{
					$out['database'] = preg_replace('#^/#', '', $parts['path']);
				}
				if ($out['port'] === '' && ! empty($parts['port']))
				{
					$out['port'] = (int) $parts['port'];
				}

				// query string: ?ssl-mode=REQUIRED, ?sslmode=require&sslca=..., ?timezone=...
				if (isset($parts['query']))
				{
					parse_str($parts['query'], $qs);
					$mode = '';
					foreach (array('ssl-mode', 'sslmode', 'ssl_mode', 'tls') as $key)
					{
						if (isset($qs[$key]) && $qs[$key] !== '')
						{
							$mode = strtolower($qs[$key]);
						}
					}
					$ca = '';
					foreach (array('sslca', 'ssl-ca', 'ssl_ca', 'sslrootcert') as $key)
					{
						if (isset($qs[$key]) && $qs[$key] !== '')
						{
							$ca = $qs[$key];
						}
					}

					if ($mode !== '' && strpos($mode, 'disabled') === FALSE && $mode !== 'false' && $mode !== 'skip-verify')
					{
						$out['encrypt'] = array('ssl_verify' => FALSE);
					}
					elseif ($mode !== '')
					{
						// ssl-mode=DISABLED is an explicit instruction, so it also
						// overrides the "this host always needs TLS" rule below
						$out['encrypt'] = FALSE;
						$url_declared_tls = TRUE;
					}
					if ($ca !== '')
					{
						$out['encrypt'] = is_array($out['encrypt']) ? $out['encrypt'] : array('ssl_verify' => FALSE);
						$out['encrypt']['ssl_ca'] = $ca;
					}
				}
			}
		}

		/*
		 * TLS, in order of precedence: DB_SSL wins, then what the URL said, and
		 * otherwise hosts that will not accept an unencrypted connection at all
		 * switch it on by themselves. That last rule is not politeness: TiDB Cloud
		 * answers "Connections using insecure transport are prohibited", and because
		 * the database library is autoloaded that failure turns into the generic
		 * production 500 on every single page. Set DB_SSL=0 to force it off.
		 */
		$db_ssl = trim(adomin_db_env('DB_SSL'));
		if ($db_ssl !== '')
		{
			$out['encrypt'] = filter_var($db_ssl, FILTER_VALIDATE_BOOLEAN)
				? (is_array($out['encrypt']) ? $out['encrypt'] : array('ssl_verify' => FALSE))
				: FALSE;
		}
		elseif ($out['encrypt'] === FALSE && ! $url_declared_tls
			&& function_exists('adomin_db_tls_required_host')
			&& adomin_db_tls_required_host($out['hostname']))
		{
			$out['encrypt'] = array('ssl_verify' => FALSE);
		}
		/*
		 * A server-owned schema. Pointing the app at `sys` or `mysql` connects
		 * successfully and then fails on every page, because the tables are not
		 * there and the app is not allowed to create them - and with the database
		 * library autoloaded that failure is CodeIgniter's generic production
		 * error page, indistinguishable from a broken deploy. Say what to do
		 * instead. DB_ALLOW_SYSTEM_SCHEMA=1 opts out if you really mean it.
		 */
		$reserved = array('sys', 'mysql', 'information_schema', 'performance_schema');
		if ($out['database'] !== ''
			&& in_array(strtolower($out['database']), $reserved, TRUE)
			&& ! filter_var(adomin_db_env('DB_ALLOW_SYSTEM_SCHEMA'), FILTER_VALIDATE_BOOLEAN))
		{
			if ( ! headers_sent())
			{
				header('HTTP/1.1 503 Service Unavailable');
				header('Content-Type: text/plain; charset=utf-8');
			}
			exit(
				"Adomin Invoices: DB_NAME is `" . $out['database'] . "`, a schema the MySQL server owns.\n\n" .
				"Create (or pick) your own schema and import the app's tables into it:\n\n" .
				"    mysql --database=yourdb < database.sql\n" .
				"    mysql --database=yourdb < database/adomin-updates.sql\n\n" .
				"If the server has no application schema at all (a fresh TiDB Cloud cluster\n" .
				"usually has none - `defaultdb` only exists on some), create one first:\n" .
				"    CREATE DATABASE `adomin`\n" .
				"then point DB_NAME (or the path part of DATABASE_URL) at it and reboot.\n" .
				"GET /status shows the whole picture, including whether the tables landed.\n"
			);
		}

		if ($out['encrypt'] !== FALSE)
		{
			$ca = trim(adomin_db_env('DB_SSL_CA'));
			if ($ca === '' && is_file('/etc/ssl/certs/ca-certificates.crt'))
			{
				$ca = '/etc/ssl/certs/ca-certificates.crt';
			}
			if ($ca !== '')
			{
				$out['encrypt']['ssl_ca'] = $ca;
			}
			// Verification is a separate decision from encryption and must not depend
			// on finding a CA bundle: off by default (like mysql's ssl-mode=REQUIRED)
			// because a host whose certificate does not match its hostname would
			// otherwise refuse the handshake, on set DB_SSL_VERIFY=1.
			$out['encrypt']['ssl_verify'] = filter_var(adomin_db_env('DB_SSL_VERIFY', '0'), FILTER_VALIDATE_BOOLEAN);
		}

		return $out;
	}
}

$_adomin_db = adomin_db_config();

$db['default'] = array(
	'dsn'	=> '',
	'hostname' => $_adomin_db['hostname'] !== '' ? $_adomin_db['hostname'] : 'localhost',
	'username' => $_adomin_db['username'] !== '' ? $_adomin_db['username'] : 'root',
	'password' => $_adomin_db['password'],
	'database' => $_adomin_db['database'] !== '' ? $_adomin_db['database'] : 'test',
	'port' => $_adomin_db['port'] !== '' ? (int) $_adomin_db['port'] : 3306,
	'dbdriver' => 'mysqli',
	'dbprefix' => '',
	'pconnect' => FALSE,
	'db_debug' => (ENVIRONMENT !== 'production'),
	'cache_on' => FALSE,
	'cachedir' => '',
	'char_set' => 'utf8',
	'dbcollat' => 'utf8_general_ci',
	'swap_pre' => '',
	'encrypt' => $_adomin_db['encrypt'],
	'compress' => FALSE,
	'stricton' => FALSE,
	'failover' => array(),
	'save_queries' => TRUE
);

unset($_adomin_db);
