<?php
/*
 * Normalise the document's labels before anything reads them.
 *
 * The wording is a snapshot, copied from invoices_languages when the document
 * was issued, so an invoice created before "bulstat" became KRA PIN still
 * carries the old string in its own row. Rewriting that row is wrong - the
 * point of a snapshot is that it does not change under the sender - so the
 * renames are applied here, on the way into the template, and every template
 * therefore prints the same words whichever path rendered it.
 */
if (isset($invoice['translation']) && function_exists('adomin_normalise_labels')) {
    $invoice['translation'] = adomin_normalise_labels($invoice['translation']);
}

/*
 * Banner — a letterhead-and-band design.
 *
 * Modelled on a printed invoice supplied as a reference: brand block at the
 * top, a reference column beside the document number and date, an accent band
 * carrying the document title, tinted bands for item groups, and a full-bleed
 * footer strip with the contact details.
 *
 * Like the other designs it renders its blocks in the order chosen on the
 * template canvas, so reordering there changes this document too. Every value
 * comes from $invoice in the same shape creative.php uses; nothing is read from
 * the settings pane here except the layout itself, which means the canvas
 * preview and the PDF are produced by this one file.
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

$invLayout = adomin_invoice_layout(NULL, isset($invoice['for_user']) ? (int) $invoice['for_user'] : NULL);
$invAccent = adomin_invoice_accent($invLayout['accent']);


/*
 * The reference lines above the table. A plumbing firm's invoice carried
 * Client / Ref / Project / Location / Prepared by; of those, this document
 * actually holds a client, a project-style reference and the person who
 * prepared it, so those are the rows that appear. Empty ones are dropped
 * rather than printed with a blank value.
 */
/*
 * The document's labels, read from a snapshot that may be partial.
 *
 * Every line below this point prints at least one of them, and a missing
 * translation row - an old document, an imported one, one whose snapshot was
 * never written - used to turn the first read into a notice where the label
 * belongs. The banner's whole header is built around these words, so the sheet
 * came out with an empty band above the table.
 */
$ibLabels = isset($invoice['translation']) && is_array($invoice['translation']) ? $invoice['translation'] : array();
$ibLabel = function ($key, $fallback) use ($ibLabels) {
    return (isset($ibLabels[$key]) && $ibLabels[$key] !== '') ? $ibLabels[$key] : $fallback;
};

/*
 * Fill in the fields this document does not carry.
 *
 * Each design builds its own bands, so each reads the snapshot directly. One
 * declared field list, applied on the way in, is what keeps a partial document
 * from costing a band - see adomin_invoice_fields().
 */
$invoice = adomin_invoice_fields($invoice);
$ibRefs = array();
$ibClientName = '';
if (isset($invoice['client']['client_name'])) {
    $ibClientName = $invoice['client']['client_name'];
} elseif (isset($invoice['client']['is_to_person'])) {
    $ibClientName = $invoice['client']['recipient_name'];
}
if ($ibClientName !== '') {
    $ibRefs[$ibLabel('recipient', 'Recipient')] = $ibClientName;
}
if (!empty($invoice['to_inv_number'])) {
    $ibRefs[$ibLabel('to_an_invoice', 'To invoice')] = $invoice['to_inv_number'];
}
if (!empty($invoice['client']['client_address'])) {
    $ibRefs[lang('firm_reg_address')] = $invoice['client']['client_address'];
}
if (!empty($invoice['client']['client_city'])) {
    $ibRefs[lang('firm_city')] = $invoice['client']['client_city'];
}
if (!empty($invoice['composed'])) {
    $ibRefs[$ibLabel('compiled', 'Compiled by')] = $invoice['composed'];
}

$ibLogo = '';
if (function_exists('adomin_firm_logo_src')) {
    $ibLogo = adomin_firm_logo_src($invoice, $firmInfo);
} elseif (!empty($invoice['firm']['image']) && !empty($firmInfo['show_logo'])) {
    $ibLogo = base_url('attachments/companiesimages/' . $firmInfo['id'] . '/' . $invoice['firm']['image']);
}
if ($invLayout['showLogo'] !== '1') {
    $ibLogo = '';
}

/*
 * The verification code, at the top right of the sheet, opposite the company
 * details.
 *
 * Two forms, on purpose. The browser gets the SVG markup, which it draws
 * directly. The PDF gets a PNG in an <img>, because dompdf does not draw inline
 * <svg> at all - it only reads an image source, and an SVG source sends it
 * through php-svg-lib's XML parser. A PNG crosses no parser and no filesystem,
 * which is what a read-only host needs.
 *
 * Both come from the same library and the same URL, so the code that prints
 * and the code that shows on screen always point at the same page. If the
 * library or the key is missing both are '' and the letterhead simply has no
 * code - a document must never fail because a check cannot be drawn.
 */
