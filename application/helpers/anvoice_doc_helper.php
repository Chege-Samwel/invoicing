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
			'grid'        => '',
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
			'opt_invGrid'        => 'grid',
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

if ( ! function_exists('adomin_qr_svg'))
{
	/**
	 * The verification code for one document, as SVG markup.
	 *
	 * The document templates are views and cannot load a library themselves
	 * without reaching into the controller, so this resolves it the way the rest
	 * of the helper file does - through the instance - and caches it for the
	 * request. Anything going wrong returns '' and the document prints without a
	 * code, which is the only acceptable failure mode for something in a footer.
	 *
	 * @param	array	$invoice
	 * @param	int		$pixels
	 * @return	string
	 */
	function adomin_qr_svg($invoice, $pixels = 78)
	{
		if ( ! is_array($invoice) || empty($invoice['inv_number']))
		{
			return '';
		}

		if ( ! function_exists('get_instance'))
		{
			return '';
		}

		$CI = get_instance();
		if ( ! is_object($CI) || ! isset($CI->load))
		{
			return '';
		}

		try
		{
			if ( ! isset($CI->adominqr) || ! is_object($CI->adominqr))
			{
				$CI->load->library('AdominQr');
			}

			if ( ! isset($CI->adominqr) || ! is_object($CI->adominqr))
			{
				return '';
			}

			$url = $CI->adominqr->verifyUrl($invoice);
			if ($url === '')
			{
				return '';
			}

			return $CI->adominqr->svg($url, $pixels);
		}
		catch (Exception $e)
		{
			log_message('error', 'AdominQr: ' . $e->getMessage());
			return '';
		}
		catch (Throwable $e)
		{
			log_message('error', 'AdominQr: ' . $e->getMessage());
			return '';
		}
	}
}

