<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Invoice layout.
 *
 * Which design a document uses and where its parts sit are two different
 * questions. The design lives in views/invoices/templates/*.php; this helper
 * answers the second one, so both the settings preview and the printed document
 * are built from the same rules and cannot disagree.
 *
 * Every value has a default that matches what the templates did before this
 * existed, so an account that has never opened the settings pane keeps the
 * document it already has.
 */

if ( ! function_exists('adomin_invoice_layout'))
{
	/**
	 * Read the layout from the value store, with defaults.
	 *
	 * @param	object	$CI	controller instance (has ->SettingsModel after settings load)
	 * @return	array
	 */
	function adomin_invoice_layout($CI = NULL)
	{
		$defaults = array(
			'logoPos'     => 'left',
			'addressPos'  => 'below',
			'showLogo'    => '1',
			'showAddress' => '1',
			'showContacts'=> '1',
			'showFirmVat' => '1',
			'showRemarks' => '1',
			'footerNote'  => '',
			'accent'      => 'blue',
			'currencyPos' => 'totals',
			'template'    => 'creative',
		);

		$keys = array(
			'opt_invLogoPos'     => 'logoPos',
			'opt_invAddressPos'  => 'addressPos',
			'opt_invShowLogo'    => 'showLogo',
			'opt_invShowAddress' => 'showAddress',
			'opt_invShowContacts'=> 'showContacts',
			'opt_invShowFirmVat' => 'showFirmVat',
			'opt_invShowRemarks' => 'showRemarks',
			'opt_invFooterNote'  => 'footerNote',
			'opt_invAccent'      => 'accent',
			'opt_invCurrencyPos' => 'currencyPos',
			'opt_invTemplate'    => 'template',
		);

		$layout = $defaults;

		if (function_exists('get_instance'))
		{
			$CI = $CI === NULL ? get_instance() : $CI;
			if (isset($CI->SettingsModel) && is_object($CI->SettingsModel)
				&& method_exists($CI->SettingsModel, 'getValueStores'))
			{
				foreach ($keys as $key => $name)
				{
					$value = $CI->SettingsModel->getValueStores($key);
					if ($value !== NULL && $value !== '')
					{
						$layout[$name] = $value;
					}
				}
			}
		}

		return $layout;
	}
}

if ( ! function_exists('adomin_invoice_accent'))
{
	/**
	 * The one colour the document is allowed to introduce.
	 *
	 * A palette rather than a colour picker: a free hex field on a document that
	 * goes to a customer produces unreadable combinations, and the templates
	 * carry their own ink and rule colours.
	 *
	 * @param	string	$name
	 * @return	array	[bar, tint]
	 */
	function adomin_invoice_accent($name = 'blue')
	{
		$palette = array(
			'blue'  => array('#0b6bcb', '#eef4fb'),
			'slate' => array('#33475b', '#eef1f4'),
			'green' => array('#157347', '#e9f6ee'),
			'mono'  => array('#111418', '#f1f2f4'),
		);

		return isset($palette[$name]) ? $palette[$name] : $palette['blue'];
	}
}

