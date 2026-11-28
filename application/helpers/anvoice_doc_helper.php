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
			// The KRA PIN is its own switch: a business may print its VAT number
			// and keep its PIN off the paper, or the other way round. Folding
			// both into one toggle is what made "hide the VAT number" also hide
			// the PIN without saying so.
			'showFirmPin' => '1',
			// The beneficiary is the person at the client the invoice is
			// addressed to. Some businesses print it, some consider it the
			// client's own business who receives the mail - so it has a switch
			// of its own rather than riding on "contacts".
			'showBeneficiary' => '1',
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
			'opt_invShowFirmPin' => 'showFirmPin',
			'opt_invShowBeneficiary' => 'showBeneficiary',
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
	 * @param	string	$qr	the verification block, drawn to the right of the firm
	 * @return	string	html
	 */
	function adomin_invoice_letterhead($layout, $firm, $logo = '', $labels = array(), $qr = '')
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

		if ($layout['showFirmPin'] === '1' && ! empty($firm['bulstat']))
		{
			$lines[] = '<p><span class="av-doc-k">' . html_escape($label('bulstat', 'KRA PIN')) . '</span> '
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
			$block = '<div class="av-doc-head av-doc-head-side">' . $logoHtml . $infoHtml . '</div>';
		}
		elseif ($pos === 'hide' || ! $showLogo)
		{
			$block = '<div class="av-doc-head av-doc-head-noimg">' . $infoHtml . '</div>';
		}
		else
		{
			$block = '<div class="av-doc-head av-doc-head-' . html_escape($pos) . '">'
				. $logoHtml . $infoHtml . '</div>';
		}

		if ($qr === '')
		{
			return $block;
		}

		/*
		 * The code goes to the right of the company block, in the same row.
		 *
		 * A table rather than a float or flex: this markup is printed by dompdf,
		 * which supports neither, and floats inside a block collapse its height.
		 * The text-align rule on the wrapper centres the design variations that
		 * ask for it while the code keeps its own right edge.
		 */
		return '<table class="av-doc-headrow" role="presentation"><tr>'
			. '<td class="av-doc-headrow-firm">' . $block . '</td>'
			. '<td class="av-doc-headrow-qr">' . $qr . '</td>'
			. '</tr></table>';
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

/*
 * The page sheet.
 *
 * Repeated here, inline, because this helper is called by every path that draws
 * a document - the invoice view, the settings preview, the share page and the
 * PDF - and only the editor loaded the stylesheet that used to define it. With
 * no .adv-page and no .adv-band rules the bands were plain blocks: their
 * left/top offsets were inert, so the totals, signature and remarks stacked on
 * top of one another and the letterhead reflowed into whatever space was left.
 *
 * position:absolute needs a positioned ancestor, hence .adv-page first.
 */