if ( ! function_exists('adomin_invoice_doc_blocks'))
{
	/**
	 * The five document blocks, as closures.
	 *
	 * Three designs already render these, and three more were about to copy the
	 * same eighty lines a third, fourth and fifth time. Each copy is a place for
	 * the same bug to appear again - the "$origin is empty" and "QR only on one
	 * design" faults both came from exactly that. The designs still own their own
	 * markup and CSS; this owns the order and the wiring.
	 *
	 * @param	array	$invoice
	 * @param	array	$invLayout
	 * @param	array	$firmInfo
	 * @param	string	$origin	'original' or 'copy'
	 * @param	array	$opts	optional keys: money(closure), qrPixels(int)
	 * @return	array	name => closure
	 */
	function adomin_invoice_doc_blocks($invoice, $invLayout, $firmInfo, $origin = 'original', $opts = array())
	{
		$money = isset($opts['money']) ? $opts['money'] : function ($value) use ($invoice) {
			return $value . ' ' . $invoice['inv_currency'];
		};
		$qrPixels = isset($opts['qrPixels']) ? (int) $opts['qrPixels'] : 74;

		/* the document's own name: invoice, pro forma, debit or credit note */
		$typeLabel = $invoice['translation']['invoice'];
		if ($invoice['inv_type'] === 'prof')
		{
			$typeLabel = $invoice['translation']['pro_forma'];
		}
		elseif ($invoice['inv_type'] === 'debit')
		{
			$typeLabel = $invoice['translation']['debit_note'];
		}
		elseif ($invoice['inv_type'] === 'credit')
		{
			$typeLabel = $invoice['translation']['credit_note'];
		}

		/* who the document is addressed to: a company or a named person */
		$clientName = $invoice['client']['is_to_person'] == 0
			? $invoice['client']['recipient_name']
			: $invoice['client']['client_name'];
		$isCompany = $invoice['client']['is_to_person'] == 0;

		$logo = '';
		if (function_exists('adomin_firm_logo_src'))
		{
			$logo = adomin_firm_logo_src($invoice, $firmInfo);
		}
		elseif ( ! empty($invoice['firm']['image']) && ! empty($firmInfo['show_logo']))
		{
			$logo = base_url('attachments/companiesimages/' . $firmInfo['id'] . '/' . $invoice['firm']['image']);
		}

		return array(
			'header' => function () use ($invoice, $invLayout, $firmInfo, $logo, $typeLabel, $clientName, $isCompany) {
				?>
				<div class="adv-head">
					<div class="adv-head-firm">
						<?php if ($logo !== '') { ?>
							<img class="adv-logo" src="<?= html_escape($logo) ?>" alt="<?= html_escape($invoice['firm']['name']) ?>">
						<?php } ?>
						<p class="adv-firm-name"><?= html_escape($invoice['firm']['name']) ?></p>
						<div class="adv-firm-lines">
							<?php if ($invLayout['showAddress'] === '1' && ! empty($invoice['firm']['address'])) { ?>
								<p><?= html_escape($invoice['firm']['address']) ?></p>
							<?php } ?>
							<?php if ($invLayout['showContacts'] === '1' && ! empty($firmInfo['city'])) { ?>
								<p><?= html_escape($firmInfo['city']) ?></p>
							<?php } ?>
							<?php if ($invLayout['showFirmVat'] === '1' && ! empty($invoice['firm']['bulstat'])) { ?>
								<p><?= $invoice['translation']['bulstat'] ?>: <?= html_escape($invoice['firm']['bulstat']) ?></p>
							<?php } ?>
							<?php if ($invLayout['showFirmVat'] === '1' && ! empty($invoice['firm']['vat_number'])) { ?>
								<p>VAT: <?= html_escape($invoice['firm']['vat_number']) ?></p>
							<?php } ?>
						</div>
					</div>
					<div class="adv-head-doc">
						<p class="adv-doc-type"><?= html_escape($typeLabel) ?></p>
						<table class="adv-doc-meta">
							<tr>
								<th><?= $invoice['translation']['number'] ?></th>
								<td><?= html_escape($invoice['inv_number']) ?></td>
							</tr>
							<tr>
								<th><?= $invoice['translation']['date_of_issue'] ?></th>
								<td><?= date('d.m.Y', $invoice['date_create']) ?></td>
							</tr>
							<tr>
								<th><?= $invoice['translation']['a_date_of_a_tax_event'] ?></th>
								<td><?= date('d.m.Y', $invoice['date_tax_event']) ?></td>
							</tr>
							<?php if ($invLayout['currencyPos'] === 'header') { ?>
								<tr>
									<th><?= $invoice['translation']['currency'] ?? 'Currency' ?></th>
									<td><?= html_escape($invoice['inv_currency']) ?></td>
								</tr>
							<?php } ?>
						</table>
					</div>
				</div>
				<div class="adv-billto">
					<span class="adv-label"><?= $invoice['translation']['recipient'] ?></span>
					<p class="adv-billto-name"><?= html_escape($clientName) ?></p>
					<?php if (! empty($invoice['client']['client_address'])) { ?>
						<p><?= html_escape($invoice['client']['client_address']) ?></p>
					<?php } ?>
					<?php if ($isCompany && ! empty($invoice['client']['accountable_person'])) { ?>
						<p><?= $invoice['translation']['mol'] ?> <?= html_escape($invoice['client']['accountable_person']) ?></p>
					<?php } ?>
					<?php if (! empty($invoice['client']['client_bulstat'])) { ?>
						<p><?= $invoice['translation']['bulstat'] ?>: <?= html_escape($invoice['client']['client_bulstat']) ?></p>
					<?php } ?>
				</div>
				<?php
			},

			'items' => function () use ($invoice, $money) {
				?>
				<table class="adv-items">
					<thead>
						<tr>
							<th class="adv-col-num">#</th>
							<th><?= $invoice['translation']['products_name'] ?></th>
							<th class="adv-col-qty"><?= $invoice['translation']['quantity'] ?></th>
							<th class="adv-col-money"><?= $invoice['translation']['single_price'] ?></th>
							<th class="adv-col-money"><?= $invoice['translation']['value'] ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$row = 0;
						foreach ($invoice['items'] as $item)
						{
							/*
							 * A "Group:" line in the name becomes a section band. The
							 * counter only advances for real items, so numbering stays
							 * 1..n across groups.
							 */
							if (preg_match('/^\s*([^:]{2,40}):\s*$/', (string) $item['name'], $group))
							{
								?>
								<tr class="adv-group"><td colspan="5"><?= html_escape($group[1]) ?></td></tr>
								<?php
								continue;
							}
							$row++;
							?>
							<tr>
								<td class="adv-col-num"><?= $row ?></td>
								<td><?= html_escape($item['name']) ?></td>
								<td class="adv-col-qty"><?= html_escape($item['quantity']) ?> <?= html_escape($item['quantity_type']) ?></td>
								<td class="adv-col-money"><?= html_escape($money($item['single_price'])) ?></td>
								<td class="adv-col-money"><?= html_escape($money($item['total_price'])) ?></td>
							</tr>
							<?php
						}
						?>
					</tbody>
				</table>
				<?php
			},

			'totals' => function () use ($invoice, $invLayout, $money) {
				?>
				<div class="adv-totals">
					<div class="adv-notes">
						<?php if (! empty($invoice['payment_method'])) { ?>
							<p><span class="adv-label"><?= $invoice['translation']['payment_type'] ?></span>
								<?= html_escape($invoice['payment_method']) ?></p>
						<?php } ?>
						<?php if ($invoice['no_vat'] == 1 && ! empty($invoice['no_vat_reason'])) { ?>
							<p class="adv-muted"><?= html_escape($invoice['no_vat_reason']) ?></p>
						<?php } ?>
					</div>
					<table class="adv-sum">
						<tr>
							<td><?= $invoice['translation']['amount'] ?></td>
							<td><?= html_escape($money($invoice['invoice_amount'])) ?></td>
						</tr>
						<?php if ((float) $invoice['discount'] != 0) { ?>
							<tr>
								<td><?= $invoice['translation']['discount'] ?></td>
								<td><?= html_escape($invoice['discount'] . ' ' . $invoice['discount_type']) ?></td>
							</tr>
						<?php } ?>
						<tr>
							<td><?= $invoice['translation']['tax_base'] ?></td>
							<td><?= html_escape($money($invoice['tax_base'])) ?></td>
						</tr>
						<tr>
							<td>
								<?php if ($invoice['no_vat'] == 1) { ?>
									<?= $invoice['translation']['vat_charget'] ?>
								<?php } else { ?>
									<?= $invoice['translation']['percentage_vat'] ?> <?= $invoice['vat_percent'] ?>%
								<?php } ?>
							</td>
							<td><?= html_escape($money($invoice['vat_sum'])) ?></td>
						</tr>
						<tr class="adv-sum-total">
							<td><?= $invoice['translation']['everything'] ?></td>
							<td><?= html_escape($money($invoice['final_total'])) ?></td>
						</tr>
					</table>
				</div>
				<?php
			},

			'signature' => function () use ($invoice, $clientName) {
				?>
				<div class="adv-sign">
					<div class="adv-sign-left">
						<?php if (! empty($invoice['schiffer'])) { ?>
							<p><?= $invoice['translation']['schiffer'] ?>: <?= html_escape($invoice['schiffer']) ?></p>
						<?php } ?>
					</div>
					<div class="adv-sign-right">
						<span class="adv-sign-line"></span>
						<p class="adv-sign-cap"><?= $invoice['translation']['signature'] ?></p>
					</div>
				</div>
				<?php
			},

			'remarks' => function () use ($invoice, $invLayout, $qrPixels) {
				if ($invLayout['showRemarks'] === '1' && trim((string) $invoice['remarks']) !== '')
				{
					?>
					<div class="adv-remarks">
						<span class="adv-label"><?= $invoice['translation']['remarks'] ?></span>
						<p><?= nl2br(html_escape($invoice['remarks'])) ?></p>
					</div>
					<?php
				}
				?>
				<div class="adv-foot">
					<?php if (trim((string) $invLayout['footerNote']) !== '') { ?>
						<p class="adv-foot-note"><?= nl2br(html_escape($invLayout['footerNote'])) ?></p>
					<?php } ?>
					<?php
					$qr = function_exists('adomin_qr_svg') ? adomin_qr_svg($invoice, $qrPixels) : '';
					if ($qr !== '')
					{
						?>
						<div class="av-doc-qr">
							<?= $qr ?>
							<span class="av-qr-cap"><?= lang('verify_scan') ?></span>
						</div>
						<?php
					}
					?>
				</div>
				<?php
			},
		);
	}
}

