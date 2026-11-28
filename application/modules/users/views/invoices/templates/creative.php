<?php
/*
 * The document's labels, read from a snapshot that may be partial.
 *
 * A missing translation row - an old document, an imported one, one whose
 * snapshot was never written - used to turn the first read into a notice where
 * a column heading belongs, and inside the page buffer that can cost the band.
 * One closure with English fallbacks, defined before anything prints.
 */
$adomLabels = isset($invoice['translation']) && is_array($invoice['translation']) ? $invoice['translation'] : array();
$adomLabel = function ($key, $fallback) use ($adomLabels) {
    return (isset($adomLabels[$key]) && $adomLabels[$key] !== '') ? $adomLabels[$key] : $fallback;
};
?>
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
 * Layout comes from the settings pane, so a change there reaches the printed
 * document and the preview at the same time.
 *
 * The account, not the viewer: a share link must show the same document.
 */
/*
 * Fill in the fields this document does not carry.
 *
 * Each design builds its own bands, so each reads the snapshot directly. One
 * declared field list, applied on the way in, is what keeps a partial document
 * from costing a band - see adomin_invoice_fields().
 */
$invoice = adomin_invoice_fields($invoice);
$invLayout = adomin_invoice_layout(NULL, isset($invoice['for_user']) ? (int) $invoice['for_user'] : NULL);
$invAccent = adomin_invoice_accent($invLayout['accent']);
?>
<?= adomin_invoice_layout_css($invLayout) ?>
<?php
/*
 * The page. Creative draws its own layout rather than going through
 * adomin_invoice_render(), so it opens the same A4 sheet here - otherwise the
 * document is as tall as its content and there is no page to preview.
 */
