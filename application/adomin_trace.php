<?php
/**
 * NOTE: no `defined('BASEPATH') OR exit` here on purpose. This file is required
 * by index.php before BASEPATH exists. Direct web access is refused by
 * application/.htaccess.
 */

/**
 * Adomin Technologies — breadcrumbs for requests that die without a word.
 *
 * WHY THIS EXISTS
 * ---------------
 * A POST to /login on the hosted instance answers 500 with an Apache error page
 * and *nothing* in the log: no PHP error, no warning, no line from
 * adomin_fatal_guard.php. Nothing can be concluded from that, because a script
 * that dies silently leaves no evidence behind.
 *
 * The way out is to write evidence *before* the death rather than after it. Each
 * breadcrumb is one line, flushed to disk at the moment it is reached, so
 * whatever happened, the file ends with the last place the request actually got
 * to. A shutdown handler cannot do this — it only runs if PHP reaches shutdown.
 *
 * Cost: nothing on the paths that work. Only a non-GET request, or a request
 * carrying ?trace=1, writes anything; a GET without it returns immediately.
 * The file is trimmed to the newest lines so it cannot grow without bound on a
 * free instance.
 */

if ( ! function_exists('adomin_trace_wanted'))
{
	/**
	 * Should this request leave breadcrumbs?
	 *
	 * GETs are excluded because Render's health check hits / every few seconds
	 * and would drown the log. A POST is exactly what we cannot see, and
	 * ?trace=1 lets a GET be traced by hand.
	 */
	function adomin_trace_wanted()
	{
		if (getenv('ADOMIN_TRACE_OFF') !== FALSE)
		{
			return FALSE;
		}

		$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : '';
		if ($method !== '' && $method !== 'GET' && $method !== 'HEAD')
		{
			return TRUE;
		}

		return isset($_GET['trace']) && $_GET['trace'] !== '0';
	}
}

if ( ! function_exists('adomin_trace_file'))
{
	/**
	 * The current trace file. One per day, next to the fatal log, because both
	 * are read through status.php on an instance with no shell.
	 */
	function adomin_trace_file()
	{
		$base = defined('APPPATH') ? rtrim(APPPATH, '/\\') . DIRECTORY_SEPARATOR : '';
		if ($base === '')
		{
			$base = dirname(__FILE__) . DIRECTORY_SEPARATOR;
		}
		return $base . 'logs' . DIRECTORY_SEPARATOR . 'trace-' . date('Y-m-d') . '.log';
	}
}

if ( ! function_exists('adomin_trace'))
{
	/**
	 * Leave a breadcrumb, then flush it. Safe to call from anywhere, including
	 * before the framework exists, and it never throws: tracing must not be able
	 * to break the request it is watching.
	 *
	 * @param string $label what just happened
	 * @param array  $data  optional extra key => value details
	 */
	function adomin_trace($label, $data = NULL)
	{
		static $enabled = NULL;

		if ($enabled === NULL)
		{
			$enabled = adomin_trace_wanted();
			if ($enabled)
			{
				// one banner per request, so entries can be told apart
				$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '?';
				$uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '?';
				adomin_trace('request', array(
					'method' => $method,
					'uri' => $uri,
					'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '?',
				));
			}
		}

		if ($enabled !== TRUE)
		{
			return FALSE;
		}

		$details = '';
		if (is_array($data))
		{
			$pairs = array();
			foreach ($data as $key => $value)
			{
				$pairs[] = $key . '=' . (is_scalar($value) ? (string) $value : gettype($value));
			}
			$details = ' ' . implode(' ', $pairs);
		}
		elseif (is_scalar($data))
		{
			$details = ' ' . (string) $data;
		}

		$line = sprintf(
			'%s %-22s %6.1fMB%s',
			date('H:i:s'),
			$label,
			memory_get_usage(TRUE) / 1048576,
			$details
		);

		return @file_put_contents(adomin_trace_file(), $line . PHP_EOL, FILE_APPEND | LOCK_EX) !== FALSE;
	}
}

if ( ! function_exists('adomin_trace_trim'))
{
	/**
	 * Keep the trace file small. Called once per traced request, after the fact,
	 * so a busy instance cannot fill the free tier's disk with breadcrumbs.
	 *
	 * @param int $keep number of trailing lines to keep
	 */
	function adomin_trace_trim($keep = 400)
	{
		$file = adomin_trace_file();
		if ( ! is_file($file) || @filesize($file) < 65536)
		{
			return;
		}

		$lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if ( ! is_array($lines) || count($lines) <= $keep)
		{
			return;
		}

		@file_put_contents($file, implode(PHP_EOL, array_slice($lines, -$keep)) . PHP_EOL, LOCK_EX);
	}
}

if ( ! function_exists('adomin_trace_current'))
{
	/**
	 * The breadcrumbs belonging to the request in progress: from its own banner
	 * line to the end of the file.
	 *
	 * The shutdown handler uses this to put the trail in the error log, which
	 * matters on an instance with no shell and no STATUS_TOKEN set - the place a
	 * request stopped then appears in Render -> Logs by itself, next to the 500.
	 *
	 * @param int $limit most lines to return (the tail is what counts)
	 * @return array
	 */
	function adomin_trace_current($limit = 12)
	{
		$file = adomin_trace_file();
		if ( ! is_file($file))
		{
			return array();
		}

		$lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if ( ! is_array($lines) || empty($lines))
		{
			return array();
		}

		// walk back to this request's banner
		$start = 0;
		for ($i = count($lines) - 1; $i >= 0; $i--)
		{
			if (strpos($lines[$i], ' request ') !== FALSE && strpos($lines[$i], 'method=') !== FALSE)
			{
				$start = $i;
				break;
			}
		}

		return array_slice($lines, $start, $limit);
	}
}