if ( ! function_exists('adomin_invoice_render'))
{
	/**
	 * Render a document from the shared blocks, in the user's chosen order.
	 *
	 * @param	array	$invoice
	 * @param	array	$invLayout
	 * @param	array	$firmInfo
	 * @param	string	$origin
	 * @param	array	$opts
	 * @return	void
	 */
	function adomin_invoice_render($invoice, $invLayout, $firmInfo, $origin = 'original', $opts = array())
	{
		/*
		 * One page, drawn on a grid, rather than a column of blocks.
		 *
		 * The old version walked the sections and printed each one in flow, so
		 * the document was as tall as its content and the preview had no page to
		 * respect. Everything now lands in a band of a fixed A4 sheet - see
		 * adomin_invoice_page_body() - which is what makes the preview look like
		 * the page it will print on.
		 */
		echo adomin_invoice_page_body($invoice, $invLayout, $firmInfo, $origin, $opts);
	}
}

if ( ! function_exists('adomin_invoice_grid'))
{
	/**
	 * The page as a grid of columns and rows, in points.
	 *
	 * This is the difference between a document and a pile of blocks.
	 *
	 * The letterhead components (logo, firm, address, docno) have always been
	 * positioned absolutely, but everything after them - client, items, totals,
	 * signature, remarks - was emitted as ordinary flow. Flow means each block is
	 * as tall as its content, so an invoice with thirty items pushed the totals
	 * off the bottom of A4, and the preview on screen could not show a page
	 * because there was no page: only a column of divs as tall as it needed to
	 * be. That is why the preview looked disorganised and why it never matched
	 * what the PDF did.
	 *
	 * A grid fixes it without taking the design away from the person editing it.
	 * Every block gets a band of the page - a top and a bottom in fractions of
	 * the page - and anything inside that band is the block's own business. A
	 * block that outgrows its band is clipped in the preview so the person can
	 * see, immediately, that their item list is too long for the page they chose
	 * - which is a fact they need, not a fact to hide. The PDF keeps every row;
	 * this only governs the page the screen draws.
	 *
	 * The bands are stored with the layout as opt_invGrid, in the same
	 * "key=x,y,w,h" form the letterhead already uses, so nothing new has to be
	 * added to the settings table and a missing value falls back to the design
	 * below.
	 *
	 * @return	array	key => [x, y, w, h] as fractions of the page
	 */
	function adomin_invoice_grid_default()
	{
		/*
		 * Reading down an A4 page: the letterhead occupies the top fifth, the
		 * parties sit under it, then the item table takes the middle of the page,
		 * and the totals, signature and remarks stack up from the bottom. The
		 * gaps are deliberate - a document that runs blocks edge to edge reads
		 * as cramped no matter how good the type is.
		 */
		return array(
			//          x      y      w      h
			'header'    => array(0.055, 0.245, 0.890, 0.075),  // company and client
			'items'     => array(0.055, 0.330, 0.890, 0.360),  // the table
			'totals'    => array(0.520, 0.700, 0.425, 0.130),  // sums, right aligned
			'signature' => array(0.055, 0.845, 0.890, 0.070),
			'remarks'   => array(0.055, 0.915, 0.620, 0.060),
		);
	}
}