$adominPage = adomin_invoice_page();
?>
<div class="main-inv-container">
    <div class="invoice-box">
            <?php
            /*
             * The sheet the closures draw, bound by reference: the blocks are
             * built once and then drawn once per page, so the item block has
             * to see the slice belonging to the page currently being drawn.
             */
            $adominSheet = null;

            /*
             * The blocks below are rendered in the order chosen on the
             * template canvas. Each one is a closure holding the markup that
             * was already here, so reordering changes position and nothing else.
             */
            $adominBlocks = array(
                'header' => function () use ($invoice, $invLayout, $firmInfo, $adomLabel) {
                    /*
                     * Who sent it, to whom, and the code that proves it - in one
                     * row. The markup lives in anvoice_doc_helper.php so every
                     * template draws the same letterhead; the templates had each
                     * grown their own, and each had the customer stacked under
                     * the company with one field per line.
                     */
                    /*
                     * The fallback read (isset($invoice['firm']['image']) ? $invoice['firm']['image'] : '') directly, so a
                     * document with no firm snapshot - an imported one, or one
                     * whose row was never written - turned the letterhead into a
                     * notice. adomin_firm_logo_src() is the one place that knows
                     * how to find a logo; when it is missing, so is the logo.
                     */
                    $adominLogo = function_exists('adomin_firm_logo_src')
                        ? adomin_firm_logo_src($invoice, $firmInfo)
                        : '';
                    echo adomin_invoice_parties($invoice, $invLayout, $adominLogo, 74);
                    ?>
            <?php if ($invLayout['currencyPos'] === 'header') { ?>
                <p class="av-doc-currency"><?= $adomLabel('currency', 'Currency') ?>:
                    <b><?= $invoice['inv_currency'] ?></b></p>
            <?php } ?>
            <div class="row-invoice-info">
                <?= adomin_invoice_meta($invoice) ?>
            </div>
                    <?php
                },
                                'items' => function () use ($invoice, &$adominSheet, $adomLabel) {
                    ?>
            <table class="table table-striped items-table">
                <thead>
                    <tr>
                        <th><?= $adomLabel('products_name', 'Item') ?></th>
                        <th><?= $adomLabel('quantity', 'Qty') ?></th>
                        <th class="text-right"><?= $adomLabel('single_price', 'Unit price') ?></th>
                        <th class="text-right"><?= $adomLabel('value', 'Amount') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    /*
                     * Only the rows on this sheet, numbered from where the sheet
                     * starts - see adomin_invoice_pages(). Printing the whole
                     * table on every page was not an option, and neither was
                     * clipping it: the rows past the printable height vanished
                     * without a mark.
                     */
                    $invRowNo = (int) $adominSheet['rowOffset'];
                    foreach ($adominSheet['rows'] as $item) {
                        if (preg_match('/^\s*[^:]{2,40}:\s*$/', (string) (isset($item['name']) && $item['name'] !== '' ? $item['name'] : (isset($item['item']) ? $item['item'] : '')))) {
                            /* a group heading is a band, not a numbered row */
                            ?>
                            <tr class="adv-group"><td colspan="4"><?= html_escape(rtrim(trim((isset($item['name']) && $item['name'] !== '' ? $item['name'] : (isset($item['item']) ? $item['item'] : ''))), ':')) ?></td></tr>
                            <?php
                            continue;
                        }
                        $invRowNo++;
                        ?>
                        <tr>
                            <td class="item-name"><?= (isset($item['name']) && $item['name'] !== '' ? $item['name'] : (isset($item['item']) ? $item['item'] : '')) ?></td>
                            <td><?= (isset($item['quantity']) ? $item['quantity'] : '') . ' ' . (isset($item['quantity_type']) ? $item['quantity_type'] : '') ?></td>
                            <td class="text-right"><?= (isset($item['single_price']) ? $item['single_price'] : '0') ?></td>
                            <td class="text-right"><?= (isset($item['total_price']) ? $item['total_price'] : (isset($item['value']) ? $item['value'] : '0')) ?></td>
                        </tr> 
                    <?php } ?>
                </tbody>
            </table>
                    <?php if (!empty($adominSheet['continued'])) { ?>
                        <p class="adv-continued"><?= html_escape(str_replace(
                            array('{page}', '{pages}'),
                            array((int) $adominSheet['pageNo'] + 1, (int) $adominSheet['pageCount']),
                            $adomLabel('continued', 'Continued on page {page} of {pages}')
                        )) ?></p>
                    <?php } ?>
                    <?php
                },
                'totals' => function () use ($invoice, $invLayout, $adomLabel) {
                    ?>
            <div class="invoice-payments">
                <?php
                /*
                 * The totals, as a right-hand block.
                 *
                 * Three things were wrong with this table and are fixed here:
                 *
                 *   1. It repeated the invoice amount at the top, which is the
                 *      sum of the items, and then showed the tax base, and then
                 *      the total - three lines that all look like "the total".
                 *      The first two are working-out, so they are set small and
                 *      quiet and only the last one is loud.
                 *
                 *   2. It sat in its band with the generic float rules still
                 *      applied, so on a short invoice it drifted left and the
                 *      figures were not on one edge. Everything is right-edged
                 *      in a fixed column now.
                 *
                 *   3. The currency was glued to every figure ("138000.00 KES"
                 *      four times), which is noise in a column that is already
                 *      labelled. It is stated once, under the total.
                 */
                ?>
                <div class="invoice-totals">
                    <table>
                        <tr class="av-sum-line">
                            <td class="info">
                                <?= $adomLabel('amount', 'Total') ?>
                            </td>
                            <td class="av-sum-figure">
                                <?= $invoice['invoice_amount'] ?>
                            </td>
                        </tr>
                        <tr class="av-sum-line">
                            <td class="info">
                                <?= $adomLabel('discount', 'Discount') ?>
                            </td>
                            <td class="av-sum-figure">
                                <?= $invoice['discount'] . ' ' . $invoice['discount_type'] ?>
                            </td>
                        </tr>
                        <tr class="av-sum-line">
                            <td class="info">
                                <?= $adomLabel('tax_base', 'Tax base') ?>
                            </td>
                            <td class="av-sum-figure">
                                <?= $invoice['tax_base'] ?>
                            </td>
                        </tr>
                        <?php if ($invoice['no_vat'] == 1) { ?>
                            <tr class="av-sum-line">
                                <td class="info">
                                    <?= $adomLabel('vat_charget', 'VAT') ?>
                                </td>
                                <td class="av-sum-figure">
                                    <?= $invoice['no_vat_reason'] ?>
                                </td>
                            </tr>
                        <?php } else { ?>
                            <tr class="av-sum-line">
                                <td class="info">
                                    <?= $adomLabel('percentage_vat', 'VAT') ?> <?= $invoice['vat_percent'] ?>%
                                </td>
                                <td class="av-sum-figure">
                                    <?= $invoice['vat_sum'] ?>
                                </td>
                            </tr>
                        <?php } ?>
                        <tr class="final-total">
                            <td class="info">
                                <?= $adomLabel('everything', 'Subtotal') ?>
                            </td>
                            <td class="total-price">
                                <?= $invoice['final_total'] ?>
                            </td>
                        </tr>
                    </table>
                    <p class="av-sum-currency"><?= html_escape($invoice['inv_currency']) ?></p>
                </div>
                <div class="clearfix"></div>
            </div>
            <?php
            /*
             * Payment type, beneficiary and compiled-by used to be three
             * separate one-line paragraphs stacked under the totals, each with
             * its own float and clearfix, each at the body font size. Between
             * them they pushed the remarks - the part a client actually reads -
             * to the very bottom of the page, under four lines of reference
             * detail.
             *
             * They are drawers now: folded shut unless someone opens them, in
             * 12px, as one block. <details> is used rather than a script
             * because it works before JavaScript, works in the PDF, and a
             * printed sheet shows the open state.
             *
             * The beneficiary is NOT repeated here. It is the party the
             * document is addressed to, which the "Bill to" block on the left
             * has already named - so printing it again reads as a second payee.
             *
             * This compared against the SENDER's name, so it never matched: the
             * beneficiary is the client, and the client is never the firm. The
             * result was that the duplicate shipped on every invoice. It is
             * compared against the recipient now, and kept only when it is
             * genuinely somebody else.
             */
            $invBeneficiary = (isset($invoice['client']['is_to_person']) ? $invoice['client']['is_to_person'] : '') == 0
                ? (isset($invoice['client']['recipient_name']) ? $invoice['client']['recipient_name'] : '')
                : (isset($invoice['client']['client_name']) ? $invoice['client']['client_name'] : '');
            $invRecipientNamed = (isset($invoice['client']['is_to_person']) ? $invoice['client']['is_to_person'] : '') == 0
                ? (isset($invoice['client']['recipient_name']) ? $invoice['client']['recipient_name'] : '')
                : (isset($invoice['client']['client_name']) ? $invoice['client']['client_name'] : '');
            $invShowBeneficiary = $invLayout['showBeneficiary'] === '1'
                && $invBeneficiary !== ''
                && strcasecmp(trim($invBeneficiary), trim((string) $invRecipientNamed)) !== 0;
            ?>
            <div class="inv-drawers">
                <?php if ($invShowBeneficiary) { ?>
                    <details>
                        <summary><?= $adomLabel('recipient', 'Recipient') ?></summary>
                        <div class="inv-drawer-body">
                            <p><?= html_escape($invBeneficiary) ?></p>
                        </div>
                    </details>
                <?php } ?>
                <?php if (!empty($invoice['payment_method'])) { ?>
                    <details>
                        <summary><?= $adomLabel('payment_type', 'Payment') ?></summary>
                        <div class="inv-drawer-body">
                            <p><?= html_escape($invoice['payment_method']) ?></p>
                        </div>
                    </details>
                <?php } ?>
                <?php if (!empty($invoice['composed'])) { ?>
                    <details>
                        <summary><?= $invoice['translation']['compiled'] ?></summary>
                        <div class="inv-drawer-body">
                            <p><?= html_escape($invoice['composed']) ?></p>
                        </div>
                    </details>
                <?php } ?>
            </div>
                    <?php
                },
                                'signature' => function () use ($invoice, $adomLabel) {
                    ?>
            <?php
            /*
             * A rule to sign on, not the word "Signature" printed twice.
             *
             * The drawer's own summary already says Signature; the body then
             * repeated the label before the dotted line, so the sheet read
             * "Signature / Signature: ........".
             */
            ?>
            <div class="inv-sign">
                <span class="inv-sign-line"></span>
                <span class="inv-sign-cap"><?= html_escape($adomLabel('signature', 'Signature')) ?></span>
            </div>
                    <?php
                },
                'remarks' => function () use ($invoice, $invLayout, $adomLabel) {
                    ?>
            <?php
            /*
             * Remarks last, at the foot of the document. Everything above them
             * is reference material that folds away; this is the one thing on
             * the page that was written for this client in particular.
             */
            ?>
            <?php if ($invLayout['showRemarks'] === '1' && trim((string) $invoice['remarks']) !== '') { ?>
                <div class="remarks">
                    <p class="remarks-cap"><?= $adomLabel('remarks', 'Remarks') ?></p>
                    <p><?= nl2br(html_escape($invoice['remarks'])) ?></p>
                </div>
            <?php } ?>
            <?php
            /*
             * The standing line about the figures, printed once.
             *
             * This block used to emit it twice: a div when the business had
             * written their own, and then a paragraph with the same text or the
             * fallback - so a sheet with a custom note carried it twice, and a
             * sheet without one carried the fallback directly under an empty
             * div. One variable, one line.
             */
            $invNote = trim((string) $invLayout['footerNote']);
            if ($invNote === '') {
                $invNote = 'The prices displayed are estimates, and are subject to change without previous notice.';
            }
            ?>
            <p class="inv-note"><?= html_escape($invNote) ?></p>
            <?php /*
                The verification code moved to the letterhead, opposite the
                company details. A second copy here would print the same code
                twice on one sheet.
            */ ?>
                    <?php
                },
            );
            /*
             * Every section lands in its band on the page. Without this the
             * blocks flowed one after another, so an invoice with thirty items
             * pushed the totals off the bottom of the sheet.
             */
            /*
             * The grid the invoice needs, not the grid the template declares:
             * the item band takes the height its rows take, and the totals,
             * signature and remarks move up under it. Without this a short
             * invoice left a large empty table box and a long one had its
             * bottom rows clipped away by the page.
             */
            /*
             * One sheet per page the invoice needs, laid out by the shared
             * loop - see adomin_invoice_band_loop(). This template used to walk
             * the sections itself, in a loop that had drifted from the shared
             * one.
             */
            $adominSheets = adomin_invoice_pages($invoice, $invLayout);
            foreach ($adominSheets as $adominSheet) {
                $adominGrid = adomin_invoice_grid_fit(
                    adomin_invoice_grid($invLayout),
                    adomin_invoice_item_lines(array('items' => $adominSheet['rows']))
                );
                adomin_invoice_band_loop($adominBlocks, $adominGrid, $invLayout, $adominPage, $adominSheet + array(
                    'pageOpen' => '<div class="invoice-creative" style="border-top:3px solid ' . $invAccent[0] . ';">',
                    'pageClose' => '</div>',
                    'headerClass' => 'adv-header',
                ));
            }
            ?>
    </div>
</div>