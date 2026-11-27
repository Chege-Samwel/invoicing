<?php
/**
 * NOTE: no `defined('BASEPATH') OR exit` here. This file is loaded from index.php
 * before BASEPATH exists. Direct web access is refused by application/.htaccess.
 */

/**
 * Adomin Technologies — make a CSRF rejection say why it was rejected.
 *
 * WHAT THIS IS FOR
 * ----------------
 * CodeIgniter answers a failed CSRF check with a bare 403 and the text "The
 * action you have requested is not allowed." That sentence is true of four
 * different situations, and they need completely different fixes:
 *
 *   1. the visitor sent no token at all (no hidden input, old cached page);
 *   2. the visitor sent no cookie (cookie blocked, expired, or not yet set);
 *   3. both were sent but they are different values (stale page, cookie rotated);
 *   4. the request was not a normal browser form at all.
 *
 * A 403 that cannot tell those apart costs hours, so this writes one line
 * naming the case, with lengths and prefixes only — never a full token, because
 * that would turn the log into a way to forge requests.
 *
 * It hooks the Security class rather than patching CodeIgniter, so the
 * framework stays exactly as shipped.
 */

if ( ! function_exists('adomin_csrf_describe'))
{
	/**
	 * Classify the CSRF failure without leaking either secret.
	 *
	 * @return string e.g. "no token in POST, cookie present"
	 */
	function adomin_csrf_describe()
	{
		$cfg_token = 'csrf_test_name';
		$cfg_cookie = 'csrf_cookie_name';

		// Read the real names from config when it is affordable to do so: the
		// pre-flight check runs before CodeIgniter has loaded anything, and the
		// two names must be right at that moment or the report is worthless.
		if (function_exists('adomin_csrf_config_names'))
		{
			list($cfg_token, $cfg_cookie) = adomin_csrf_config_names();
		}

		if (function_exists('config_item'))
		{
			$token = config_item('csrf_token_name');
			$cookie = config_item('csrf_cookie_name');
			if (is_string($token) && $token !== '')
			{
				$cfg_token = $token;
			}
			if (is_string($cookie) && $cookie !== '')
			{
				$cfg_cookie = $cookie;
			}
		}

		$posted = isset($_POST[$cfg_token]) ? $_POST[$cfg_token] : NULL;
		$cooked = isset($_COOKIE[$cfg_cookie]) ? $_COOKIE[$cfg_cookie] : NULL;

		$have_post = is_string($posted) && $posted !== '';
		$have_cookie = is_string($cooked) && $cooked !== '';

		if ( ! $have_post && ! $have_cookie)
		{
			return 'no token in the POST body and no ' . $cfg_cookie . ' cookie';
		}
		if ( ! $have_post)
		{
			return 'cookie ' . $cfg_cookie . ' present but the POST body carried no ' . $cfg_token;
		}
		if ( ! $have_cookie)
		{
			return 'token in the POST body (' . strlen($posted) . ' chars) but no ' . $cfg_cookie . ' cookie - the browser did not send it back';
		}
		if ($posted === $cooked)
		{
			// A pass, but recorded anyway in case this is called for another reason.
			return 'token and cookie match (' . strlen($posted) . ' chars)';
		}

		return 'token and cookie DISAGREE (post ' . strlen($posted) . ' chars starting '
			. substr($posted, 0, 6) . ', cookie ' . strlen($cooked) . ' chars starting '
			. substr($cooked, 0, 6) . ')';
	}
}

if ( ! function_exists('adomin_csrf_report'))
{
	/**
	 * Write the classification where the owner can read it: the SAPI error log,
	 * which reaches Render -> Logs, and the breadcrumb trail.
	 */
	function adomin_csrf_report()
	{
		$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '?';
		$uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '?';

		error_log(sprintf(
			'Adomin CSRF rejected %s %s | %s',
			$method,
			$uri,
			adomin_csrf_describe()
		));

		if (function_exists('adomin_trace'))
		{
			adomin_trace('csrf: rejected', adomin_csrf_describe());
		}
	}
}

if ( ! function_exists('adomin_csrf_config_names'))
{
	/**
	 * The two CSRF names, read from application/config/config.php without
	 * booting the framework. Cheap (one small file, POSTs only) and it means the
	 * report can run before CodeIgniter exists.
	 *
	 * @return array array(token_name, cookie_name)
	 */
	function adomin_csrf_config_names()
	{
		$token = 'csrf_test_name';
		$cookie = 'csrf_cookie_name';

		$file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
		$text = @file_get_contents($file);
		if (is_string($text) && $text !== '')
		{
			if (preg_match("/csrf_token_name'\]\s*=\s*'([^']+)'/", $text, $m))
			{
				$token = $m[1];
			}
			if (preg_match("/csrf_cookie_name'\]\s*=\s*'([^']+)'/", $text, $m))
			{
				$cookie = $m[1];
			}
		}

		return array($token, $cookie);
	}
}

if ( ! function_exists('adomin_csrf_preflight'))
{
	/**
	 * Record the CSRF state of every POST before the framework can act on it.
	 *
	 * This is the one place that cannot be skipped by an early exit(): it runs
	 * from index.php before CodeIgniter loads, so a submission that is about to
	 * be rejected with a 403 still leaves its state in the log first. A shutdown
	 * handler cannot promise that - it only runs if the process reaches
	 * shutdown - and that is exactly the blind spot that made this failure take
	 * so long to explain.
	 */
	function adomin_csrf_preflight()
	{
		$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : '';
		if ($method !== 'POST')
		{
			return FALSE;
		}

		$state = adomin_csrf_describe();
		$uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '?';

		// Keep the pre-rejection truth. CI_Security::csrf_verify() unsets the
		// token from $_POST before it calls csrf_show_error(), so a report taken
		// at that later moment would claim "no token" for a request that plainly
		// carried one. This snapshot is what the rejection should quote.
		$GLOBALS['adomin_csrf_state'] = $state;

		error_log('Adomin CSRF preflight | POST ' . $uri . ' | ' . $state);

		if (function_exists('adomin_trace'))
		{
			adomin_trace('csrf preflight', $state);
		}

		return $state;
	}
}

if ( ! function_exists('adomin_csrf_hook'))
{
	/**
	 * Kept for callers that want a post-hoc report after the framework loaded:
	 * if the response ended as a 403 on a POST, write the classification again
	 * next to the rejection. Harmless when the pre-flight line already exists.
	 */
	function adomin_csrf_hook()
	{
		static $done = FALSE;
		if ($done)
		{
			return FALSE;
		}
		$done = TRUE;

		register_shutdown_function(function () {
			$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : '';
			if ($method !== 'POST' || ! function_exists('http_response_code'))
			{
				return;
			}
			if ((int) http_response_code() === 403)
			{
				adomin_csrf_report();
			}
		});

		return TRUE;
	}
}
