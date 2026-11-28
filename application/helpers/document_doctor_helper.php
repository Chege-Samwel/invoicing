<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Why is this document missing a block?
 *
 * The preview is a page like any other: it answers 200 and draws the sheet. If
 * the letterhead is absent from it, one of exactly five bands produced nothing -
 * and from the outside all five absences look identical. This builds the same
 * blocks the page builds, one buffer each, and reports what each one emitted.
 *
 * It runs inside the logged-in app, on the invoice the reader is already
 * looking at, so it needs no environment token: the session is the credential,
 * and the document is one they own. /render-doctor.php answers the same
 * question from outside; this answers it where the problem is seen.
 *
 * @param	array	$invoice
 * @param	array	$invLayout
 * @param	array	$firmInfo
 * @return	array
 */
if ( ! function_exists('adomin_doc_trim'))
{
	/**
	 * The first $length characters, with or without mbstring.
	 *
	 * The container ships mbstring (status.php reports it), but a fallback costs
	 * one line and this helper's whole job is to report rather than to throw.
	 * The first version called mb_substr unguarded and lost the entire report on
	 * a host without it - the failure it exists to explain, repeated.
	 *
	 * @param	string	$text
	 * @param	int	$length
	 * @return	string
	 */
	function adomin_doc_trim($text, $length = 120)
	{
		$text = (string) $text;
		if (function_exists('mb_substr'))
		{
			return mb_substr($text, 0, $length, 'UTF-8');
		}
		return substr($text, 0, $length);
	}
}

if ( ! function_exists('adomin_doc_band_report'))
{
	function adomin_doc_band_report($invoice, $invLayout, $firmInfo)
	{
		$report = array(
			'bands' => array(),
			'notices' => array(),
			'page_bytes' => 0,
			'snapshot' => array(),
			'template' => '',
			'template_found' => FALSE,
			'template_bytes' => 0,
			'template_error' => NULL,
		);

		/*
		 * The snapshot first. A band with nothing to draw is not a rendering
		 * fault at all - it is a document that never captured the firm or the
		 * client - and the two faults need opposite fixes.
		 */
		$report['snapshot'] = array(
			'firm row' => !empty($invoice['firm']) && is_array($invoice['firm']),
			'firm name' => isset($invoice['firm']['name']) ? $invoice['firm']['name'] : '',
			'client row' => !empty($invoice['client']) && is_array($invoice['client']),
			'client name' => isset($invoice['client']['client_name']) ? $invoice['client']['client_name'] : '',
			'translation row' => !empty($invoice['translation']) && is_array($invoice['translation']),
			'item rows' => isset($invoice['items']) && is_array($invoice['items']) ? count($invoice['items']) : 0,
		);

		$collect = function ($severity, $message, $file, $line) use (&$report) {
			if ( ! (error_reporting() & $severity))
			{
				return FALSE;
			}
			if (count($report['notices']) < 30)
			{
				$report['notices'][] = $message . ' — ' . basename($file) . ':' . $line;
			}
			return TRUE;
		};

		set_error_handler($collect);

		try
		{
			$blocks = adomin_invoice_doc_blocks($invoice, $invLayout, $firmInfo);
			foreach ($blocks as $name => $fn)
			{
				ob_start();
				$error = NULL;
				try
				{
					$fn();
				}
				catch (Throwable $e)
				{
					$error = $e->getMessage() . ' — ' . basename($e->getFile()) . ':' . $e->getLine();
				}
				$html = ob_get_clean();
				try
				{
					$text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
					$report['bands'][$name] = array(
						'html_bytes' => strlen($html),
						'text_chars' => strlen($text),
						'opens' => $text === '' ? '' : adomin_doc_trim($text, 120),
						'error' => $error,
					);
				}
				catch (Throwable $e)
				{
					$report['bands'][$name] = array(
						'html_bytes' => strlen($html),
						'text_chars' => 0,
						'opens' => '',
						'error' => $error !== NULL ? $error : $e->getMessage(),
					);
				}
			}

			$body = adomin_invoice_page_body($invoice, $invLayout, $firmInfo);
			$report['page_bytes'] = strlen($body);
			$report['page_bands'] = preg_match_all('/class="adv-band adv-band-(\w+)"/', $body, $m)
				? array_values(array_unique($m[1]))
				: array();
		}
		catch (Throwable $e)
		{
			$report['fatal'] = $e->getMessage() . ' — ' . basename($e->getFile()) . ':' . $e->getLine();
		}

		restore_error_handler();

		/*
		 * And the template the page actually includes - a different path from
		 * the block builder, and the one the reported page takes.
		 */
		if (function_exists('get_instance') && is_object(get_instance()) && isset(get_instance()->adomindocs))
		{
			$docs = get_instance()->adomindocs;
			$template = $docs->invoiceTemplate(NULL, isset($invoice['for_user']) ? (int) $invoice['for_user'] : NULL);
			$report['template'] = $template;
			$file = $docs->invoiceTemplateFile($template);
			$report['template_found'] = ($file !== NULL && is_file($file));
			if ($report['template_found'])
			{
				ob_start();
				try
				{
					include $file;
				}
				catch (Throwable $e)
				{
					$report['template_error'] = $e->getMessage() . ' — ' . basename($e->getFile()) . ':' . $e->getLine();
				}
				$report['template_bytes'] = strlen(ob_get_clean());
			}
		}

		return $report;
	}
}

/**
 * One-line verdict for the report above.
 *
 * @param	array	$report
 * @return	string
 */
if ( ! function_exists('adomin_doc_band_verdict'))
{
	function adomin_doc_band_verdict($report)
	{
		if ( ! empty($report['fatal']))
		{
			return 'The page body threw: ' . $report['fatal'];
		}

		$empty = array();
		foreach ($report['bands'] as $name => $band)
		{
			if ($band['text_chars'] === 0)
			{
				$empty[] = $name;
			}
		}
		if ($empty)
		{
			return 'These blocks printed nothing: ' . implode(', ', $empty) . '.';
		}
		if ( ! empty($report['notices']))
		{
			return count($report['notices']) . ' PHP notice(s) raised — each one costs a block inside the page buffer.';
		}
		if ($report['page_bytes'] === 0)
		{
			return 'The blocks all drew, but the page body is empty.';
		}

		return 'Every block drew and the page body is ' . $report['page_bytes'] . ' bytes. '
			. 'If the screen still shows less, the difference is the stylesheet, not the document.';
	}
}
