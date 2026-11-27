<?php
/**
 * NOTE: no `defined('BASEPATH') OR exit` line here. This file is required by
 * index.php *before* BASEPATH exists, and it must survive that: it exists to
 * report errors that happen during the very boot it runs in. Direct web access
 * is already refused by application/.htaccess.
 */

/**
 * Adomin Technologies — last-resort error reporting for the front controller.
 *
 * WHY THIS FILE EXISTS
 * --------------------
 * A production CodeIgniter install is deliberately quiet: `display_errors` is
 * Off, so a fatal error produces no output at all. Two things then happen on a
 * hosted instance, and both cost hours:
 *
 *   1. the visitor gets Apache's default 500 page (about 550 bytes, no useful
 *      text), and Render's request log records only "POST /login 500 551";
 *   2. nothing is written where the owner can read it. CodeIgniter logs to
 *      application/logs/, which on the free tier cannot be opened - there is no
 *      shell and no persistent disk.
 *
 * So the real cause ("Call to a member function X() on null in
 * models/PublicModel.php line 41") stays invisible, and every debugging round
 * becomes a guess.
 *
 * What this adds, for fatals and uncaught exceptions only:
 *   * one line to the SAPI error log, which in this image is stderr and therefore
 *     appears in Render -> Logs next to the request;
 *   * the same line appended to application/logs/fatal-YYYY-MM-DD.php;
 *   * a small page instead of a blank one: what failed, and where, when
 *     APP_ENV=development; a one-line pointer to the log when it is production.
 *
 * Normal requests are untouched: the handler exits immediately unless PHP is
 * already shutting down after a fatal error or an uncaught exception, and it
 * never replaces output that was already produced.
 */

if ( ! function_exists('adomin_fatal_type_is_fatal'))
{
	function adomin_fatal_type_is_fatal($type)
	{
		return (bool) ($type & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR));
	}
}

if ( ! function_exists('adomin_fatal_relative'))
{
	/**
	 * Path relative to the application root, so a log line does not have to
	 * repeat "/var/www/html/" and a browser never learns the server layout.
	 */
	function adomin_fatal_relative($path)
	{
		$base = defined('FCPATH') ? FCPATH : '';
		if ($base !== '' && strpos($path, $base) === 0)
		{
			return substr($path, strlen($base));
		}
		return $path;
	}
}

if ( ! function_exists('adomin_fatal_log'))
{
	function adomin_fatal_log($summary, $detail)
	{
		$line = 'Adomin FATAL: ' . $summary . ($detail !== '' ? ' | ' . $detail : '');

		// stderr first: this is what shows up in Render -> Logs.
		error_log($line);

		// ...and the framework's own log directory, when it is writable. The
		// separator is added explicitly: APPPATH normally ends with one, but a
		// caller that defines it without the trailing slash would otherwise
		// write "logsfatal-....php" next to the directory and lose the line.
		$dir = defined('APPPATH') ? rtrim(APPPATH, '/\\') . DIRECTORY_SEPARATOR . 'logs' : '';
		if ($dir !== '' && is_dir($dir))
		{
			$file = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'fatal-' . date('Y-m-d') . '.php';
			$stamp = date('Y-m-d H:i:s') . ' --> ' . $line . PHP_EOL;
			@file_put_contents($file, '<?php defined("BASEPATH") OR exit("No direct script access allowed"); ?>' . PHP_EOL . $stamp, FILE_APPEND);
		}
	}
}

