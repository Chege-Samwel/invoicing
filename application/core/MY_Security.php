<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CSRF that explains itself.
 *
 * CI_Security::csrf_show_error() answers a rejected POST with
 * "The action you have requested is not allowed." and a 403 page. That page is
 * a dead end: it does not say which of the four possible causes fired, it does
 * not offer a way back, and - because the token is regenerated on the way out -
 * pressing Back and submitting the same cached form simply fails again.
 *
 * This override keeps the rejection (the POST is still not trusted) but:
 *
 *   1. records the classification in the error log and the breadcrumb trail,
 *      so a hosted failure can be read from the log instead of guessed at;
 *   2. sends the browser back to the page it submitted from, so the next render
 *      carries a fresh token and the user can retry one click later.
 *
 * A redirect cannot loop: the rejected request was a POST and the redirect is a
 * GET, and a GET is never CSRF-checked. If there is no same-origin referer to
 * return to (a direct API call, a curl test) it falls back to a plain 403 with
 * an explanatory body.
 */
class MY_Security extends CI_Security
{
	/**
	 * Where to send the browser after a rejection, or NULL to just 403.
	 *
	 * @return string|null
	 */
	protected function adomin_recover_url()
	{
		if (headers_sent())
		{
			return NULL;
		}

		$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
		if ($host === '')
		{
			return NULL;
		}

		$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
		$target = NULL;

		if ($referer !== '')
		{
			$parts = parse_url($referer);
			// Only ever bounce back to this same host: a referer is an input and
			// must not be able to turn a failed login into an open redirect.
			if (is_array($parts) && isset($parts['host']) && strcasecmp($parts['host'], $host) === 0)
			{
				$path = isset($parts['path']) ? $parts['path'] : '/';
				$query = isset($parts['query']) ? $parts['query'] : '';

				// Drop any earlier marker so repeat failures cannot grow the URL.
				$query = trim(preg_replace('/(^|&)csrf_error=[^&]*/', '', $query), '&');

				$target = $path . ($query === '' ? '' : '?' . $query);
			}
		}

		if ($target === NULL)
		{
			// No usable referer: return to the URL that was posted to, minus the
			// POST body. Same page, fresh token.
			$uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : FALSE;
			if ($uri === FALSE || $uri === '')
			{
				return NULL;
			}
			$target = $uri;
		}

		$sep = (strpos($target, '?') === FALSE) ? '?' : '&';

		return $target . $sep . 'csrf_error=1';
	}

	/**
	 * Show CSRF Error
	 *
	 * @return	void
	 */
	public function csrf_show_error()
	{
		$state = 'unknown';

		// Prefer the snapshot taken before CI unset the token from $_POST.
		if ( ! empty($GLOBALS['adomin_csrf_state']))
		{
			$state = $GLOBALS['adomin_csrf_state'];
		}
		elseif (function_exists('adomin_csrf_describe'))
		{
			$state = adomin_csrf_describe();
		}

		if (function_exists('adomin_trace'))
		{
			adomin_trace('csrf rejected by CI_Security', $state);
		}

		error_log('Adomin CSRF rejected | POST ' . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '?') . ' | ' . $state);

		$recover = $this->adomin_recover_url();

		if ($recover !== NULL)
		{
			// 303 turns the POST into a plain GET at the destination, which is
			// what the browser needs in order to render a fresh form.
			header('Location: ' . $recover, TRUE, 303);
			header('X-Adomin-Csrf: rejected');
			exit;
		}

		header('X-Adomin-Csrf: rejected');
		show_error('The action you have requested is not allowed.', 403);
	}
}
