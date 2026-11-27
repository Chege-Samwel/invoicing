<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Guarantees that every POST form the server sends out carries its CSRF token.
 *
 * Why this exists: CSRF protection is on, so CodeIgniter rejects any POST whose
 * body does not repeat the token held in the csrf_cookie_name cookie. Every
 * layout also ships a small footer script that adds the missing input on the
 * client - but that recovery only works if the script loads, the DOM is parsed
 * and the meta tag was rendered. Roughly thirty authenticated templates (admin
 * login, clients, invoices, items, settings, store, warranty, plans) print no
 * token of their own, so on those pages the token exists only if JavaScript ran.
 * A cold Render start, a stale asset cache, a blocked script or a CSP rule is
 * enough to lose it - and the user then sees "The action you have requested is
 * not allowed." for a form they filled in correctly.
 *
 * Doing it here, once, at the last moment before the body is sent, removes the
 * dependency on the client entirely and covers templates added later. Forms
 * that already print a token are left exactly as they are.
 */
class MY_Output extends CI_Output
{
	/**
	 * Display Output
	 *
	 * @param	string	$output
	 * @return	void
	 */
	public function _display($output = '')
	{
		if ($output === '')
		{
			$output =& $this->final_output;
		}

		if (is_string($output) && $output !== '')
		{
			$output = $this->adomin_csrf_inject($output);
		}

		parent::_display($output);
	}

	// --------------------------------------------------------------------

	/**
	 * Add the CSRF input to every POST form that is missing it.
	 *
	 * @param	string	$html
	 * @return	string
	 */
	protected function adomin_csrf_inject($html)
	{
		if (strpos($html, '<form') === FALSE && stripos($html, '<form') === FALSE)
		{
			return $html;
		}

		if ( ! config_item('csrf_protection') || ! class_exists('CI_Controller', FALSE))
		{
			return $html;
		}

		// A cached page would also cache one visitor's token and serve it to the
		// next one. Output caching is off here; if it is ever switched on, stop
		// injecting rather than hand out a shared token.
		if ( ! empty($this->cache_expiration))
		{
			error_log('Adomin CSRF: output caching is enabled - skipping token injection');
			return $html;
		}

		$CI =& get_instance();
		if ( ! isset($CI->security) || ! is_object($CI->security))
		{
			return $html;
		}

		$name = $CI->security->get_csrf_token_name();
		$hash = $CI->security->get_csrf_hash();

		if ($name === '' || $hash === '' || $hash === NULL)
		{
			return $html;
		}

		$name = html_escape($name);
		$field = '<input type="hidden" name="' . $name . '" value="' . html_escape($hash) . '">';
		$needle = 'name="' . $name . '"';

		if ( ! preg_match_all('/<form\b[^>]*>/i', $html, $matches, PREG_OFFSET_CAPTURE))
		{
			return $html;
		}

		$out = '';
		$cursor = 0;
		$added = 0;

		foreach ($matches[0] as $match)
		{
			$tag = $match[0];
			$pos = $match[1];

			// Look at the form element this tag opens: if it already carries the
			// token, leave it untouched.
			$end = stripos($html, '</form>', $pos);
			if ($end === FALSE)
			{
				$end = strlen($html);
			}

			$block = substr($html, $pos, $end - $pos);
			if (stripos($block, $needle) !== FALSE)
			{
				continue;
			}

			$insert_at = $pos + strlen($tag);
			$out .= substr($html, $cursor, $insert_at - $cursor) . $field;
			$cursor = $insert_at;
			$added++;
		}

		if ($added === 0)
		{
			return $html;
		}

		$out .= substr($html, $cursor);

		if (function_exists('adomin_trace'))
		{
			adomin_trace('csrf token added to forms', $added);
		}

		return $out;
	}
}
