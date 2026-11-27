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
	 * Works from any controller: the settings model when it is loaded, and the
	 * public model's own value-store reader otherwise. That matters because the
	 * public share link and the PDF route never load the settings model, and a
	 * document that quietly falls back to default positions would contradict
	 * the pane that set them.
	 *
	 * @param	object	$CI		controller instance, if not the current one
	 * @param	int		$userId		account to read for; the public pages know it
	 * @return	array
	 */
	function adomin_invoice_layout($CI = NULL, $userId = NULL)
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
			'sections'    => 'header,items,totals,signature,remarks',
			'placement'   => '',
			'edge'        => 'none',
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
			'opt_invSections'    => 'sections',
			'opt_invPlacement'   => 'placement',
			'opt_invEdge'        => 'edge',
		);

		$layout = $defaults;

		if ( ! function_exists('get_instance'))
		{
			return $layout;
		}

		$CI = $CI === NULL ? get_instance() : $CI;

		$read = NULL;
		if (isset($CI->SettingsModel) && is_object($CI->SettingsModel)
			&& method_exists($CI->SettingsModel, 'getValueStores'))
		{
			$read = function ($key) use ($CI) {
				return $CI->SettingsModel->getValueStores($key);
			};
		}
		elseif ($userId !== NULL && isset($CI->PublicModel) && is_object($CI->PublicModel)
			&& method_exists($CI->PublicModel, 'getOneValueStore'))
		{
			$read = function ($key) use ($CI, $userId) {
				return $CI->PublicModel->getOneValueStore($key, (int) $userId);
			};
		}

		if ($read === NULL)
		{
			return $layout;
		}

		foreach ($keys as $key => $name)
		{
			$value = $read($key);
			if ($value !== NULL && $value !== '')
			{
				$layout[$name] = $value;
			}
		}

		return $layout;
	}
}

if ( ! function_exists('adomin_invoice_sections'))
{
	/**
	 * The document's blocks, in the order the account put them.
	 *
	 * Stored as a comma list so the value survives a round trip through the
	 * value store. Anything repeated, unknown or missing is dropped or appended:
	 * a corrupt value must produce a complete document, never a half one.
	 *
	 * @param	array	$layout
	 * @return	array	names in render order, always all of them
	 */
	function adomin_invoice_sections($layout)
	{
		$known = array('header', 'items', 'totals', 'signature', 'remarks');

		$stored = isset($layout['sections']) ? (string) $layout['sections'] : '';
		$order = array();
		foreach (explode(',', $stored) as $name)
		{
			$name = trim($name);
			if (in_array($name, $known, TRUE) && ! in_array($name, $order, TRUE))
			{
				$order[] = $name;
			}
		}
		foreach ($known as $name)
		{
			if ( ! in_array($name, $order, TRUE))
			{
				$order[] = $name;
			}
		}

		return $order;
	}
}