if ( ! function_exists('adomin_invoice_grid'))
{
	/**
	 * The grid in use: the design above, with whatever the account has stored
	 * laid over it.
	 *
	 * @param	array	$layout
	 * @return	array	key => [x, y, w, h]
	 */
	function adomin_invoice_grid($layout)
	{
		$out = adomin_invoice_grid_default();

		$stored = isset($layout['grid']) ? (string) $layout['grid'] : '';
		if (trim($stored) === '')
		{
			return $out;
		}

		foreach (explode(';', $stored) as $chunk)
		{
			$parts = explode('=', $chunk, 2);
			if (count($parts) !== 2)
			{
				continue;
			}

			$key = trim($parts[0]);
			if ( ! isset($out[$key]))
			{
				continue;
			}

			$numbers = explode(',', $parts[1]);
			if (count($numbers) < 4)
			{
				continue;
			}

			$values = array();
			foreach (array_slice($numbers, 0, 4) as $number)
			{
				$values[] = min(1, max(0, (float) $number));
			}

			/*
			 * A band that is inverted or empty would draw a block with no area,
			 * which is indistinguishable from a bug to whoever is looking at it.
			 * Anything that does not describe a real box is ignored and the
			 * design is used instead.
			 */
			if ($values[2] <= 0 || $values[3] <= 0)
			{
				continue;
			}

			$out[$key] = $values;
		}

		return $out;
	}
}

