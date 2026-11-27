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
 * ---------------------------------------------------------------------
 * Adomin: connection settings from the environment
 * ---------------------------------------------------------------------
 *
 * A hosted deploy must not need an edited config file, and credentials do not
 * belong in a repository. Both are read from the environment here, exactly one
 * of two ways - individual variables, or one connection URL from the database
 * provider:
 *
 *   DB_HOST / DB_USER / DB_PASS / DB_NAME / DB_PORT
 *   DATABASE_URL=mysql://user:pass@host:3306/dbname?ssl-mode=REQUIRED
 *   (DB_URL, MYSQL_URL and CLEARDB_DATABASE_URL are also accepted; individual
 *   variables win where both are set, so a partial override is possible.)
 *
 * TLS is explicit, never guessed: ?ssl-mode= / ?sslmode= in the URL or DB_SSL=1,
 * with DB_SSL_CA to point at an authority and DB_SSL_VERIFY=1 to also require the
 * server certificate to match its hostname. This app speaks MySQL through the
 * mysqli driver; a PostgreSQL URL (Neon, Supabase, Render Postgres) cannot be
 * made to work by configuration.
 *
 * When nothing is set, the values below stay as the project had them, so local
 * XAMPP-style installs are unaffected.
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

if ( ! function_exists('adomin_db_config'))
{
	function adomin_db_config()
	{
		$out = array(
			'hostname' => trim(adomin_db_env('DB_HOST')),
			'username' => trim(adomin_db_env('DB_USER')),
			'password' => adomin_db_env('DB_PASS'),
			'database' => trim(adomin_db_env('DB_NAME')),
			'port'     => trim(adomin_db_env('DB_PORT')),
			'encrypt'  => FALSE
		);

		$url = '';
		foreach (array('DB_URL', 'MYSQL_URL', 'CLEARDB_DATABASE_URL', 'DATABASE_URL') as $key)
		{
			$candidate = trim(adomin_db_env($key));
			if ($candidate !== '')
			{
				$url = $candidate;
				break;
			}
		}

		if ($url !== '')
		{
			$parts = parse_url($url);
			if (is_array($parts) && ! empty($parts['host']))
			{
				$out['hostname'] = $out['hostname'] !== '' ? $out['hostname'] : $parts['host'];
				$out['username'] = $out['username'] !== '' ? $out['username'] : (isset($parts['user']) ? rawurldecode($parts['user']) : '');
				$out['password'] = $out['password'] !== '' ? $out['password'] : (isset($parts['pass']) ? rawurldecode($parts['pass']) : '');
				if ($out['database'] === '' && ! empty($parts['path']))
				{
					$out['database'] = ltrim($parts['path'], '/');
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
							// "REQUIRED"/"verify-ca"/... turn TLS on; "DISABLED" and
							// "SKIP-VERIFY" are handled here, not by guessing
							if (strpos($mode, 'disable') === FALSE && strpos($mode, 'skip') === FALSE && $mode !== 'false')
							{
								$out['encrypt'] = array(
									// verification only when the URL asks for it
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

		$ssl = trim(adomin_db_env('DB_SSL'));
		if ($ssl !== '')
		{
			$out['encrypt'] = filter_var($ssl, FILTER_VALIDATE_BOOLEAN)
				? (is_array($out['encrypt']) ? $out['encrypt'] : array('ssl_verify' => FALSE))
				: FALSE;
		}

		if (is_array($out['encrypt']))
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