if ( ! function_exists('adomin_fatal_page'))
{
	/**
	 * The replacement page. In development it carries the message, file and
	 * line; in production it says where to look, because PHP error text names
	 * absolute paths and internal functions - useful to an attacker, useless to
	 * a customer.
	 */
	function adomin_fatal_page($heading, $message, $file = '', $line = 0)
	{
		if (headers_sent())
		{
			return;
		}

		if (function_exists('http_response_code'))
		{
			http_response_code(500);
		}
		else
		{
			header('HTTP/1.1 500 Internal Server Error', TRUE, 500);
		}
		header('Content-Type: text/html; charset=utf-8');
		header('Cache-Control: no-store');

		$development = defined('ENVIRONMENT') && ENVIRONMENT !== 'production';
		$title = 'This page could not be rendered';
		$safe = function ($value) {
			return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
		};

		echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
			. '<meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<title>' . $safe($title) . '</title><style>'
			. 'body{margin:0;padding:2.5rem 1.25rem;font:15px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
			. 'color:#1f2733;background:#f6f8fb}main{max-width:40rem;margin:0 auto}h1{font-size:1.25rem;margin:0 0 .6rem}'
			. 'p{margin:.4rem 0}code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.87rem;'
			. 'background:#eef2f7;padding:.1rem .3rem;border-radius:.25rem}.muted{color:#64748b;font-size:.87rem}'
			. 'pre{background:#fff;border:1px solid #e2e8f0;border-radius:.5rem;padding:.8rem;overflow:auto}</style></head><body><main>';

		echo '<h1>' . $safe($heading !== '' ? $heading : $title) . '</h1>';

		if ($development)
		{
			if ($message !== '')
			{
				echo '<p><code>' . $safe($message) . '</code></p>';
			}
			if ($file !== '')
			{
				echo '<p class="muted">' . $safe(adomin_fatal_relative($file)) . ($line ? ':' . (int) $line : '') . '</p>';
			}
			echo '<p class="muted">APP_ENV=development, so this detail is shown. It is also in Render &rarr; Logs '
				. '(line starting with <code>Adomin FATAL</code>). Set APP_ENV=production to hide it again.</p>';
		}
		else
		{
			echo '<p>The application stopped before it could draw this page. The reason was written to the server log '
				. '(Render &rarr; Logs, a line beginning <code>Adomin FATAL</code>).</p>'
				. '<p class="muted">Deployment health: <a href="' . $safe(rtrim(base_url_or_empty(), '/')) . '/status.php">/status.php</a>. '
				. 'To see the technical detail in the browser, set <code>APP_ENV=development</code>, reload, then set it back.</p>';
		}

		echo '</main></body></html>';
	}
}

if ( ! function_exists('base_url_or_empty'))
{
	function base_url_or_empty()
	{
		// Deliberately not the CI function: this may run before the framework is up.
		$scheme = 'http';
		if ( ! empty($_SERVER['HTTP_X_FORWARDED_PROTO']))
		{
			$scheme = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
		}
		elseif ( ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
		{
			$scheme = 'https';
		}
		$host = ! empty($_SERVER['HTTP_X_FORWARDED_HOST'])
			? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_HOST'])[0])
			: (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');

		return $host === '' ? '' : $scheme . '://' . $host;
	}
}

if ( ! function_exists('adomin_fatal_report'))
{
	function adomin_fatal_report($summary, $detail, $file, $line, $heading, $message)
	{
		if (ob_get_level() > 0)
		{
			// Drop a half-written page: a fragment plus an error page is worse
			// than the error page alone.
			$buffer = '';
			while (ob_get_level() > 0)
			{
				$chunk = ob_get_clean();
				if (is_string($chunk))
				{
					$buffer = $chunk . $buffer;
				}
			}
			if (trim(strip_tags($buffer)) === '')
			{
				$buffer = '';
			}
			else
			{
				// Keep what was rendered: the user may be mid-way through a form.
				echo $buffer;
				adomin_fatal_log($summary, $detail);
				return;
			}
		}

		adomin_fatal_log($summary, $detail);
		adomin_fatal_page($heading, $message, $file, $line);
	}
}

if ( ! function_exists('adomin_fatal_shutdown'))
{
	function adomin_fatal_shutdown()
	{
		$error = error_get_last();
		if ( ! is_array($error) || ! adomin_fatal_type_is_fatal($error['type']))
		{
			return;
		}

		adomin_fatal_report(
			$error['message'],
			adomin_fatal_relative($error['file']) . ':' . $error['line'],
			$error['file'],
			$error['line'],
			'This page could not be rendered',
			$error['message']
		);
	}
}

if ( ! function_exists('adomin_fatal_exception'))
{
	function adomin_fatal_exception($exception)
	{
		// Read through get_class()/method calls rather than typing $exception
		// against Throwable: PHP 5-style runtimes (and some embedded ones) do
		// not define that interface, and a TypeError inside an exception handler
		// would replace the real error with a confusing one.
		$class = is_object($exception) ? get_class($exception) : 'Throwable';
		$message = is_object($exception) && method_exists($exception, 'getMessage') ? $exception->getMessage() : '';
		$file = is_object($exception) && method_exists($exception, 'getFile') ? $exception->getFile() : '';
		$line = is_object($exception) && method_exists($exception, 'getLine') ? $exception->getLine() : 0;

		adomin_fatal_report(
			'Uncaught ' . $class . ': ' . $message,
			adomin_fatal_relative($file) . ':' . $line,
			$file,
			$line,
			'This page could not be rendered',
			$class . ': ' . $message
		);
	}
}

register_shutdown_function('adomin_fatal_shutdown');
set_exception_handler('adomin_fatal_exception');