if ( ! function_exists('adomin_invoice_page_body'))
{
	/**
	 * Draw every block of the document inside its band on a single page.
	 *
	 * The bands are absolute, so the blocks cannot push each other down the page
	 * - which is the whole point: the page is fixed and the content has to fit
	 * it, rather than the content deciding how tall the page is.
	 *
	 * @param	array	$invoice
	 * @param	array	$invLayout
	 * @param	array	$firmInfo
	 * @param	string	$origin
	 * @param	array	$opts
	 * @return	string	html
	 */
	function adomin_invoice_page_body($invoice, $invLayout, $firmInfo, $origin = 'original', $opts = array())
	{
		$blocks = adomin_invoice_doc_blocks($invoice, $invLayout, $firmInfo, $origin, $opts);
		$grid = adomin_invoice_grid($invLayout);
		$page = adomin_invoice_page();
		$pw = (int) $page[0];

		$html = '<div class="adv-page" data-av-page="1" style="--av-pw:' . $pw . 'pt;--av-ph:' . (int) $page[1] . 'pt;">';

		foreach (adomin_invoice_sections($invLayout) as $name)
		{
			if ( ! isset($blocks[$name]) || ! isset($grid[$name]))
			{
				continue;
			}

			list($x, $y, $w, $h) = $grid[$name];

			/*
			 * The block is marked with the same data attribute the letterhead
			 * components use, so the editor can make it draggable and resizable
			 * with the code it already has. The difference is that a block is a
			 * region of the page rather than a line of text, so its box is the
			 * unit of meaning.
			 */
			$style = 'left:' . round($x * $pw, 2) . 'pt;'
				. 'top:' . round($y * $pw, 2) . 'pt;'
				. 'width:' . round($w * $pw, 2) . 'pt;'
				. 'height:' . round($h * $pw, 2) . 'pt;';

			$html .= '<div class="adv-band adv-band-' . $name . '"'
				. ' data-avc-block="' . $name . '"'
				. ' data-avc-label="' . html_escape(adomin_invoice_section_label($name)) . '"'
				. ' style="' . $style . '">';

			ob_start();
			$blocks[$name]();
			$html .= ob_get_clean();

			$html .= '</div>';
		}

		return $html . '</div>';
	}
}