if ( ! function_exists('adomin_invoice_letterhead'))
{
	/**
	 * The letterhead: logo, company block, and where they sit.
	 *
	 * @param	array	$layout	from adomin_invoice_layout()
	 * @param	array	$firm	name / bulstat / vat_number / address / city / accountable_person
	 * @param	string	$logo	src, or '' for none
	 * @param	array	$labels	translated labels for the firm fields
	 * @return	string	html
	 */
	function adomin_invoice_letterhead($layout, $firm, $logo = '', $labels = array())
	{
		$showLogo = ($layout['showLogo'] === '1' && $logo !== '' && $layout['logoPos'] !== 'hide');
		$showAddress = ($layout['showAddress'] === '1');

		$label = function ($key, $fallback) use ($labels) {
			return isset($labels[$key]) && $labels[$key] !== '' ? $labels[$key] : $fallback;
		};

		$logoHtml = $showLogo
			? '<div class="av-doc-logo"><img src="' . html_escape($logo) . '" alt=""></div>'
			: '';

		$lines = array();
		$lines[] = '<p class="av-doc-firm">' . html_escape($firm['name']) . '</p>';

		if ($showAddress)
		{
			if ( ! empty($firm['address']))
			{
				$lines[] = '<p>' . nl2br(html_escape($firm['address'])) . '</p>';
			}
			if ( ! empty($firm['city']))
			{
				$lines[] = '<p>' . html_escape($firm['city']) . '</p>';
			}
		}

		if ( ! empty($firm['bulstat']))
		{
			$lines[] = '<p><span class="av-doc-k">' . html_escape($label('bulstat', 'Reg.')) . '</span> '
				. html_escape($firm['bulstat']) . '</p>';
		}

		if ($layout['showFirmVat'] === '1' && ! empty($firm['is_vat_registered']) && ! empty($firm['vat_number']))
		{
			$lines[] = '<p><span class="av-doc-k">' . html_escape($label('vat_number', 'VAT')) . '</span> '
				. html_escape($firm['vat_number']) . '</p>';
		}

		if ($layout['showContacts'] === '1' && ! empty($firm['accountable_person']))
		{
			$lines[] = '<p><span class="av-doc-k">' . html_escape($label('mol', 'For')) . '</span> '
				. html_escape($firm['accountable_person']) . '</p>';
		}

		$infoHtml = '<div class="av-doc-firminfo">' . implode('', $lines) . '</div>';

		// where the two blocks sit relative to each other
		$pos = $layout['logoPos'];
		if ($layout['addressPos'] === 'side' && $showLogo)
		{
			return '<div class="av-doc-head av-doc-head-side">' . $logoHtml . $infoHtml . '</div>';
		}
		if ($pos === 'hide' || ! $showLogo)
		{
			return '<div class="av-doc-head av-doc-head-noimg">' . $infoHtml . '</div>';
		}

		return '<div class="av-doc-head av-doc-head-' . html_escape($pos) . '">'
			. $logoHtml . $infoHtml . '</div>';
	}
}

if ( ! function_exists('adomin_invoice_layout_css'))
{
	/**
	 * The stylesheet the layout needs, printed once per document (and once per
	 * preview). Kept in one place so a printed invoice and the settings preview
	 * are the same object, not two drawings of one.
	 *
	 * @param	array	$layout
	 * @return	string
	 */
	function adomin_invoice_layout_css($layout)
	{
		$accent = adomin_invoice_accent($layout['accent']);

		return '<style>
.av-doc-head { display:flex; flex-wrap:wrap; align-items:flex-start; gap:18px; margin-bottom:18px; }
.av-doc-head-left   { flex-direction:row; }
.av-doc-head-center { flex-direction:column; align-items:center; text-align:center; }
.av-doc-head-right  { flex-direction:row-reverse; text-align:right; }
.av-doc-head-side   { justify-content:space-between; }
.av-doc-head-noimg  { display:block; }
.av-doc-logo img { max-width:190px; max-height:78px; width:auto; height:auto; display:block; }
.av-doc-firminfo { min-width:0; }
.av-doc-firminfo p { margin:0 0 2px; font-size:12px; line-height:1.45; color:#2a323c; }
.av-doc-firminfo p.av-doc-firm { font-size:16px; font-weight:700; color:#111418; margin-bottom:5px; }
.av-doc-k { color:#6a7480; font-size:11px; text-transform:uppercase; letter-spacing:.06em; margin-right:4px; }
.av-doc-accent { border-top:3px solid ' . $accent[0] . '; margin:0 0 14px; }
.av-doc-note { margin-top:18px; padding:10px 12px; background:' . $accent[1] . ';
               border-left:3px solid ' . $accent[0] . '; font-size:11.5px; line-height:1.5; color:#2a323c; }
.av-doc-currency { font-size:11.5px; color:#6a7480; margin:0 0 8px; }
.av-doc-currency b { color:#111418; }
/*
 * The document stylesheet still carries the old float rules for the previous
 * firm block. Inside the letterhead they would float the logo away from the
 * text, so they are reset here rather than edited out of a file other designs
 * also use.
 */
.av-doc-head .firm-logo,
.av-doc-head .my-firm-info { float:none; width:auto; max-width:none; text-align:inherit; }
.av-doc-head .my-firm-info p { margin-bottom:2px; font-size:12px; color:#2a323c; }
@media print { .av-doc-head { break-inside:avoid; } }
</style>';
	}
}