if ( ! function_exists('adomin_invoice_section_label'))
{
	/**
	 * @param	string	$name
	 * @return	string
	 */
	function adomin_invoice_section_label($name)
	{
		$labels = array(
			'header' => 'Company and client',
			'items' => 'Items',
			'totals' => 'Totals',
			'signature' => 'Signature and cipher',
			'remarks' => 'Remarks and message',
		);

		return isset($labels[$name]) ? $labels[$name] : $name;
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

if ( ! function_exists('adomin_invoice_components'))
{
	/**
	 * The pieces of the letterhead a canvas can place freely.
	 *
	 * These are the parts that used to be locked into one arrangement: the mark,
	 * the firm's name, its address details and the box holding the document's
	 * number and dates. Splitting them means a layout can put the number where a
	 * printer would have put it instead of wherever the flow happened to land it.
	 *
	 * Key => label, in the order the canvas lists them.
	 *
	 * @return	array
	 */
	function adomin_invoice_components()
	{
		return array(
			'logo'    => 'Logo',
			'firm'    => 'Company name',
			'address' => 'Company details',
			'docno'   => 'Number and dates',
		);
	}
}

if ( ! function_exists('adomin_invoice_placement_key'))
{
	/**
	 * Width and height of the page the canvas draws on, in points.
	 * A4 at 72dpi. Positions are stored as a fraction of this box, so the same
	 * layout lands in the same place whatever the screen or the PDF engine is
	 * using, and nothing has to round.
	 *
	 * @return	array	[width, height]
	 */
	function adomin_invoice_page()
	{
		return array(595, 842);
	}
}

if ( ! function_exists('adomin_invoice_placements'))
{
	/**
	 * Where each component sits on the page.
	 *
	 * Stored as one value-store row: "logo=x,y,w,h,size,face;firm=...". A
	 * fraction-based, comma-separated string is not elegant, but it survives the
	 * round trip through the settings table that every other layout value uses,
	 * and it degrades safely - anything missing or unreadable falls back to the
	 * component's designed position below, so a corrupt value still prints a
	 * complete letterhead.
	 *
	 * The defaults reproduce the flow layout the templates had before, so an
	 * account that never opens the canvas sees no change.
	 *
	 * @param	array	$layout
	 * @return	array	key => [x, y, w, h, size, face]  (x/y/w/h are 0..1)
	 */
	function adomin_invoice_placements($layout)
	{
		$defaults = array(
			//        x      y     w     h    size  face
			'logo'    => array(0.055, 0.045, 0.20, 0.075, 0, ''),
			'firm'    => array(0.055, 0.128, 0.55, 0.030, 15, ''),
			'address' => array(0.055, 0.168, 0.55, 0.090, 9, ''),
			'docno'   => array(0.620, 0.045, 0.325, 0.110, 9, ''),
		);

		$stored = isset($layout['placement']) ? (string) $layout['placement'] : '';
		if (trim($stored) === '')
		{
			return $defaults;
		}

		$out = $defaults;

		foreach (explode(';', $stored) as $chunk)
		{
			$parts = explode('=', $chunk, 2);
			if (count($parts) !== 2)
			{
				continue;
			}

			$key = trim($parts[0]);
			if ( ! isset($defaults[$key]))
			{
				continue;
			}

			$nums = explode(',', trim($parts[1]));
			if (count($nums) < 6)
			{
				continue;
			}

			$clean = array();
			for ($i = 0; $i < 4; $i++)
			{
				// a fraction outside the page is a corrupt value, not a design choice
				$value = (float) $nums[$i];
				$clean[$i] = $value < 0 ? 0 : ($value > 1 ? 1 : $value);
			}

			$size = (int) $nums[4];
			$clean[4] = $size < 6 ? $defaults[$key][4] : ($size > 48 ? 48 : $size);

			$face = trim($nums[5]);
			$clean[5] = $face === '' ? '' : substr(preg_replace('/[^A-Za-z0-9 \\-]/', '', $face), 0, 40);

			$out[$key] = $clean;
		}

		return $out;
	}
}

if ( ! function_exists('adomin_invoice_edge_designs'))
{
	/**
	 * Decorative bands for the printed document.
	 *
	 * The reference invoice carried a wave along the top and a diagonal
	 * watermark; both are cheap in CSS and survive the PDF engine, which is the
	 * constraint that rules out most "design" options. Each one is drawn with a
	 * gradient or a border and no images, so nothing has to be fetched at print
	 * time.
	 *
	 * @return	array	key => [label, description]
	 */
	function adomin_invoice_edge_designs()
	{
		return array(
			'none'   => array('Plain edge', 'No decoration.'),
			'wave'   => array('Wave', 'A soft double curve across the top.'),
			'rule'   => array('Accent rule', 'One straight line in the accent colour.'),
			'corner' => array('Corner wedge', 'A diagonal triangle in the top corner.'),
			'frame'  => array('Fine frame', 'A hairline border around the page.'),
			'chevron'=> array('Chevron band', 'A repeating zig-zag strip.'),
			'arc'    => array('Corner arc', 'A quarter circle sweeping from the corner.'),
		);
	}
}

if ( ! function_exists('adomin_invoice_edge_css'))
{
	/**
	 * The CSS for one edge design, given the accent colour.
	 *
	 * Returns a rule for a positioned element (the template supplies the
	 * element), except for 'frame', which needs to draw on the page itself and
	 * so also returns a rule for the document box.
	 *
	 * @param	string	$name
	 * @param	array	$accent	[bar, tint]
	 * @return	string	CSS, or '' for none
	 */
	function adomin_invoice_edge_css($name, $accent)
	{
		$bar = $accent[0];
		$tint = $accent[1];

		switch ($name)
		{
			case 'wave':
				return '.av-edge{height:54px;'
					. 'background:radial-gradient(circle at 25% 130%,' . $bar . ' 0 38%,transparent 39%),'
					. 'radial-gradient(circle at 78% 130%,' . $bar . ' 0 30%,transparent 31%),'
					. 'linear-gradient(' . $bar . ',' . $bar . ') 0 0/100% 9px no-repeat;}';

			case 'rule':
				return '.av-edge{height:6px;border-top:5px solid ' . $bar . ';}';

			case 'corner':
				return '.av-edge{height:70px;'
					. 'background:linear-gradient(225deg,' . $bar . ' 0 50%,transparent 50.5%);'
					. 'clip-path:polygon(0 0,100% 0,0 100%);}';

			case 'chevron':
				return '.av-edge{height:16px;'
					. 'background:repeating-linear-gradient(135deg,' . $bar . ' 0 8px,transparent 8px 16px),'
					. 'repeating-linear-gradient(45deg,' . $bar . ' 0 8px,transparent 8px 16px);'
					. 'opacity:.9;}';

			case 'arc':
				return '.av-edge{height:74px;'
					. 'background:radial-gradient(circle at 0 0,' . $bar . ' 0 68px,' . $tint . ' 69px 76px,transparent 77px);}';

			case 'frame':
				return '.av-doc-edge{outline:1px solid ' . $bar . ';outline-offset:-14px;}';

			default:
				return '';
		}
	}
}

if ( ! function_exists('adomin_invoice_positioned_letterhead'))
{
	/**
	 * The letterhead, with each component placed where the canvas put it.
	 *
	 * Absolute positioning against a page-sized box, with x/y/width/height as
	 * fractions of that box, is the only arrangement that means the same thing
	 * on a canvas, in a browser print and inside the PDF engine. Tables and
	 * floats each broke in one of those three, and the PDF engine is the one that
	 * matters most.
	 *
	 * The caller supplies the markup for each piece because only the template
	 * knows what a document number looks like in its own design; this function
	 * owns the geometry and nothing else.
	 *
	 * @param	array	$layout		from adomin_invoice_layout()
	 * @param	array	$pieces		key => html for any of logo / firm / address / docno
	 * @return	string	html
	 */
	function adomin_invoice_positioned_letterhead($layout, $pieces)
	{
		$places = adomin_invoice_placements($layout);
		$page = adomin_invoice_page();
		$components = adomin_invoice_components();

		$scale = 1;
		if (isset($layout['_scale']) && (float) $layout['_scale'] > 0)
		{
			$scale = (float) $layout['_scale'];
		}

		$html = '<div class="av-place" style="--av-pw:' . (int) $page[0] . 'pt;--av-ph:' . (int) $page[1] . 'pt;">';

		foreach ($components as $key => $label)
		{
			if ( ! isset($pieces[$key]) || trim((string) $pieces[$key]) === '')
			{
				continue;
			}

			list($x, $y, $w, $h, $size, $face) = $places[$key];

			/*
			 * Points, not percentages. Every stored number is a fraction of the
			 * page *width* (595pt for A4), and a percentage would be resolved
			 * against the containing box's own width or height depending on the
			 * property - so top:5% would land somewhere else entirely. Points
			 * mean the same thing on the canvas, in the browser and in dompdf.
			 */
			/*
			 * Text is given a minimum height, an image box an exact one. A text
			 * component whose font size or wording makes it taller than the box
			 * the canvas drew must still print in full - clipping a client's
			 * address to keep a box tidy would be the worst possible trade.
			 */
			$heightRule = $key === 'logo' ? 'height:' : 'min-height:';

			$style = 'left:' . round($x * $page[0], 2) . 'pt;'
				. 'top:' . round($y * $page[0], 2) . 'pt;'
				. 'width:' . round($w * $page[0], 2) . 'pt;'
				. $heightRule . round($h * $page[0], 2) . 'pt;';

			if ((int) $size > 0)
			{
				$style .= 'font-size:' . round($size * $scale, 2) . 'pt;';
			}

			if ($face !== '')
			{
				$style .= "font-family:'" . $face . "',Helvetica,Arial,sans-serif;";
			}

			/*
			 * data-avc-component is what the canvas hooks onto when it adopts the
			 * preview: one attribute is enough for it to find the element, read
			 * its geometry back and make it draggable, without either side
			 * needing a list of ids to stay in step.
			 */
			$html .= '<div class="av-place-item av-place-' . $key . '"'
				. ' data-avc-component="' . $key . '"'
				. ' style="' . $style . '">'
				. $pieces[$key]
				. '</div>';
		}

		return $html . '</div>';
	}
}

if ( ! function_exists('adomin_invoice_positioned_css'))
{
	/**
	 * The rules the positioned letterhead and the edge designs need.
	 *
	 * Dompdf understands absolute positioning against a positioned ancestor and
	 * percentage widths, but not flexbox or grid, so this deliberately uses the
	 * older model throughout.
	 *
	 * @param	array	$layout
	 * @return	string
	 */
	function adomin_invoice_positioned_css($layout)
	{
		$accent = adomin_invoice_accent($layout['accent']);
		$edge = isset($layout['edge']) ? (string) $layout['edge'] : 'none';
		$edgeCss = adomin_invoice_edge_css($edge, $accent);

		$page = adomin_invoice_page();

		return '<style>'
			// the band the components are placed in, sized in the same unit they use
			. '.av-place{position:relative;width:100%;height:' . round($page[0] * 0.28, 2) . 'pt;'
			. 'margin:0 0 10px;}'
			. '.av-place-item{position:absolute;box-sizing:border-box;}'
			// only the image box clips; text is allowed to grow past its box
			. '.av-place-logo{overflow:hidden;}'
			. '.av-place-logo img{max-width:100%;max-height:100%;width:auto;height:auto;display:block;object-fit:contain;}'
			. '.av-place-firm{font-weight:700;color:#111418;line-height:1.25;}'
			. '.av-place-address{color:#2a323c;line-height:1.45;}'
			. '.av-place-address p{margin:0 0 2px;}'
			. '.av-place-docno{text-align:right;color:#2a323c;}'
			. '.av-place-docno table{width:100%;border-collapse:collapse;}'
			. '.av-place-docno th{text-align:left;font-weight:400;color:#6a7480;padding:1px 8px 1px 0;white-space:nowrap;}'
			. '.av-place-docno td{text-align:right;color:#111418;padding:1px 0;}'
			. '.av-edge{position:relative;width:100%;}'
			. ($edgeCss !== '' ? $edgeCss : '')
			. '@media print{.av-place{break-inside:avoid;}}'
			. '</style>';
	}
}
