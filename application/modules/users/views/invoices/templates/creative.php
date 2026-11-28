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
      <div class="adv-page adv-page-final" data-av-page="1"
           style="--av-pw:<?= (int) $adominPage[0] ?>pt;--av-ph:<?= (int) $adominPage[1] ?>pt;">
        <div class="invoice-creative" style="border-top:3px solid <?= $invAccent[0] ?>;">
            <?php
            /*
             * The blocks below are rendered in the order chosen on the
             * template canvas. Each one is a closure holding the markup that
             * was already here, so reordering changes position and nothing else.
             */
            $adominBlocks = array(
                'header' => function () use ($invoice, $invLayout, $firmInfo) {
                    /*
                     * Who sent it, to whom, and the code that proves it - in one
                     * row. The markup lives in anvoice_doc_helper.php so every
                     * template draws the same letterhead; the templates had each
                     * grown their own, and each had the customer stacked under
                     * the company with one field per line.
                     */
                    $adominLogo = function_exists('adomin_firm_logo_src') ? adomin_firm_logo_src($invoice, $firmInfo) : (($invoice['firm']['image'] != null && $firmInfo['show_logo'] == 1) ? base_url('attachments/companiesimages/' . $firmInfo['id'] . '/' . $invoice['firm']['image']) : '');
                    echo adomin_invoice_parties($invoice, $invLayout, $adominLogo, 74);
                    ?>
            <?php if ($invLayout['currencyPos'] === 'header') { ?>
                <p class="av-doc-currency"><?= $invoice['translation']['currency'] ?? 'Currency' ?>:
                    <b><?= $invoice['inv_currency'] ?></b></p>
            <?php } ?>
            <div class="row-invoice-info">
                <?= adomin_invoice_meta($invoice) ?>
            </div>
                    <?php
                },
                                'items' => function () use ($invoice) {
                    ?>
            <table class="table table-striped items-table">
                <thead>
                    <tr>
                        <th><?= $invoice['translation']['products_name'] ?></th>
                        <th><?= $invoice['translation']['quantity'] ?></th>
                        <th class="text-right"><?= $invoice['translation']['single_price'] ?></th>
                        <th class="text-right"><?= $invoice['translation']['value'] ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoice['items'] as $item) { ?>
                        <tr>
                            <td class="item-name"><?= $item['name'] ?></td>
                            <td><?= $item['quantity'] . ' ' . $item['quantity_type'] ?></td>
                            <td class="text-right"><?= $item['single_price'] . ' ' . $invoice['inv_currency'] ?></td>
                            <td class="text-right"><?= $item['total_price'] . ' ' . $invoice['inv_currency'] ?></td>
                        </tr> 
                    <?php } ?>
                </tbody>
            </table>
                    <?php
                },
                'totals' => function () use ($invoice, $invLayout) {
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
                                <?= $invoice['translation']['amount'] ?>
                            </td>
                            <td class="av-sum-figure">
                                <?= $invoice['invoice_amount'] ?>
                            </td>
                        </tr>
                        <tr class="av-sum-line">
                            <td class="info">
                                <?= $invoice['translation']['discount'] ?>
                            </td>
                            <td class="av-sum-figure">
                                <?= $invoice['discount'] . ' ' . $invoice['discount_type'] ?>
                            </td>
                        </tr>
                        <tr class="av-sum-line">
                            <td class="info">
                                <?= $invoice['translation']['tax_base'] ?>
                            </td>
                            <td class="av-sum-figure">
                                <?= $invoice['tax_base'] ?>
                            </td>
                        </tr>
                        <?php if ($invoice['no_vat'] == 1) { ?>
                            <tr class="av-sum-line">
                                <td class="info">
                                    <?= $invoice['translation']['vat_charget'] ?>
                                </td>
                                <td class="av-sum-figure">
                                    <?= $invoice['no_vat_reason'] ?>
                                </td>
                            </tr>
                        <?php } else { ?>
                            <tr class="av-sum-line">
                                <td class="info">
                                    <?= $invoice['translation']['percentage_vat'] ?> <?= $invoice['vat_percent'] ?>%
                                </td>
                                <td class="av-sum-figure">
                                    <?= $invoice['vat_sum'] ?>
                                </td>
                            </tr>
                        <?php } ?>
                        <tr class="final-total">
                            <td class="info">
                                <?= $invoice['translation']['everything'] ?>
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
             * The beneficiary is NOT repeated here: it is already named on the
             * left of the letterhead as the sender, and printing the same name
             * twice on one sheet reads as two different payees. It stays in the
             * payment drawer only when it differs from the company name.
             */
            $invBeneficiary = $invoice['client']['is_to_person'] == 0
                ? $invoice['client']['recipient_name']
                : $invoice['client']['client_name'];
            $invShowBeneficiary = $invLayout['showBeneficiary'] === '1'
                && $invBeneficiary !== ''
                && strcasecmp(trim($invBeneficiary), trim($invoice['firm']['name'])) !== 0;
            ?>
            <div class="inv-drawers">
                <?php if ($invShowBeneficiary) { ?>
                    <details>
                        <summary><?= $invoice['translation']['recipient'] ?></summary>
                        <div class="inv-drawer-body">
                            <p><?= html_escape($invBeneficiary) ?></p>
                        </div>
                    </details>
                <?php } ?>
                <?php if (!empty($invoice['payment_method'])) { ?>
                    <details>
                        <summary><?= $invoice['translation']['payment_type'] ?></summary>
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
                                'signature' => function () use ($invoice) {
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
                <span class="inv-sign-cap"><?= html_escape($invoice['translation']['signature']) ?></span>
            </div>
                    <?php
                },
                'remarks' => function () use ($invoice, $invLayout) {
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
                    <p class="remarks-cap"><?= $invoice['translation']['remarks'] ?></p>
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
            $adominGrid = adomin_invoice_grid_for($invoice, $invLayout);
            foreach (adomin_invoice_sections($invLayout) as $adominSection) {
                if (!isset($adominBlocks[$adominSection])) {
                    continue;
                }
                if ($adominSection === 'header') {
                    /*
                     * The letterhead is placed, not flowed.
                     *
                     * Every other block is absolutely positioned on the sheet,
                     * so a header left in normal flow sat flush against the
                     * paper's own edge while the item table started 32pt in -
                     * one column indented, the other not. It shares the grid's
                     * horizontal inset now, and is capped above the item band.
                     */
                    echo '<div class="adv-header">';
                    $adominBlocks[$adominSection]();
                    echo '</div>';
                    continue;
                }
                if (!isset($adominGrid[$adominSection])) {
                    $adominBlocks[$adominSection]();
                    continue;
                }
                list($adominX, $adominY, $adominW, $adominH) = $adominGrid[$adominSection];
                echo '<div class="adv-band adv-band-' . $adominSection . '"'
                    . ' data-avc-block="' . $adominSection . '"'
                    . ' data-avc-label="' . html_escape(adomin_invoice_section_label($adominSection)) . '"'
                    . ' style="left:' . round($adominX * $adominPage[0], 2) . 'pt;'
                    . 'top:' . round($adominY * $adominPage[1], 2) . 'pt;'
                    . 'width:' . round($adominW * $adominPage[0], 2) . 'pt;'
                    . 'height:' . round($adominH * $adominPage[1], 2) . 'pt;">';
                $adominBlocks[$adominSection]();
                echo '</div>';
            }
            ?>
        </div>
      </div>
    </div>
</div>