.adv-page { position:relative; width:var(--av-pw,595pt); height:var(--av-ph,842pt);
            margin:0 auto; padding:0; background:#fff; box-sizing:border-box;
            overflow:hidden; font-size:9pt; color:#22282f; }
.adv-band { position:absolute; box-sizing:border-box; overflow:visible; }
.adv-band-items > * { max-height:100%; }
.adv-page-final .adv-band { overflow:hidden; }
/*
 * The bands are placed in points by the grid, so the sheet must not be scaled
 * by a parent transform in a print path: the editor does that deliberately on
 * its own canvas, not here.
 */
.adv-page .invoice-creative { padding:0; }
/*
 * The letterhead shares the grid inset. The bands below it are placed in
 * points from the sheet corner, so a flowed header would sit at x=0 and read
 * as a different document from the table underneath it.
 */
.adv-page .adv-header { position:absolute; left:5.5%; top:4.2%; width:89%; }
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

if ( ! function_exists('adomin_invoice_print_margins'))
{
	/**
	 * The margin the PDF renderer puts around our sheet, in millimetres.
	 *
	 * This mirrors the `margins` the invoice PDF path passes to AdominPdf
	 * (Invoiceview::pdf and Home::sharedInvoicePdf: 8 / 8 sides, 10 top,
	 * 16 bottom). It is declared once, here, so the sheet can be sized to the
	 * space that is actually printable instead of to the full A4 page.
	 *
	 * Why it matters: the sheet was sized to the whole page (842pt) while the
	 * renderer only had 842 - (10 + 16)mm = 768pt of printable height. Anything
	 * the grid placed below 768pt was pushed onto a second page - in practice
	 * the signature and the standing note, giving a one-line invoice a second
	 * sheet with nothing on it but the foot of the letter.
	 *
	 * @return	array	[left, right, top, bottom] in millimetres
	 */
	function adomin_invoice_print_margins()
	{
		return array(8, 8, 10, 16);
	}
}

if ( ! function_exists('adomin_invoice_printable_height'))
{
	/**
	 * How tall the sheet may be, in points, once the renderer's margins are off.
	 *
	 * @return	float
	 */
	function adomin_invoice_printable_height()
	{
		$page = adomin_invoice_page();
		$mm = adomin_invoice_print_margins();
		$ptPerMm = 72 / 25.4;
		return (float) $page[1] - ((float) $mm[2] + (float) $mm[3]) * $ptPerMm;
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
			 * sheet, and a percentage would be resolved against the containing
			 * box's own width or height depending on the property - so top:5%
			 * would land somewhere else entirely. Points mean the same thing on
			 * the canvas, in the browser and in dompdf.
			 *
			 * x and width are fractions of the page width; y and height are
			 * fractions of its height. Reading the vertical pair against the
			 * width pulled every letterhead component up the sheet and made it
			 * shorter than the one the editor drew.
			 */
			/*
			 * Text is given a minimum height, an image box an exact one. A text
			 * component whose font size or wording makes it taller than the box
			 * the canvas drew must still print in full - clipping a client's
			 * address to keep a box tidy would be the worst possible trade.
			 */
			$heightRule = $key === 'logo' ? 'height:' : 'min-height:';

			$style = 'left:' . round($x * $page[0], 2) . 'pt;'
				. 'top:' . round($y * $page[1], 2) . 'pt;'
				. 'width:' . round($w * $page[0], 2) . 'pt;'
				. $heightRule . round($h * $page[1], 2) . 'pt;';

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

if ( ! function_exists('adomin_qr_png'))
{
	/**
	 * The same verification code as a data: URI PNG.
	 *
	 * The SVG helper above is what the screen gets. This is what the PDF gets,
	 * and it is a different function rather than a flag because the two are
	 * rendered by different engines: a browser draws inline <svg> markup,
	 * dompdf does not - it only reads an image source, and an SVG source is
	 * handed to php-svg-lib, which parses XML. A PNG needs neither.
	 *
	 * Falls back to '' for the same reason the SVG does: a document must print
	 * without its check rather than not print at all.
	 *
	 * @param	array	$invoice
	 * @param	int		$pixels
	 * @return	string
	 */
	function adomin_qr_png($invoice, $pixels = 78)
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

			return $CI->adominqr->pngDataUri($url, $pixels);
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

if ( ! function_exists('adomin_invoice_qr_html'))
{
	/**
	 * The verification code as markup, in whichever form the reader's engine
	 * can actually draw.
	 *
	 * The PDF gets a PNG in an <img>: dompdf never draws inline <svg>, and an
	 * SVG image source sends it through php-svg-lib's XML parser, which a
	 * stripped host may not have. The browser gets the SVG, which it draws
	 * directly. Both encode the same verification URL.
	 *
	 * Returns '' when neither can be produced - a document must print without
	 * its check rather than not print at all.
	 *
	 * @param	array	$invoice
	 * @param	int		$pixels
	 * @return	string
	 */
	function adomin_invoice_qr_html($invoice, $pixels = 74)
	{
		$png = function_exists('adomin_qr_png') ? adomin_qr_png($invoice, $pixels) : '';
		if ($png !== '')
		{
			return '<img src="' . html_escape($png) . '" width="' . (int) $pixels . '"'
				. ' height="' . (int) $pixels . '" alt="" class="av-qr-img">';
		}

		return function_exists('adomin_qr_svg') ? adomin_qr_svg($invoice, $pixels) : '';
	}
}

if ( ! function_exists('adomin_invoice_qr_block'))
{
	/**
	 * The code plus its caption, wrapped in the block every design styles.
	 *
	 * One function so the three letterhead styles cannot drift into drawing it
	 * three different ways - which is how the code ended up in the footer of
	 * some designs and nowhere at all in others.
	 *
	 * @param	array	$invoice
	 * @param	int		$pixels
	 * @param	string	$extra	additional class names
	 * @return	string
	 */
	function adomin_invoice_qr_block($invoice, $pixels = 74, $extra = '')
	{
		$html = adomin_invoice_qr_html($invoice, $pixels);
		if ($html === '')
		{
			return '';
		}

		return '<div class="av-doc-qr' . ($extra !== '' ? ' ' . $extra : '') . '">'
			. $html
			. '<span class="av-qr-cap">' . html_escape(lang('verify_scan')) . '</span>'
			. '</div>';
	}
}

if ( ! function_exists('adomin_invoice_parties'))
{
	/**
	 * The two parties and the verification code, as one row.
	 *
	 * Sender on the left, receiver in the middle, QR on the right. This is the
	 * thing every template got wrong in the same way: the company and the
	 * customer were stacked in a single column, each field on its own line, and
	 * the code was either inside that column or missing from the PDF entirely.
	 *
	 * Returns markup only. Address lines are joined into one line because a
	 * four-line block out of two facts is what made the top of the page look
	 * like a form rather than a letterhead.
	 *
	 * A <table> rather than flex or float: dompdf supports neither.
	 *
	 * @param	array	$invoice
	 * @param	array	$layout
	 * @param	string	$logo	src, or '' when there is none
	 * @param	int		$qrPixels
	 * @return	string
	 */
	function adomin_invoice_parties($invoice, $layout, $logo = '', $qrPixels = 74)
	{
		$labels = isset($invoice['translation']) && is_array($invoice['translation']) ? $invoice['translation'] : array();
		$label = function ($key, $fallback) use ($labels) {
			return (isset($labels[$key]) && $labels[$key] !== '') ? $labels[$key] : $fallback;
		};

		/*
		 * One line out of address + city (+ country). Empty parts are skipped
		 * rather than leaving a trailing comma, and newlines inside a stored
		 * address become the same separator.
		 */
		$joinLine = function ($parts) {
			$out = array();
			foreach ($parts as $part) {
				$part = trim((string) $part);
				if ($part === '') {
					continue;
				}
				$out[] = rtrim($part, ',');
			}
			$line = implode(', ', $out);
			return preg_replace('/\s*\n\s*/', ', ', $line);
		};

		$firm = isset($invoice['firm']) && is_array($invoice['firm']) ? $invoice['firm'] : array();
		$client = isset($invoice['client']) && is_array($invoice['client']) ? $invoice['client'] : array();

		$showLogo = ($layout['showLogo'] === '1' && $logo !== '');
		$showAddress = ($layout['showAddress'] === '1');

		// ---- sender
		$firmAddress = $showAddress
			? $joinLine(array(
				isset($firm['address']) ? $firm['address'] : '',
				isset($firm['city']) ? $firm['city'] : '',
			))
			: '';

		// ---- receiver
		$clientAddress = $joinLine(array(
			isset($client['client_address']) ? $client['client_address'] : '',
			isset($client['client_city']) ? $client['client_city'] : '',
			isset($client['client_country']) ? $client['client_country'] : '',
		));

		$h = '<div class="row-my-firm">';
		$h .= '<table class="av-parties" role="presentation"><tr>';

		// sender cell
		$h .= '<td class="av-party av-party-firm">';
		if ($showLogo)
		{
			$h .= '<div class="av-doc-logo"><img src="' . html_escape($logo) . '" alt=""></div>';
		}
		$h .= '<p class="av-party-name">' . html_escape(isset($firm['name']) ? $firm['name'] : '') . '</p>';
		if ($firmAddress !== '')
		{
			$h .= '<p class="av-party-line">' . $firmAddress . '</p>';
		}
		if ($layout['showFirmPin'] === '1' && ! empty($firm['bulstat']))
		{
			$h .= '<p class="av-party-line"><span class="av-doc-k">'
				. html_escape($label('bulstat', 'KRA PIN')) . '</span> '
				. html_escape($firm['bulstat']) . '</p>';
		}
		if ($layout['showFirmVat'] === '1' && ! empty($firm['is_vat_registered']) && ! empty($firm['vat_number']))
		{
			$h .= '<p class="av-party-line"><span class="av-doc-k">'
				. html_escape($label('vat_number', 'VAT')) . '</span> '
				. html_escape($firm['vat_number']) . '</p>';
		}
		if ($layout['showContacts'] === '1' && ! empty($firm['accountable_person']))
		{
			$h .= '<p class="av-party-line"><span class="av-doc-k">'
				. html_escape($label('mol', 'For')) . '</span> '
				. html_escape($firm['accountable_person']) . '</p>';
		}
		$h .= '</td>';

		// receiver cell
		$h .= '<td class="av-party av-party-client">';
		$h .= '<p class="av-party-kicker">' . html_escape($label('recipient', 'Bill to')) . '</p>';
		$h .= '<p class="av-party-name">' . html_escape(isset($client['client_name']) ? $client['client_name'] : '') . '</p>';
		if ($clientAddress !== '')
		{
			$h .= '<p class="av-party-line">' . $clientAddress . '</p>';
		}
		$isPerson = (isset($client['is_to_person']) && (int) $client['is_to_person'] === 1);
		if ( ! $isPerson && ! empty($client['client_bulstat']))
		{
			$h .= '<p class="av-party-line"><span class="av-doc-k">'
				. html_escape($label('bulstat', 'KRA PIN')) . '</span> '
				. html_escape($client['client_bulstat']) . '</p>';
		}
		elseif ($isPerson && ! empty($client['client_ident_num']))
		{
			$h .= '<p class="av-party-line">' . html_escape($client['client_ident_num']) . '</p>';
		}
		if ( ! empty($client['client_vat_registered']) && ! empty($client['vat_number']))
		{
			$h .= '<p class="av-party-line"><span class="av-doc-k">'
				. html_escape($label('vat_number', 'VAT')) . '</span> '
				. html_escape($client['vat_number']) . '</p>';
		}
		if ( ! $isPerson && ! empty($client['accountable_person']))
		{
			$h .= '<p class="av-party-line"><span class="av-doc-k">'
				. html_escape($label('mol', 'For')) . '</span> '
				. html_escape($client['accountable_person']) . '</p>';
		}
		$h .= '</td>';

		// the code, once
		$h .= '<td class="av-party av-party-qr">' . adomin_invoice_qr_block($invoice, $qrPixels) . '</td>';

		return $h . '</tr></table></div>';
	}
}

if ( ! function_exists('adomin_invoice_meta'))
{
	/**
	 * What the document is, and the numbers that identify it, on one band.
	 *
	 * The old markup was a five-row two-column table with an "amount" row that
	 * repeated the subtotal, floated beside the customer block. Reading it
	 * meant crossing six lines to find three facts.
	 *
	 * @param	array	$invoice
	 * @return	string
	 */
	function adomin_invoice_meta($invoice)
	{
		$labels = isset($invoice['translation']) && is_array($invoice['translation']) ? $invoice['translation'] : array();
		$label = function ($key, $fallback) use ($labels) {
			return (isset($labels[$key]) && $labels[$key] !== '') ? $labels[$key] : $fallback;
		};

		$type = '';
		switch (isset($invoice['inv_type']) ? $invoice['inv_type'] : '')
		{
			case 'tax_inv': $type = $label('invoice', 'Invoice'); break;
			case 'prof':    $type = $label('pro_forma', 'Pro forma'); break;
			case 'debit':   $type = $label('debit_note', 'Debit note'); break;
			case 'credit':  $type = $label('credit_note', 'Credit note'); break;
			default:        $type = $label('invoice', 'Invoice');
		}
		$isNote = in_array(isset($invoice['inv_type']) ? $invoice['inv_type'] : '', array('debit', 'credit'), TRUE);

		$items = '';
		if ($isNote)
		{
			$items .= '<span class="av-meta-item"><span class="av-doc-k">'
				. html_escape($label('to_an_invoice', 'To invoice')) . '</span> <b>'
				. html_escape(isset($invoice['to_inv_number']) ? $invoice['to_inv_number'] : '') . '</b></span>';
			$items .= '<span class="av-meta-item"><span class="av-doc-k">'
				. html_escape($label('from_date', 'From')) . '</span> '
				. date('d.m.Y', isset($invoice['to_inv_date']) ? (int) $invoice['to_inv_date'] : time()) . '</span>';
		}
		$items .= '<span class="av-meta-item"><span class="av-doc-k">'
			. html_escape($label('number', 'Number')) . '</span> <b>'
			. html_escape(isset($invoice['inv_number']) ? $invoice['inv_number'] : '') . '</b></span>';
		$items .= '<span class="av-meta-item"><span class="av-doc-k">'
			. html_escape($label('date_of_issue', 'Date of issue')) . '</span> '
			. date('d.m.Y', isset($invoice['date_create']) ? (int) $invoice['date_create'] : time()) . '</span>';
		$items .= '<span class="av-meta-item"><span class="av-doc-k">'
			. html_escape($label('a_date_of_a_tax_event', 'Tax event')) . '</span> '
			. date('d.m.Y', isset($invoice['date_tax_event']) ? (int) $invoice['date_tax_event'] : time()) . '</span>';

		return '<table class="av-meta" role="presentation"><tr>'
			. '<td class="av-meta-type"><span class="invoice-type">' . html_escape($type) . '</span></td>'
			. '<td class="av-meta-fields">' . $items . '</td>'
			. '</tr></table>';
	}
}

if ( ! function_exists('adomin_normalise_labels'))
{
	/**
	 * Rename the document labels that were renamed.
	 *
	 * The wording printed on an invoice is not a lang() key: it is a column on
	 * invoices_translations, copied from invoices_languages when the document
	 * was issued. So an invoice created before "bulstat" became KRA PIN keeps
	 * printing "Bulstat" however the language files are edited - and so does a
	 * customer's own saved translation, which this app must not rewrite because
	 * a snapshot is supposed to stay as sent.
	 *
	 * Normalising on read fixes every route at once: the invoice view, the
	 * preview, the share link and the PDF all get their translation row through
	 * a model, and all three models call this. An empty replacement means the
	 * label is no longer printed at all.
	 *
	 * @param	array|null	$translation
	 * @return	array
	 */
	function adomin_normalise_labels($translation)
	{
		if ( ! is_array($translation))
		{
			return array();
		}

		$renames = array(
			'bulstat'  => 'KRA PIN:',
			'schiffer' => '',
			'original' => '',
		);

		foreach ($renames as $key => $replacement)
		{
			if (array_key_exists($key, $translation))
			{
				$translation[$key] = $replacement;
			}
		}

		// the shipped seed misspelt two labels; they are on real documents
		$spellings = array(
			'Remakrs'         => 'Remarks',
			'Reveice invoice' => 'Received invoice',
		);

		foreach ($translation as $key => $value)
		{
			if (is_string($value) && isset($spellings[$value]))
			{
				$translation[$key] = $spellings[$value];
			}
		}

		return $translation;
	}
}

if ( ! function_exists('adomin_invoice_fields'))
{
	/**
	 * The document, with every optional field present.
	 *
	 * A document is a snapshot, and snapshots are partial in practice: an
	 * imported invoice has no firm row, a deleted contact leaves no client row,
	 * an older row was written before a column existed. Every block then reads
	 * those keys directly, and a read of an absent key is a PHP notice - which
	 * inside adomin_invoice_page_body()'s output buffer costs the whole band.
	 * That is how a letterhead disappears and leaves the item table on its own.
	 *
	 * Guarding each read at the point of use was the first attempt, and it is
	 * the wrong shape: there are forty of them across the renderer and three
	 * templates that build their own bands, and every new field is another one
	 * to remember. The set of fields is known here instead, so it is filled in
	 * once, on the way in, and the blocks can go on reading plainly.
	 *
	 * Values are only defaulted, never replaced: a field that is present is
	 * returned exactly as it arrived, so nothing a document already says about
	 * itself changes here.
	 *
	 * @param	array	$invoice
	 * @return	array
	 */
	function adomin_invoice_fields($invoice)
	{
		if ( ! is_array($invoice))
		{
			$invoice = array();
		}

		$defaults = array(
			'id' => 0,
			'for_user' => 0,
			'for_company' => 0,
			'inv_type' => 'tax_inv',
			'inv_number' => '',
			'inv_currency' => '',
			'exchange_rate' => 1,
			'date_create' => 0,
			'date_tax_event' => 0,
			'maturity_date' => 0,
			'have_maturity_date' => 0,
			'invoice_amount' => '0',
			'tax_base' => '0',
			'vat_sum' => '0',
			'vat_percent' => '0',
			'discount' => '0',
			'discount_type' => 'percent',
			'final_total' => '0',
			'no_vat' => 0,
			'no_vat_reason' => '',
			'payment_method' => '',
			'payment_status' => '',
			'remarks' => '',
			'composed' => '',
			'schiffer' => '',
			'status' => 'issued',
			'cash_accounting' => 0,
			'to_inv_number' => '',
			'to_inv_date' => 0,
			'tax_event_date' => 0,
			'items' => array(),
			'client' => array(),
			'firm' => array(),
			'translation' => array(),
		);

		$invoice = array_merge($defaults, $invoice);

		/* the three that are read as arrays must actually be arrays */
		foreach (array('client', 'firm', 'translation') as $key)
		{
			if ( ! is_array($invoice[$key]))
			{
				$invoice[$key] = array();
			}
		}
		if ( ! is_array($invoice['items']))
		{
			$invoice['items'] = array();
		}

		return $invoice;
	}
}

if ( ! function_exists('adomin_invoice_doc_blocks'))
{
	/**
	 * The five document blocks, as closures.
	 *
	 * Three designs already render these, and three more were about to copy the
	 * same eighty lines a third, fourth and fifth time. Each copy is a place for
	 * the same bug to appear again - the "QR only on one design" fault came from
	 * exactly that. The designs still own their own markup and CSS; this owns the
	 * order and the wiring.
	 *
	 * There is no $origin parameter: documents are not issued as one of a pair
	 * any more, so there is no original/copy marker to pass down.
	 *
	 * @param	array	$invoice
	 * @param	array	$invLayout
	 * @param	array	$firmInfo
	 * @param	array	$opts	optional keys: money(closure), qrPixels(int)
	 * @return	array	name => closure
	 */
	function adomin_invoice_doc_blocks($invoice, $invLayout, $firmInfo, $opts = array())
	{
		/* a partial snapshot must not cost a band - see adomin_invoice_fields() */
		$invoice = adomin_invoice_fields($invoice);

		/*
		 * The currency is printed once, at the head of the figures - not on
		 * every line.
		 *
		 * This default used to append the code to each amount, so a five-line
		 * invoice said KES ten times: once per unit price, once per line total,
		 * once per subtotal, once per tax row and once on the total. Repeating
		 * a currency the document has already declared is noise, and it costs
		 * the column width that the figures need for the numbers themselves.
		 *
		 * A caller may still supply its own formatter through $opts['money'];
		 * that is how a design that deliberately prints the code per row keeps
		 * doing so.
		 */
		$money = isset($opts['money']) ? $opts['money'] : function ($value) {
			return $value;
		};
		$qrPixels = isset($opts['qrPixels']) ? (int) $opts['qrPixels'] : 74;

		/*
		 * Which item rows this page prints.
		 *
		 * The sheet is a fixed height, so a long invoice cannot simply flow onto
		 * another one. The caller decides the slice - adomin_invoice_pages()
		 * splits the rows at the number a page holds - and passes it here with
		 * the number already printed before it, so the numbering continues
		 * across pages instead of restarting at 1 on every sheet.
		 */
		$rows = isset($opts['rows']) && is_array($opts['rows'])
			? array_values($opts['rows'])
			: adomin_invoice_item_rows($invoice);
		$rowOffset = isset($opts['rowOffset']) ? (int) $opts['rowOffset'] : 0;

		/*
		 * An address is one line, whatever shape it was stored in.
		 *
		 * The parts of an address live in separate columns - street, city,
		 * country - and the client block printed only the street, so a client
		 * in Mombasa was filed as "12 Moi Avenue" with no town at all. Where
		 * the street itself had line breaks (everyone types an address into a
		 * textarea) those newlines came out as separate lines in the PDF,
		 * pushing the block down the page. Joining with a comma and collapsing
		 * the breaks gives one line per party, which is what the layout
		 * reserves room for.
		 */
		$oneLine = function ($parts) {
			$out = array();
			foreach ($parts as $part) {
				$part = trim((string) $part);
				if ($part === '') {
					continue;
				}
				$out[] = rtrim($part, ',');
			}
			return preg_replace('/\s*\n\s*/', ', ', implode(', ', $out));
		};

		/*
		 * The verification code, built once and drawn in the letterhead.
		 *
		 * The PDF gets the PNG, the browser gets the SVG - dompdf draws inline
		 * <svg> nowhere and only reads an image source, and an SVG source is
		 * parsed as XML by php-svg-lib, which a stripped host may not have. If
		 * neither can be produced this stays '' and the letterhead simply has no
		 * code; a document must never fail because a check cannot be drawn.
		 */
		$qrHtml = adomin_invoice_qr_html($invoice, $qrPixels);

		/*
		 * These keys are read, not guaranteed.
		 *
		 * A document with no translation row - an old one, an imported one, one
		 * whose snapshot was never written - used to make this block raise on a
		 * missing index. The band is rendered inside ob_start(), so the notice
		 * went to the log, the output buffer was discarded, and the page printed
		 * every band except this one: an invoice with an item table and no
		 * letterhead above it, which reads as "the template is missing". The
		 * party helper below has always been defensive about the same data; this
		 * is the same guard for the band.
		 */
		$labels = isset($invoice['translation']) && is_array($invoice['translation'])
			? $invoice['translation']
			: array();
		$label = function ($key, $fallback) use ($labels) {
			return (isset($labels[$key]) && $labels[$key] !== '') ? $labels[$key] : $fallback;
		};

		/* the document's own name: invoice, pro forma, debit or credit note */
		$typeLabel = $label('invoice', 'Invoice');
		if (isset($invoice['inv_type']) && $invoice['inv_type'] === 'prof')
		{
			$typeLabel = $label('pro_forma', 'Pro forma invoice');
		}
		elseif (isset($invoice['inv_type']) && $invoice['inv_type'] === 'debit')
		{
			$typeLabel = $label('debit_note', 'Debit note');
		}
		elseif (isset($invoice['inv_type']) && $invoice['inv_type'] === 'credit')
		{
			$typeLabel = $label('credit_note', 'Credit note');
		}

		/* who the document is addressed to: a company or a named person */
		$client = isset($invoice['client']) && is_array($invoice['client']) ? $invoice['client'] : array();
		/* the sender's own snapshot, for the same reason */
		$firm = isset($invoice['firm']) && is_array($invoice['firm']) ? $invoice['firm'] : array();
		$isCompany = ! empty($client['is_to_person']) ? FALSE : TRUE;
		$clientName = $isCompany
			? (isset($client['recipient_name']) ? $client['recipient_name'] : '')
			: (isset($client['client_name']) ? $client['client_name'] : '');

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
			'header' => function () use ($invoice, $invLayout, $firmInfo, $logo, $typeLabel, $clientName, $isCompany, $qrHtml, $oneLine, $label, $firm, $client) {
				?>
				<?php
				/*
				 * The letterhead is a two-cell table, not two floated divs.
				 *
				 * dompdf supports neither flexbox nor grid, and floats in a block
				 * container collapse the parent's height, which drops the band
				 * border the Modern design draws under the header. A table puts
				 * the company details and the code on one line in both engines -
				 * that is the whole requirement - and needs nothing from the PDF
				 * renderer that it does not already do well.
				 */
				?>
				<table class="adv-head" role="presentation">
					<tr>
						<td class="adv-head-firm">
						<?php if ($logo !== '') { ?>
							<img class="adv-logo" src="<?= html_escape($logo) ?>" alt="<?= html_escape(isset($firm['name']) ? $firm['name'] : '') ?>">
						<?php } ?>
						<?php
							/*
							 * The sender's name, from whichever column carries it.
							 * invoices_firms calls it `name`; the session copy used by the
							 * logged-in preview can carry `firm_name`. An empty letterhead
							 * is the one thing a reader cannot explain away.
							 */
							$advFirmName = '';
							foreach (array('name', 'firm_name', 'company_name') as $advKey) {
								if (!empty($firm[$advKey])) { $advFirmName = $firm[$advKey]; break; }
							}
							if ($advFirmName === '' && !empty($firmInfo['name'])) { $advFirmName = $firmInfo['name']; }
							?>
						<p class="adv-firm-name"><?= html_escape($advFirmName) ?></p>
						<div class="adv-firm-lines">
							<?php
							/* street and town are one line, not two - see $oneLine */
							$firmLine = $oneLine(array(
								$invLayout['showAddress'] === '1' ? ((isset($firm['address']) ? $firm['address'] : '')) : '',
								$invLayout['showContacts'] === '1' ? (isset($firmInfo['city']) ? $firmInfo['city'] : '') : '',
							));
							if ($firmLine !== '') { ?>
								<p><?= html_escape($firmLine) ?></p>
							<?php } ?>
							<?php if ($invLayout['showFirmPin'] === '1' && ! empty($firm['bulstat'])) { ?>
								<p><?= $label('bulstat', 'KRA PIN') ?> <?= html_escape($firm['bulstat']) ?></p>
							<?php } ?>
							<?php if ($invLayout['showFirmVat'] === '1' && ! empty($firm['vat_number'])) { ?>
								<p><?= $label('vat_number', 'VAT') ?> <?= html_escape($firm['vat_number']) ?></p>
							<?php } ?>
						</div>
						</td>
						<td class="adv-head-doc">
						<?php
						/*
						 * The verification code goes first in the right-hand cell, so
						 * it lands in the top right corner of the sheet, level with
						 * the company name on the left. Putting it under the number
						 * and dates pushed it down the page until it sat opposite
						 * the client block instead, which is not where a reader
						 * looks for it. It used to live in the footer - the one
						 * place a reader looks last - and the PDF never printed it
						 * there at all.
						 */
						if ($qrHtml !== '')
						{
							// one builder for the block, so the header, the share page
							// and any future surface cannot drift apart
							echo adomin_invoice_qr_block($invoice, 74, 'av-doc-qr-head');
						}
						?>
						<p class="adv-doc-type"><?= html_escape($typeLabel) ?></p>
						<table class="adv-doc-meta">
							<tr>
								<th><?= $label('number', 'Number') ?></th>
								<td><?= html_escape($invoice['inv_number']) ?></td>
							</tr>
							<tr>
								<th><?= $label('date_of_issue', 'Date of issue') ?></th>
								<td><?= date('d.m.Y', $invoice['date_create']) ?></td>
							</tr>
							<tr>
								<th><?= $label('a_date_of_a_tax_event', 'Date of tax event') ?></th>
								<td><?= date('d.m.Y', $invoice['date_tax_event']) ?></td>
							</tr>
							<?php if ($invLayout['currencyPos'] === 'header') { ?>
								<tr>
									<th><?= $label('currency', 'Currency') ?></th>
									<td><?= html_escape($invoice['inv_currency']) ?></td>
								</tr>
							<?php } ?>
						</table>						</td>
					</tr>
				</table>
				<div class="adv-billto">
					<span class="adv-label"><?= $label('recipient', 'Recipient') ?></span>
					<p class="adv-billto-name"><?= html_escape($clientName) ?></p>
					<?php
					/*
					 * The client's town and country were being dropped: only
					 * client_address was printed. Three columns, one line.
					 */
					$clientLine = $oneLine(array(
						(isset($client['client_address']) ? $client['client_address'] : ''),
						(isset($client['client_city']) ? $client['client_city'] : ''),
						(isset($client['client_country']) ? $client['client_country'] : ''),
					));
					if ($clientLine !== '') { ?>
						<p><?= html_escape($clientLine) ?></p>
					<?php } ?>
					<?php if ($isCompany && ! empty($client['accountable_person'])) { ?>
						<p><?= $label('mol', 'MOL') ?> <?= html_escape($client['accountable_person']) ?></p>
					<?php } ?>
					<?php if (! empty($client['client_bulstat'])) { ?>
						<p><?= $label('bulstat', 'KRA PIN') ?> <?= html_escape($client['client_bulstat']) ?></p>
					<?php } ?>
				</div>
				<?php
			},

			'items' => function () use ($invoice, $money, $invLayout, $rows, $rowOffset, $opts, $label) {
				?>
				<?php
				/*
				 * Where the currency is stated. One line above the table, so
				 * every figure under it is read in that currency - see the
				 * $money default for why it is not repeated per row. When the
				 * layout puts the currency in the header instead, this stays
				 * quiet rather than saying it twice.
				 */
				if ($invLayout['currencyPos'] !== 'header' && ! empty($invoice['inv_currency'])) { ?>
					<p class="adv-currency"><?= html_escape($label('currency', 'Currency')) ?>: <b><?= html_escape($invoice['inv_currency']) ?></b></p>
				<?php } ?>
				<table class="adv-items">
					<thead>
						<tr>
							<th class="adv-col-num">#</th>
							<th><?= $label('products_name', 'Item') ?></th>
							<th class="adv-col-qty"><?= $label('quantity', 'Qty') ?></th>
							<th class="adv-col-money"><?= $label('single_price', 'Unit price') ?></th>
							<th class="adv-col-money"><?= $label('value', 'Amount') ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$row = (int) $rowOffset;
						foreach ($rows as $item)
						{
							/*
							 * A "Group:" line in the name becomes a section band. The
							 * counter only advances for real items, so numbering stays
							 * 1..n across groups.
							 */
							/*
							 * Items come from a table whose columns have changed over time:
							 * older rows have `item` where newer ones have `name`, and a
							 * written-off row can have no price at all. Reading the key
							 * directly made the row a notice and left the cell blank, or -
							 * worse, since this runs inside the page buffer - lost the whole
							 * band. The renderer accepts either shape.
							 */
							$itemName = isset($item['name']) && $item['name'] !== ''
								? $item['name']
								: (isset($item['item']) ? $item['item'] : '');
							$itemQtyType = isset($item['quantity_type']) ? $item['quantity_type'] : '';
							$itemTotal = isset($item['total_price'])
								? $item['total_price']
								: (isset($item['value']) ? $item['value'] : '0');
							$itemPrice = isset($item['single_price']) ? $item['single_price'] : '0';

							if (preg_match('/^\s*([^:]{2,40}):\s*$/', (string) $itemName, $group))
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
								<td><?= html_escape($itemName) ?></td>
								<td class="adv-col-qty"><?= html_escape(isset($item['quantity']) ? $item['quantity'] : '') ?> <?= html_escape($itemQtyType) ?></td>
								<td class="adv-col-money"><?= html_escape($money($itemPrice)) ?></td>
								<td class="adv-col-money"><?= html_escape($money($itemTotal)) ?></td>
							</tr>
							<?php
						}
						?>
					</tbody>
				</table>
				<?php
				/*
				 * Say that the table carries on. Without this the reader has a
				 * sheet that ends mid-invoice and no way of telling whether the
				 * rest exists - the same failure as a missing page.
				 */
				if ( ! empty($opts['continued'])) { ?>
					<p class="adv-continued"><?= html_escape(
						str_replace(
							array('{page}', '{pages}'),
							array((int) $opts['pageNo'] + 1, (int) $opts['pageCount']),
							isset($invoice['translation']['continued']) ? $invoice['translation']['continued'] : 'Continued on page {page} of {pages}'
						)
					) ?></p>
				<?php } ?>
				<?php
			},

			'totals' => function () use ($invoice, $invLayout, $money, $clientName, $label) {
				?>
				<div class="adv-totals">
					<?php
					/*
					 * TOTAL first, then the workings underneath it.
					 *
					 * A reader looking at an invoice wants one number - what
					 * they owe - and used to find it at the bottom of a
					 * three-row sum they had to read to get to it. The breakdown
					 * is still there, in the same right-hand column, set one
					 * size smaller: it is the evidence, not the answer.
					 */
					?>
					<table class="adv-sum">
						<tr class="adv-sum-total">
							<td><?= $label('everything', 'Subtotal') ?></td>
							<td><?= html_escape($money($invoice['final_total'])) ?></td>
						</tr>
						<tr class="adv-sum-line">
							<td><?= $label('amount', 'Total') ?></td>
							<td><?= html_escape($money($invoice['invoice_amount'])) ?></td>
						</tr>
						<?php if ((float) $invoice['discount'] != 0) { ?>
							<tr class="adv-sum-line">
								<td><?= $label('discount', 'Discount') ?></td>
								<td><?= html_escape($invoice['discount'] . ' ' . $invoice['discount_type']) ?></td>
							</tr>
						<?php } ?>
						<tr class="adv-sum-line">
							<td><?= $label('tax_base', 'Tax base') ?></td>
							<td><?= html_escape($money($invoice['tax_base'])) ?></td>
						</tr>
						<tr class="adv-sum-line">
							<td>
								<?php if ($invoice['no_vat'] == 1) { ?>
									<?= $label('vat_charget', 'VAT') ?>
								<?php } else { ?>
									<?= $label('percentage_vat', 'Rate') ?> <?= $invoice['vat_percent'] ?>%
								<?php } ?>
							</td>
							<td><?= html_escape($money($invoice['vat_sum'])) ?></td>
						</tr>
					</table>
					<?php
					/*
					 * The drawers: payment type, beneficiary. Same idea as the
					 * Creative design - reference detail, folded away, small.
					 */
					/*
					 * Who receives the document - unless we have just said so.
					 *
					 * This drawer printed the client's own name a second time,
					 * six millimetres under the "Bill to" block that had already
					 * printed it. On a normal invoice the beneficiary IS the
					 * recipient, so the row was pure repetition: the same three
					 * words twice, and a reader who notices starts wondering
					 * whether they mean two different parties.
					 *
					 * It is suppressed when it matches what the block above
					 * already says, and kept when it genuinely differs - a
					 * company billed to its registered office but addressed to a
					 * named person, for instance.
					 */
					$advBeneficiary = (! empty($client['is_to_person']) && ! empty((isset($client['client_name']) ? $client['client_name'] : '')))
						? (isset($client['client_name']) ? $client['client_name'] : '')
						: ((isset($client['recipient_name']) ? $client['recipient_name'] : ''));
					$advAlreadyNamed = trim((string) $advBeneficiary) !== ''
						&& trim((string) $advBeneficiary) === trim((string) $clientName);
					$advHasPayment = ! empty($invoice['payment_method']);
					$advShowBen = $invLayout['showBeneficiary'] === '1'
						&& $advBeneficiary !== ''
						&& ! $advAlreadyNamed;
					?>
					<?php if ($advHasPayment || $advShowBen || ($invoice['no_vat'] == 1 && ! empty($invoice['no_vat_reason']))) { ?>
						<div class="adv-drawers">
							<?php if ($advHasPayment) { ?>
								<details>
									<summary><?= $label('payment_type', 'Payment') ?></summary>
									<p><?= html_escape($invoice['payment_method']) ?></p>
								</details>
							<?php } ?>
							<?php if ($advShowBen) { ?>
								<details>
									<summary><?= $label('recipient', 'Recipient') ?></summary>
									<p><?= html_escape($advBeneficiary) ?></p>
								</details>
							<?php } ?>
							<?php if ($invoice['no_vat'] == 1 && ! empty($invoice['no_vat_reason'])) { ?>
								<p class="adv-muted"><?= html_escape($invoice['no_vat_reason']) ?></p>
							<?php } ?>
						</div>
					<?php } ?>
				</div>
				<?php
			},

			'signature' => function () use ($invoice, $clientName, $label) {
				?>
				<div class="adv-sign">
					<?php
					/*
					 * The left column used to print the invoice's "schiffer" -
					 * a role label carried over from the original Bulgarian
					 * document, meaningless on the invoices this app issues.
					 * Removed outright rather than blanked: an empty left
					 * column is a visible gap, and this one was never filled.
					 */
					?>
					<div class="adv-sign-right">
						<span class="adv-sign-line"></span>
						<p class="adv-sign-cap"><?= $label('signature', 'Signature and stamp') ?></p>
					</div>
				</div>
				<?php
			},

			'remarks' => function () use ($invoice, $invLayout, $label) {
				if ($invLayout['showRemarks'] === '1' && trim((string) $invoice['remarks']) !== '')
				{
					?>
					<div class="adv-remarks">
						<span class="adv-label"><?= $label('remarks', 'Remarks') ?></span>
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
					/*
					 * The code is drawn in the letterhead now. A second copy here
					 * would put the same code on one sheet twice and invite a
					 * reader to scan the one nearest the fold.
					 */
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
	 * @param	array	$opts
	 * @return	void
	 */
	function adomin_invoice_render($invoice, $invLayout, $firmInfo, $opts = array())
	{
		/*
		 * Pages, drawn on a grid, rather than a column of blocks.
		 *
		 * The old version walked the sections and printed each one in flow, so
		 * the document was as tall as its content and the preview had no page to
		 * respect. Everything now lands in a band of a fixed A4 sheet - see
		 * adomin_invoice_page_body() - which is what makes the preview look like
		 * the page it will print on.
		 *
		 * A fixed sheet cannot flow, so an invoice with more rows than a page
		 * holds is split at the row a page can print and the remainder is carried
		 * onto a further sheet. Before this the extra rows were simply dropped
		 * past the printable height: the table looked complete because it ended
		 * at the paper's edge, and the client was never billed for the rest.
		 */
		$pages = adomin_invoice_pages($invoice, $invLayout);
		foreach ($pages as $page)
		{
			/*
			 * Only the last sheet carries the closing blocks. A signature on
			 * every page reads as several documents.
			 */
			$pageOpts = $opts;
			$pageOpts['rows'] = $page['rows'];
			$pageOpts['rowOffset'] = $page['rowOffset'];
			$pageOpts['pageNo'] = $page['pageNo'];
			$pageOpts['pageCount'] = $page['pageCount'];
			$pageOpts['continued'] = $page['continued'];
			echo adomin_invoice_page_body($invoice, $invLayout, $firmInfo, $pageOpts);
		}
	}
}

if ( ! function_exists('adomin_invoice_item_lines'))
{
	/**
	 * How many lines the item table will take.
	 *
	 * The grid below is proportional, so a fixed items band is either a large
	 * empty box on a three-line invoice or - the worse case - a band that clips
	 * rows off the bottom of the page once an account sells more than a dozen
	 * things. Counting the rows first lets the band take the height it needs and
	 * pushes the totals, signature and remarks down with it.
	 *
	 * @param	array	$invoice
	 * @return	int
	 */
	function adomin_invoice_item_lines($invoice)
	{
		$rows = NULL;
		foreach (array('items', 'products') as $key)
		{
			if (isset($invoice[$key]) && is_array($invoice[$key]))
			{
				$rows = $invoice[$key];
				break;
			}
		}
		if ($rows === NULL)
		{
			return 0;
		}

		$lines = 0;
		foreach ($rows as $row)
		{
			$lines += adomin_invoice_item_weight($row);
		}
		return $lines;
	}
}

if ( ! function_exists('adomin_invoice_item_weight'))
{
	/**
	 * How many printed lines one item takes.
	 *
	 * One for the row, plus two when the description is long enough to wrap at
	 * 10pt in a 512pt column. Deliberately generous: overestimating leaves a
	 * little white space, underestimating loses the bottom of a row.
	 *
	 * The table counter and the page splitter must agree on this or a page
	 * will be handed more lines than the band reserved for it - which is how a
	 * long description pushes the last row into the totals.
	 *
	 * @param	array	$item
	 * @return	int
	 */
	function adomin_invoice_item_weight($item)
	{
		$extras = '';
		foreach (array('name', 'description', 'item_description', 'notes') as $key)
		{
			if ( ! empty($item[$key]))
			{
				$extras .= ' ' . $item[$key];
			}
		}
		return strlen($extras) > 110 ? 3 : 1;
	}
}

if ( ! function_exists('adomin_invoice_item_rows'))
{
	/**
	 * The items, minus the ones that are really group headings.
	 *
	 * A "Group: " line is a band inside the table rather than a thing being
	 * invoiced, so it does not consume a numbered row - which means it must not
	 * count against the rows a page can hold either.
	 *
	 * @param	array	$invoice
	 * @return	array	the rows that will be printed, in order
	 */
	function adomin_invoice_item_rows($invoice)
	{
		$rows = array();
		$items = array();
		foreach (array('items', 'products') as $key)
		{
			if (isset($invoice[$key]) && is_array($invoice[$key]))
			{
				$items = $invoice[$key];
				break;
			}
		}

		foreach ($items as $item)
		{
			// the same two column shapes the renderer accepts - a group header
			// is "Section:" in either, and a row without a name is not a row
			$name = isset($item['name']) && $item['name'] !== ''
				? $item['name']
				: (isset($item['item']) ? $item['item'] : '');
			if (preg_match('/^\s*[^:]{2,40}:\s*$/', (string) $name))
			{
				continue;
			}
			$rows[] = $item;
		}
		return $rows;
	}
}

if ( ! function_exists('adomin_invoice_page_capacity'))
{
	/**
	 * How many item rows a sheet holds, by kind of sheet.
	 *
	 * The figure is not one number, because the two kinds of page are not the
	 * same page. The first carries the totals, the signature and the remarks
	 * underneath the table; a continued sheet carries only the table. So the
	 * first holds far fewer rows than the second - about fourteen against
	 * twenty-eight for the default design - and using a single number either
	 * drops rows off the first page or wastes half of every page after it.
	 *
	 * Rather than restate the fitter's arithmetic here and let the two drift
	 * apart, this asks the fitter: handing it a row count no page could hold
	 * makes it return the tallest band it would ever allow, which is exactly
	 * the ceiling. The row metrics then divide that into rows.
	 *
	 * @param	array	$invLayout
	 * @return	array	'first' => rows on the opening sheet, 'then' => on each sheet after
	 */
	function adomin_invoice_page_capacity($invLayout)
	{
		/* mirrors $head / $row / $padding in adomin_invoice_grid_fit */
		$head = 24.0;
		$row = 17.0;
		$padding = 10.0;

		/* the currency line above the table, when the layout puts it there */
		$currency = (isset($invLayout['currencyPos']) && $invLayout['currencyPos'] !== 'header') ? 14.0 : 0.0;

		$page = adomin_invoice_page();
		$ph = (float) $page[1];

		$grid = adomin_invoice_grid($invLayout);
		$closing = array('totals', 'signature', 'remarks');
		foreach ($closing as $key)
		{
			unset($grid[$key]);
		}

		/* the opening sheet: the fitter with the closing bands in place */
		$full = adomin_invoice_grid($invLayout);
		$opening = adomin_invoice_grid_fit($full, 100000);

		/* a continued sheet: the same fitter, with nothing left to reserve */
		$continuing = adomin_invoice_grid_fit($grid, 100000);

		$rows = function ($fit) use ($ph, $head, $row, $padding, $currency) {
			$band = $fit['items'][3] * $ph;
			return max(1, (int) floor(($band - $head - $padding - $currency) / $row));
		};

		return array(
			'first' => $rows($opening),
			'then' => $rows($continuing),
		);
	}
}

if ( ! function_exists('adomin_invoice_rows_per_page'))
{
	/**
	 * Rows a sheet holds, for callers that do not care which kind.
	 *
	 * @param	array	$invLayout
	 * @param	bool	$carriesClosing	TRUE for the opening sheet
	 * @return	int
	 */
	function adomin_invoice_rows_per_page($invLayout, $carriesClosing = TRUE)
	{
		$capacity = adomin_invoice_page_capacity($invLayout);
		return $carriesClosing ? $capacity['first'] : $capacity['then'];
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
		/*
		 * The totals band carries the sum, the folded drawers under it and the
		 * standing note about prices, so it grew: the drawers are four rows of
		 * 8pt type, and the total itself now leads the column at 11pt. The
		 * bands below did not move - a document that shifts its signature and
		 * remarks because a drawer was added is a document nobody can lay out
		 * against a template.
		 */
		return array(
			//          x      y      w      h
			/*
			 * The vertical fractions are fractions of the page *height*; only x
			 * and w are fractions of the width. The letterhead occupies the top
			 * of the sheet, so the header row reserves just that - it used to
			 * claim a quarter of the page and left a hand's width of blank paper
			 * between the letterhead and the first item.
			 */
			/*
			 * The header reserves the room the letterhead actually uses.
			 *
			 * The letterhead is positioned by adomin_invoice_placements(), and its
			 * lowest component - the address block - ends at 0.258 of the page.
			 * This band used to reserve 0.245 and was changed to 0.042 on the
			 * assumption that it was empty space; it was not. It was clearing the
			 * address, and removing it laid the company details underneath the
			 * item table.
			 *
			 * 0.265 clears that 0.258 with a little air, and the item band starts
			 * just under it. If the letterhead placements move, move this with
			 * them: the two are one layout, and the geometry harness asserts that
			 * the gap stays positive.
			 */
			'header'    => array(0.055, 0.042, 0.890, 0.223),  // logo, firm, address, doc no
			'items'     => array(0.055, 0.275, 0.890, 0.300),  // the table
			'totals'    => array(0.520, 0.590, 0.425, 0.120),  // sum + drawers
			'signature' => array(0.055, 0.760, 0.890, 0.055),
			'remarks'   => array(0.055, 0.825, 0.890, 0.050),
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

if ( ! function_exists('adomin_invoice_grid_fit'))
{
	/**
	 * Give the item band the height its rows need, and move the bands below it.
	 *
	 * A proportional grid alone cannot know how many rows an invoice has, so on
	 * a short invoice the table band was a large empty box and on a long one the
	 * rows were clipped at the band edge - silently losing the bottom of the
	 * table, which is the worst possible failure on a document someone is
	 * handing to a client.
	 *
	 * The bands are laid out in a known order and all share one column, so the
	 * fix is to measure once and then walk down the page: the item band takes
	 * exactly the height its rows need, and every band under it starts where the
	 * one above ends, keeping its original gap. A band an account has dragged
	 * somewhere else is left alone as long as it still fits.
	 *
	 * @param	array	$grid	key => [x, y, w, h] as fractions of the page
	 * @param	int	$rows	number of item lines
	 * @return	array
	 */
	function adomin_invoice_grid_fit($grid, $rows)
	{
		if ( ! is_array($grid) || ! isset($grid['items']))
		{
			return $grid;
		}

		/*
		 * Points, because the row height is a typographic fact rather than a
		 * fraction of anything. A 10pt row with its cell padding is 17pt; the
		 * table head is 22pt. These mirror the .adv-items table rules.
		 */
		$head = 24;
		$row = 17;
		$padding = 10;
		$needed = $head + max(0, (int) $rows) * $row + $padding;

		$page = adomin_invoice_page();
		$pw = (float) $page[0];
		$ph = (float) $page[1];

		/*
		 * Top, height and the fractions of them are all fractions of the page
		 * *height*; only left and width are fractions of the width. Mixing the
		 * two put the whole document in the top of the sheet.
		 */
		list($ix, $iy, $iw, $ih) = $grid['items'];
		$have = $ih * $ph;

		/*
		 * The grid works in fractions of the full sheet, but only the part of the
		 * sheet inside the renderer's margins is printable. Anything below that
		 * line is pushed onto a second page by dompdf, so the table's ceiling is
		 * the printable height rather than the paper height.
		 */
		$printable = adomin_invoice_printable_height();

		/*
		 * Never grow beyond what the sheet can hold: the bottom margin is the
		 * same 5% the grid uses, and the fixed bands below the table must keep
		 * their own room.
		 */
		/*
		 * The room the rest of the sheet needs. The bands under the table keep
		 * their declared heights, and the two gaps of the default design come
		 * on top - without them a long invoice pushed the foot blocks past the
		 * bottom margin and the note printed off the paper.
		 */
		$below = 0.0;
		$gaps = 0.0;
		$prev = $iy + $ih;
		foreach (array('totals', 'signature', 'remarks') as $key)
		{
			if ( ! isset($grid[$key]))
			{
				continue;
			}
			$below += $grid[$key][3];
			$gaps += max(0.0, $grid[$key][1] - $prev);
			$prev = $grid[$key][1] + $grid[$key][3];
		}

		$ceiling = $printable - ($iy + $below + $gaps) * $ph;

		/*
		 * Never below the room the other bands need: if thirty rows genuinely
		 * do not fit, the table takes the page and the note is what gives -
		 * losing white space is better than losing invoice lines.
		 */
		$height = min(max($needed, 0), max($ceiling, $have));
		$grid['items'][3] = round($height / $ph, 4);

		/*
		 * Where the bands go.
		 *
		 * With a short invoice the table gives up more height than it needs, and
		 * that slack has to go somewhere. It is handed to the signature and the
		 * remarks - the two blocks that sit at the foot of a letter - so the
		 * closing of the document still lands near the bottom of the paper and
		 * the sheet reads as one page rather than a short document pinned to the
		 * top. A long invoice gets no slack, and the blocks simply stack.
		 */
		$order = array('totals', 'signature', 'remarks');

		/*
		 * The last fraction of the sheet that is still printable. A block whose
		 * bottom crossed this ended up on the next page, which is how a
		 * one-line invoice produced a second sheet carrying only the note.
		 */
		$floor = $printable / $ph - 0.005;

		$cursor = $iy + $grid['items'][3];
		foreach ($order as $key)
		{
			if ( ! isset($grid[$key]))
			{
				continue;
			}
			/* never start below the last printable line, nor above the block above */
			$cursor = min($cursor, $floor - $grid[$key][3]);
			$cursor = max($cursor, $iy + $grid['items'][3]);
			$grid[$key][1] = round($cursor, 4);
			$cursor = $grid[$key][1] + $grid[$key][3];
		}

		/*
		 * The bottom inset the grid itself uses. Everything below is measured
		 * against it, so the slack is real empty paper rather than a page that
		 * has overflowed.
		 */
		/*
		 * Measured against the printable area, not the paper. Pushing the foot
		 * blocks down to 0.95 of the sheet - what this used to do - put them at
		 * 800pt on a page whose last printable line is 768pt, so dompdf moved the
		 * signature and the note onto a second sheet. The slack runs to the last
		 * line the renderer can print, and no further.
		 */
		$bottom = $printable / $ph;
		$slack = max(0.0, $bottom - $cursor);

		/*
		 * Push the foot blocks down by the slack: two thirds of it to the
		 * signature, so the gap above a signature line is the larger one, and
		 * the rest to the remarks.
		 */
		if ($slack > 0 && isset($grid['signature']))
		{
			$grid['signature'][1] = round($grid['signature'][1] + $slack, 4);
			if (isset($grid['remarks']))
			{
				/*
				 * The note stays under the signature line rather than being
				 * pushed to the foot on its own: a caption that has drifted
				 * away from the thing it captions reads as a stray sentence,
				 * and one gap above the signature is the natural break.
				 */
				$grid['remarks'][1] = round($grid['signature'][1] + $grid['signature'][3], 4);
			}
		}

		return $grid;
	}
}

if ( ! function_exists('adomin_invoice_grid_for'))
{
	/**
	 * The grid an invoice actually needs.
	 *
	 * The two paths that draw a document - the shared renderer and the canvas
	 * templates, which build their own band loop - have to agree on where the
	 * bands go, or the same invoice prints differently depending on which one
	 * produced it. Both call this.
	 *
	 * @param	array	$invoice
	 * @param	array	$invLayout
	 * @return	array
	 */
	function adomin_invoice_grid_for($invoice, $invLayout)
	{
		return adomin_invoice_grid_fit(
			adomin_invoice_grid($invLayout),
			adomin_invoice_item_lines($invoice)
		);
	}
}

if ( ! function_exists('adomin_invoice_pages'))
{
	/**
	 * Split an invoice into the sheets it needs.
	 *
	 * Most invoices are one page, and the first page keeps every part of the
	 * letter: letterhead, figures, signature, remarks. A further page carries
	 * the letterhead and the rest of the table, and says so at the foot -
	 * without that line a second sheet looks like a different document, and the
	 * reader has no way of telling whether anything is missing between them.
	 *
	 * The opening sheet holds fewer rows than the ones after it, because the
	 * closing bands sit under its table. Filling it to the continued sheet's
	 * capacity would run the last rows into the totals.
	 *
	 * @param	array	$invoice
	 * @param	array	$invLayout
	 * @return	array	list of array(rows, rowOffset, pageNo, pageCount, continued)
	 */
	function adomin_invoice_pages($invoice, $invLayout)
	{
		$rows = adomin_invoice_item_rows($invoice);
		$capacity = adomin_invoice_page_capacity($invLayout);

		if (count($rows) === 0)
		{
			return array(array(
				'rows' => array(), 'rowOffset' => 0, 'pageNo' => 1, 'pageCount' => 1, 'continued' => FALSE,
			));
		}

		/*
		 * Spread the rows across the sheets, rather than filling each one.
		 *
		 * Filling the opening sheet to its capacity and then starting another
		 * gives 13 rows on the first and 17 on the second for a 30-row invoice,
		 * with the lower third of the first sheet empty - the dead space a
		 * reader notices first. The rows are shared out in proportion to what
		 * each sheet can hold instead, so the table reads evenly across the
		 * paper it is printed on.
		 *
		 * Lines, not rows: an item with a long description occupies three lines
		 * of the band, so a sheet that accepted thirty rows of them would run
		 * the last rows under the totals.
		 */
		$weights = array();
		foreach ($rows as $row)
		{
			$weights[] = adomin_invoice_item_weight($row);
		}

		/*
		 * Fill each sheet in turn: the opening one to its smaller capacity, and
		 * every sheet after it to the larger one.
		 *
		 * Filling, rather than sharing the rows out evenly, is what looks right.
		 * The closing blocks occupy the space under the table on the last sheet,
		 * so the last sheet fills itself; on any sheet before it there is nothing
		 * under the table, and the only way to avoid a half-empty page is to use
		 * all of it. Rows are counted in printed lines, not items, because a long
		 * description takes three lines of the band.
		 */
		$first = max(1, $capacity['first']);
		$then = max(1, $capacity['then']);

		$pages = array();
		$offset = 0;
		$rowsTotal = count($rows);
		while ($offset < $rowsTotal)
		{
			$budget = count($pages) === 0 ? $first : $then;
			$take = array();
			$used = 0;
			while ($offset + count($take) < $rowsTotal)
			{
				$weight = adomin_invoice_item_weight($rows[$offset + count($take)]);
				/* always take one row, or a sheet would print nothing */
				if (count($take) > 0 && $used + $weight > $budget)
				{
					break;
				}
				$used += $weight;
				$take[] = $rows[$offset + count($take)];
			}

			$pages[] = array(
				'rows' => $take,
				'rowOffset' => $offset,
				'pageNo' => count($pages) + 1,
				'pageCount' => 0,
				'continued' => FALSE,
			);
			$offset += count($take);
		}

		/* the count is only known once every sheet exists */
		$last = count($pages) - 1;
		foreach ($pages as $i => $page)
		{
			$pages[$i]['pageCount'] = $last + 1;
			$pages[$i]['continued'] = $i < $last;
		}

		return $pages;
	}
}

if ( ! function_exists('adomin_invoice_band_loop'))
{
	/**
	 * Lay the blocks out as bands on one sheet.
	 *
	 * Three paths draw a document: the shared renderer, and the Creative and
	 * Toner Save templates, which build their own closures. They used to walk
	 * the sections themselves, in two slightly different loops, so a change to
	 * the page landed in one path and not the others. This is the one loop.
	 *
	 * @param	array	$blocks		name => closure
	 * @param	array	$grid		name => [x, y, w, h] fractions
	 * @param	array	$invLayout
	 * @param	array	$page		array(width, height) in points
	 * @param	array	$opts		rowOffset, continued, pageNo as for the renderer
	 * @return	void
	 */
	function adomin_invoice_band_loop($blocks, $grid, $invLayout, $page, $opts = array())
	{
		$pw = (float) $page[0];
		$ph = (float) $page[1];

		/*
		 * Mark the sheet with the slice it carries, so the item block knows
		 * which rows to print and where their numbering starts.
		 */
		$pageNo = isset($opts['pageNo']) ? (int) $opts['pageNo'] : 1;
		echo '<div class="adv-page adv-page-final" data-av-page="' . $pageNo . '"'
			. ' style="--av-pw:' . $pw . 'pt;--av-ph:' . round(adomin_invoice_printable_height(), 2) . 'pt;">';

		/* a design's own frame around the page content, if it has one */
		if ( ! empty($opts['pageOpen']))
		{
			echo $opts['pageOpen'];
		}

		/*
		 * A continued sheet carries the letterhead and the rest of the table,
		 * and nothing that closes a letter - see adomin_invoice_page_body().
		 */
		$carriesOver = ! empty($opts['continued']);

		foreach (adomin_invoice_sections($invLayout) as $name)
		{
			if ( ! isset($blocks[$name]))
			{
				continue;
			}
			if ($carriesOver && in_array($name, array('totals', 'signature', 'remarks'), TRUE))
			{
				continue;
			}
			if ($name === 'header' || ! isset($grid[$name]))
			{
				if ($name === 'header' && ! empty($opts['headerClass']))
				{
					echo '<div class="' . html_escape($opts['headerClass']) . '">';
					$blocks[$name]();
					echo '</div>';
					continue;
				}
				$blocks[$name]();
				continue;
			}

			list($x, $y, $w, $h) = $grid[$name];
			echo '<div class="adv-band adv-band-' . $name . '"'
				. ' data-avc-block="' . $name . '"'
				. ' data-avc-label="' . html_escape(adomin_invoice_section_label($name)) . '"'
				. ' style="left:' . round($x * $pw, 2) . 'pt;'
				. 'top:' . round($y * $ph, 2) . 'pt;'
				. 'width:' . round($w * $pw, 2) . 'pt;'
				. 'height:' . round($h * $ph, 2) . 'pt;">';
			$blocks[$name]();
			echo '</div>';
		}

		if ( ! empty($opts['pageClose']))
		{
			echo $opts['pageClose'];
		}

		echo '</div>';
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
	 * @param	array	$opts
	 * @return	string	html
	 */
	function adomin_invoice_page_body($invoice, $invLayout, $firmInfo, $opts = array())
	{
		$blocks = adomin_invoice_doc_blocks($invoice, $invLayout, $firmInfo, $opts);
		/*
		 * The grid is proportional; the item table is not. Fit the one to the
		 * other before drawing, so a three-line invoice does not leave half the
		 * page boxed off and a thirty-line one does not lose its bottom rows.
		 */
		/*
		 * Measure the rows this sheet prints. On a one-page invoice that is the
		 * whole table; on a continued sheet it is the remainder, so the band
		 * takes its own height rather than reserving space for rows that were
		 * carried over.
		 */
		$grid = adomin_invoice_grid_fit(
			adomin_invoice_grid($invLayout),
			adomin_invoice_item_lines(array('items' => isset($opts['rows']) ? $opts['rows'] : adomin_invoice_item_rows($invoice)))
		);
		$page = adomin_invoice_page();
		$pw = (int) $page[0];
		$ph = (int) $page[1];

		/*
		 * adv-page-final marks a rendered document, as opposed to the editor's
		 * canvas. The page geometry is the same; the difference is that a
		 * finished page clips a band that overflows it instead of letting the
		 * block spill over the one below.
		 */
		/*
		 * A continued sheet is marked so the sheet under it reads as the same
		 * document, and so nothing that belongs at the foot of a letter - the
		 * signature, the remarks - is repeated on every page.
		 */
		$pageNo = isset($opts['pageNo']) ? max(1, (int) $opts['pageNo']) : 1;
		/*
		 * --av-ph is the printable height, not the paper height. The sheet sits
		 * inside the renderer's margins, so a sheet as tall as the paper is
		 * taller than the box it is drawn in and its foot lands on a second
		 * page. Every band is already anchored inside the printable area, so
		 * this moves nothing; it only stops the overflow.
		 */
		$html = '<div class="adv-page adv-page-final" data-av-page="' . $pageNo . '"'
			. ' style="--av-pw:' . $pw . 'pt;--av-ph:' . round(adomin_invoice_printable_height(), 2) . 'pt;">';

		/*
		 * A continued sheet carries the letterhead and the rest of the table,
		 * and nothing that closes a letter. Totals, signature and remarks on
		 * every sheet would read as several separate documents - and a total
		 * halfway through a table invites the reader to stop at it.
		 */
		$carriesOver = ! empty($opts['continued']);
		foreach (adomin_invoice_sections($invLayout) as $name)
		{
			if ($carriesOver && in_array($name, array('totals', 'signature', 'remarks'), TRUE))
			{
				continue;
			}
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
			/*
			 * Width against the page width, height against the page height.
			 * The grid is a fraction of the *sheet*, and the five bands of the
			 * default design sum to exactly 1.0 down the page - so reading the
			 * vertical fractions against the width (595pt) drew the whole
			 * document into the top 70% of an 842pt sheet and left the rest of
			 * the paper empty.
			 */
			$style = 'left:' . round($x * $pw, 2) . 'pt;'
				. 'top:' . round($y * $ph, 2) . 'pt;'
				. 'width:' . round($w * $pw, 2) . 'pt;'
				. 'height:' . round($h * $ph, 2) . 'pt;';

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