$ibQr = adomin_invoice_qr_html($invoice, 74);

if (isset($invoice['inv_type']) && $invoice['inv_type'] == 'prof') {
    $ibType = $ibLabel('pro_forma', 'Pro forma invoice');
} elseif (isset($invoice['inv_type']) && $invoice['inv_type'] == 'debit') {
    $ibType = $ibLabel('debit_note', 'Debit note');
} elseif (isset($invoice['inv_type']) && $invoice['inv_type'] == 'credit') {
    $ibType = $ibLabel('credit_note', 'Credit note');
} else {
    $ibType = $ibLabel('invoice', 'Invoice');
}

$ibMoney = function ($value) use ($invoice) {
    // the currency is stated once, in the header row - see adomin_invoice_doc_blocks
    return $value;
};
?>
<?php
/*
 * The page. Banner draws its own layout rather than going through
 * adomin_invoice_render(), so it opens the same A4 sheet here - without this the
 * design was as tall as its content and the preview could not show a page.
 */
$ibPage = adomin_invoice_page();
?>
<div class="main-inv-container">
    <div class="invoice-box">
            <?php
            /*
             * The sheet the closures draw, bound by reference: the blocks are
             * built once and drawn once per page.
             */
            $ibSheet = null;

            /*
             * The decorative band along the top. It is a separate element rather
             * than a background on the document so the frame design can outline
             * the page while the other designs draw a strip inside it.
             */
            if (!empty($invLayout['edge'])) {
                echo adomin_invoice_edge_css($invLayout['edge'], $invAccent) !== ''
                    ? '<div class="av-edge" aria-hidden="true"></div>'
                    : '';
            }
            ?>
            <?php
            $ibBlocks = array(
                'header' => function () use ($invoice, $invLayout, $firmInfo, $ibLogo, $ibRefs, $ibType, $ibMoney, $ibQr, $ibLabel) {
                    /*
                     * Four independently placed pieces rather than one flow. The
                     * canvas moves each one and the printed document follows,
                     * because both go through adomin_invoice_positioned_letterhead()
                     * with the same positions.
                     */
                    $ibPieces = array();

                    if ($ibLogo !== '') {
                        $ibPieces['logo'] = '<img src="' . html_escape($ibLogo) . '" alt="'
                            . html_escape(isset($firmInfo['name']) && $firmInfo['name'] !== '' ? $firmInfo['name'] : (isset($invoice['firm']['name']) ? $invoice['firm']['name'] : '')) . '">';
                    }

                    $ibPieces['firm'] = '<p class="ib-firm" style="margin:0;">'
                        . html_escape(isset($firmInfo['name']) && $firmInfo['name'] !== '' ? $firmInfo['name'] : (isset($invoice['firm']['name']) ? $invoice['firm']['name'] : '')) . '</p>';

                    $ibAddr = '';
                    if ($invLayout['showAddress'] === '1' && !empty($invoice['firm']['address'])) {
                        $ibAddr .= '<p>' . html_escape($invoice['firm']['address']) . '</p>';
                    }
                    if ($invLayout['showContacts'] === '1' && !empty($firmInfo['city'])) {
                        $ibAddr .= '<p>' . html_escape($firmInfo['city']) . '</p>';
                    }
                    /*
                     * The KRA PIN and the VAT number are separate switches. A
                     * business can print one without the other, which is the
                     * whole reason they are not one "tax details" toggle.
                     */
                    if ($invLayout['showFirmPin'] === '1' && !empty($invoice['firm']['bulstat'])) {
                        $ibAddr .= '<p>' . $ibLabel('bulstat', 'KRA PIN')
                            . html_escape($invoice['firm']['bulstat']) . '</p>';
                    }
                    if ($ibAddr !== '') {
                        $ibPieces['address'] = $ibAddr;
                    }

                    $ibDocNo = '<table>'
                        . '<tr><th>' . $ibLabel('number', 'Number') . '</th><td>'
                        . html_escape($invoice['inv_number']) . '</td></tr>'
                        . '<tr><th>' . $ibLabel('date_of_issue', 'Date of issue') . '</th><td>'
                        . date('d.m.Y', $invoice['date_create']) . '</td></tr>'
                        . '<tr><th>' . $ibLabel('a_date_of_a_tax_event', 'Date of tax event') . '</th><td>'
                        . date('d.m.Y', $invoice['date_tax_event']) . '</td></tr>';
                    /*
                     * The currency is declared once, here.
                     *
                     * It used to be appended to every figure in the item table
                     * AND printed here when the layout asked for it in the
                     * header, so a Banner invoice could state KES ten times or
                     * none at all depending on one layout setting. The
                     * reference block always carries it now, because the
                     * figures below are bare and have to be read in something.
                     */
                    if ($invLayout['currencyPos'] === 'header' || ! empty($invoice['inv_currency'])) {
                        $ibDocNo .= '<tr><th>' . (isset($invoice['translation']['currency']) ? $invoice['translation']['currency'] : 'Currency')
                            . '</th><td>' . html_escape($invoice['inv_currency']) . '</td></tr>';
                    }
                    $ibPieces['docno'] = $ibDocNo . '</table>';

                    echo adomin_invoice_positioned_css($invLayout);
                    /*
                     * The company block is placed absolutely on the page - the
                     * canvas moves it - so it cannot share a table row with
                     * anything. The code is therefore the right-hand cell of the
                     * reference row underneath it, which is the same place on the
                     * sheet: top right, opposite the company details.
                     */
                    echo adomin_invoice_positioned_letterhead($invLayout, $ibPieces);
                    ?>
            <!-- the reference column on the left, the verification code on the right -->
            <table class="ib-meta">
                <tr>
                    <td class="ib-meta-left">
                        <table class="ib-ref">
                            <?php foreach ($ibRefs as $ibLabel => $ibValue) { ?>
                                <tr>
                                    <th><?= html_escape($ibLabel) ?>:</th>
                                    <td><?= html_escape($ibValue) ?></td>
                                </tr>
                            <?php } ?>
                        </table>
                    </td>
                    <td class="ib-meta-qr">
                        <?php if ($ibQr !== '') { ?>
                            <div class="ib-qr">
                                <?= $ibQr ?>
                                <?php // verify_scan is a lang() key, not a per-invoice translation column ?>
                                <span class="ib-qr-cap"><?= html_escape(lang('verify_scan')) ?></span>
                            </div>
                        <?php } ?>
                    </td>
                </tr>
            </table>

            <?php /*
                The code has to sit above the company block's absolute box, not
                behind it. Both are in the same stacking context and the code
                comes later in the document, so it wins - but only if it is
                actually painted, hence the explicit position on the cell.
            */ ?>

            <div class="ib-title-band">
                <h2 class="ib-title"><?= html_escape($ibType) ?></h2>
            </div>
                    <?php
                },
                'items' => function () use ($invoice, $ibMoney, &$ibSheet, $ibLabel) {
                    ?>
            <table class="ib-items">
                <thead>
                    <tr>
                        <th class="ib-num">#</th>
                        <th class="ib-name"><?= $ibLabel('products_name', 'Item') ?></th>
                        <th class="ib-qty"><?= $ibLabel('quantity', 'Qty') ?></th>
                        <th class="ib-unit">Unit</th>
                        <th class="ib-money"><?= $ibLabel('single_price', 'Unit price') ?></th>
                        <th class="ib-money"><?= $ibLabel('value', 'Amount') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    /* only the rows this sheet carries - see adomin_invoice_pages() */
                    $ibRow = (int) $ibSheet['rowOffset'];
                    foreach ($ibSheet['rows'] as $item) {
                        /* a leading "Group:" line in the name becomes a section
                           band, the way the reference invoice groups pipes,
                           valves, fasteners and tools. It is optional: items
                           without it simply continue the table.
                           The row counter only advances for real items, so the
                           numbering stays 1..n across groups instead of
                           counting the bands. */
                        if (preg_match('/^\s*([^:]{2,40}):\s*$/', (string) (isset($item['name']) && $item['name'] !== '' ? $item['name'] : (isset($item['item']) ? $item['item'] : '')), $ibMatch)) {
                            ?>
                            <tr>
                                <td colspan="6" class="ib-section" style="padding:7px 10px;">
                                    <?= html_escape($ibMatch[1]) ?>
                                </td>
                            </tr>
                            <?php
                            continue;
                        }
                        $ibRow++;
                        ?>
                        <tr>
                            <td class="ib-num"><?= $ibRow ?></td>
                            <td class="ib-name"><?= html_escape((isset($item['name']) && $item['name'] !== '' ? $item['name'] : (isset($item['item']) ? $item['item'] : ''))) ?></td>
                            <td class="ib-qty"><?= html_escape((isset($item['quantity']) ? $item['quantity'] : '')) ?></td>
                            <td class="ib-unit"><?= html_escape((isset($item['quantity_type']) ? $item['quantity_type'] : '')) ?></td>
                            <td class="ib-money"><?= html_escape($ibMoney((isset($item['single_price']) ? $item['single_price'] : '0'))) ?></td>
                            <td class="ib-money"><?= html_escape($ibMoney((isset($item['total_price']) ? $item['total_price'] : (isset($item['value']) ? $item['value'] : '0')))) ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
                    <?php if (!empty($ibSheet['continued'])) { ?>
                        <p class="adv-continued"><?= html_escape(str_replace(
                            array('{page}', '{pages}'),
                            array((int) $ibSheet['pageNo'] + 1, (int) $ibSheet['pageCount']),
                            isset($invoice['translation']['continued']) ? $invoice['translation']['continued'] : 'Continued on page {page} of {pages}'
                        )) ?></p>
                    <?php } ?>
                    <?php
                },
                'totals' => function () use ($invoice, $ibMoney, $ibLabel) {
                    ?>
            <table class="ib-totals">
                <tr>
                    <td class="ib-totals-note">
                        <?php if (!empty($invoice['payment_method'])) { ?>
                            <p style="margin:0 0 4px;">
                                <b><?= $ibLabel('payment_type', 'Payment') ?>:</b>
                                <?= html_escape($invoice['payment_method']) ?>
                            </p>
                        <?php } ?>
                        <?php if ($invoice['no_vat'] == 1 && !empty($invoice['no_vat_reason'])) { ?>
                            <p style="margin:0;"><?= html_escape($invoice['no_vat_reason']) ?></p>
                        <?php } ?>
                    </td>
                    <td>
                        <table class="ib-sum">
                            <tr>
                                <td><?= $ibLabel('amount', 'Total') ?></td>
                                <td><?= html_escape($ibMoney($invoice['invoice_amount'])) ?></td>
                            </tr>
                            <?php if ((float) $invoice['discount'] != 0) { ?>
                                <tr>
                                    <td><?= $ibLabel('discount', 'Discount') ?></td>
                                    <td><?= html_escape($invoice['discount'] . ' ' . $invoice['discount_type']) ?></td>
                                </tr>
                            <?php } ?>
                            <tr>
                                <td><?= $ibLabel('tax_base', 'Tax base') ?></td>
                                <td><?= html_escape($ibMoney($invoice['tax_base'])) ?></td>
                            </tr>
                            <tr>
                                <td>
                                    <?php if ($invoice['no_vat'] == 1) { ?>
                                        <?= $ibLabel('vat_charget', 'VAT') ?>
                                    <?php } else { ?>
                                        <?= $ibLabel('percentage_vat', 'VAT') ?> - <?= $invoice['vat_percent'] ?>%
                                    <?php } ?>
                                </td>
                                <td><?= html_escape($ibMoney($invoice['vat_sum'])) ?></td>
                            </tr>
                            <tr class="ib-sum-total">
                                <td><?= $ibLabel('everything', 'Subtotal') ?></td>
                                <td><?= html_escape($ibMoney($invoice['final_total'])) ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
                    <?php
                },
                'signature' => function () use ($invoice, $ibLabel) {
                    ?>
            <table class="ib-sign">
                <tr>
                    <td class="ib-sign-left">
                        <span class="ib-sign-line"></span>
                        <div class="ib-sign-cap"><?= html_escape(isset($firmInfo['name']) && $firmInfo['name'] !== '' ? $firmInfo['name'] : (isset($invoice['firm']['name']) ? $invoice['firm']['name'] : '')) ?></div>
                    </td>
                    <td class="ib-sign-right">
                        <span class="ib-sign-line"><?= $ibLabel('signature', 'Signature') ?></span>
                        <div class="ib-sign-cap">Prepared by <?= html_escape($invoice['composed']) ?></div>
                    </td>
                </tr>
            </table>
                    <?php
                },
                'remarks' => function () use ($invoice, $invLayout, $firmInfo, $ibLabel) {
                    ?>
            <?php if ($invLayout['showRemarks'] === '1' && trim((string) $invoice['remarks']) !== '') { ?>
                <div class="ib-remarks">
                    <p><b><?= $ibLabel('remarks', 'Remarks') ?>:</b></p>
                    <p><?= nl2br(html_escape($invoice['remarks'])) ?></p>
                </div>
            <?php } ?>
            <?php
            /*
             * The standing line about the figures, last on the sheet - the same
             * line the other designs carry, from the same setting.
             */
            $ibNote = trim((string) $invLayout['footerNote']);
            if ($ibNote === '') {
                $ibNote = 'The prices displayed are estimates, and are subject to change without previous notice.';
            }
            ?>
            <p class="ib-note"><?= html_escape($ibNote) ?></p>
            <!-- the footer strip: contact details, then the closing line -->
            <div class="ib-footer">
                <?php /*
                    This was a two-column table whose right-hand cell held a
                    second copy of the verification code. The code lives in the
                    letterhead now, opposite the company details, so the table
                    has one cell left and became a plain paragraph. Printing it
                    here again would put two copies on one sheet and invite
                    somebody to scan the one nearest the fold.
                */ ?>
                <p class="ib-footer-thanks">Thank you for your business</p>
                <?php
                /*
                 * Only columns that exist on firms_translations / firms_users:
                 * name, address, city, mol, image, bulstat, vat_number. An
                 * earlier draft also printed email and phone, which are not
                 * stored anywhere - they would have been empty on every invoice.
                 */
                $ibFoot = array();
                if (!empty($invoice['firm']['address']) || !empty($firmInfo['city'])) {
                    $ibFoot['Address'] = trim($invoice['firm']['address'] . ', ' . $firmInfo['city'], ', ');
                }
                if (!empty($firmInfo['accountable_person']) || !empty($invoice['firm']['accountable_person'])) {
                    $ibFoot[$ibLabel('mol', 'MOL')] = !empty($firmInfo['accountable_person'])
                        ? $firmInfo['accountable_person']
                        : $invoice['firm']['accountable_person'];
                }
                if ($invLayout['showFirmVat'] === '1' && !empty($invoice['firm']['vat_number'])) {
                    // vat_number is a column on invoices_translations; not every
                    // saved translation has it, and an undefined index here is
                    // a warning in the middle of the printed footer
                    $ibFoot[isset($invoice['translation']['vat_number']) && $invoice['translation']['vat_number'] !== ''
                        ? $invoice['translation']['vat_number']
                        : 'VAT'] = $invoice['firm']['vat_number'];
                }
                ?>
                <?php if ($ibFoot) { ?>
                    <table>
                        <tr>
                            <?php foreach ($ibFoot as $ibLabel => $ibValue) { ?>
                                <td>
                                    <span class="ib-footer-label"><?= html_escape($ibLabel) ?></span>
                                    <?= html_escape($ibValue) ?>
                                </td>
                            <?php } ?>
                        </tr>
                    </table>
                <?php } ?>
                <?php if (trim((string) $invLayout['footerNote']) !== '') { ?>
                    <p class="ib-footer-note"><?= nl2br(html_escape($invLayout['footerNote'])) ?></p>
                <?php } ?>
            </div>
                    <?php
                },
            );

            /*
             * Every section lands in its band on the page. The header block is
             * already positioned absolutely by adomin_invoice_positioned_letterhead(),
             * so it is drawn as it is; the rest are regions of the page and are
             * wrapped so they can be moved and resized as one.
             */
            /*
             * The grid this invoice needs: the item band takes the height its
             * rows take, and the bands under it move up to meet it.
             */
            $ibSheets = adomin_invoice_pages($invoice, $invLayout);
            foreach ($ibSheets as $ibSheet) {
                $ibGrid = adomin_invoice_grid_fit(
                    adomin_invoice_grid($invLayout),
                    adomin_invoice_item_lines(array('items' => $ibSheet['rows']))
                );
                ?>
                <?php
                /*
                 * The sheet itself is opened by adomin_invoice_band_loop(), and
                 * Banner's document frame goes inside it - 'pageOpen' / 'pageClose'
                 * rather than a div wrapped round the call. Outside the sheet the
                 * frame belongs to whichever page happens to follow it, so with
                 * more than one page the second sheet had no frame at all.
                 */
                adomin_invoice_band_loop($ibBlocks, $ibGrid, $invLayout, $ibPage, $ibSheet + array(
                    'pageOpen' => '<div class="ib-doc av-doc-edge'
                        . (!empty($invLayout['edge']) && $invLayout['edge'] !== 'none' ? ' has-edge' : '')
                        . '" style="--ib-accent: ' . $invAccent[0] . '; --ib-tint: ' . $invAccent[1] . ';">',
                    'pageClose' => '</div>',
                ));
            }
            ?>
    </div>
</div>
