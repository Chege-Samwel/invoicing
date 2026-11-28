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
 Layout from the settings pane - same rules as the Creative design. */
/* the account, not the viewer: a share link must show the same document */
$invLayout = adomin_invoice_layout(NULL, isset($invoice['for_user']) ? (int) $invoice['for_user'] : NULL);
?>
<?= adomin_invoice_layout_css($invLayout) ?>
<?php $adominPage = adomin_invoice_page(); ?>
<div class="invoice-toner-save">
    <?php
    /* Rendered in the order chosen on the template canvas. */
    /*
     * The sheet the closures draw, bound by reference: the blocks are built
     * once and then drawn once per page, so the item block has to see the slice
     * belonging to the page currently being drawn.
     */
    $adominSheet = null;

    $adominBlocks = array(
        'header' => function () use ($invoice, $invLayout, $firmInfo) {
            /*
             * Same letterhead as every other design: sender, receiver and the
             * verification code in one row, from anvoice_doc_helper.php. The
             * customer used to be a separate stack under the invoice numbers,
             * one field per line, with the code nowhere on some designs.
             */
            $adominLogo = function_exists('adomin_firm_logo_src') ? adomin_firm_logo_src($invoice, $firmInfo) : (($invoice['firm']['image'] != null && $firmInfo['show_logo'] == 1) ? base_url('attachments/companiesimages/' . $firmInfo['id'] . '/' . $invoice['firm']['image']) : '');
            echo adomin_invoice_parties($invoice, $invLayout, $adominLogo, 66);
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
        'items' => function () use ($invoice, &$adominSheet) {
            ?>
    <table class="table items-table">
        <thead>
            <tr>
                <th><?= $invoice['translation']['products_name'] ?></th>
                <th><?= $invoice['translation']['quantity'] ?></th>
                <th class="text-right"><?= $invoice['translation']['single_price'] ?></th>
                <th class="text-right"><?= $invoice['translation']['value'] ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $tsRowNo = (int) $adominSheet['rowOffset'];
            foreach ($adominSheet['rows'] as $item) {
                if (preg_match('/^\s*[^:]{2,40}:\s*$/', (string) $item['name'])) {
                    /* a group heading is a band, not a numbered row */
                    ?>
                    <tr class="adv-group"><td colspan="4"><?= html_escape(rtrim(trim($item['name']), ':')) ?></td></tr>
                    <?php
                    continue;
                }
                $tsRowNo++;
                ?>
                <tr>
                    <td class="item-name"><?= $item['name'] ?></td>
                    <td><?= $item['quantity'] . ' ' . $item['quantity_type'] ?></td>
                    <td class="text-right"><?= $item['single_price'] ?></td>
                    <td class="text-right"><?= $item['total_price'] ?></td>
                </tr> 
            <?php } ?>
        </tbody>
    </table>
            <?php if (!empty($adominSheet['continued'])) { ?>
                <p class="adv-continued"><?= html_escape(str_replace(
                    array('{page}', '{pages}'),
                    array((int) $adominSheet['pageNo'] + 1, (int) $adominSheet['pageCount']),
                    isset($invoice['translation']['continued']) ? $invoice['translation']['continued'] : 'Continued on page {page} of {pages}'
                )) ?></p>
            <?php } ?>
            <?php
        },
        'totals' => function () use ($invoice, $invLayout) {
            ?>
    <div class="invoice-payments">
        <div class="invoice-totals">
            <table>
                <?php /*
                    The same order as every other design: the total answers the
                    question, the workings support it. This template had the
                    total at the bottom, so the one figure a reader wants was
                    the last one they reached - and three designs then read
                    differently from the rest.
                */ ?>
                <tr class="final-total">
                    <td class="info">
                        <?= $invoice['translation']['everything'] ?>
                    </td>
                    <td class="total-price">
                        <?= $invoice['final_total'] ?>
                    </td>
                </tr>
                <tr class="info-line">
                    <td class="info">
                        <?= $invoice['translation']['amount'] ?>
                    </td>
                    <td class="av-sum-figure">
                        <?= $invoice['invoice_amount'] ?>
                    </td>
                </tr>
                <tr class="info-line">
                    <td class="info">
                        <?= $invoice['translation']['discount'] ?>
                    </td>
                    <td class="av-sum-figure">
                        <?= $invoice['discount'] . ' ' . $invoice['discount_type'] ?>
                    </td>
                </tr>
                <tr class="info-line">
                    <td class="info">
                        <?= $invoice['translation']['tax_base'] ?>
                    </td>
                    <td class="av-sum-figure">
                        <?= $invoice['tax_base'] ?>
                    </td>
                </tr>
                <?php if ($invoice['no_vat'] == 1) { ?>
                    <tr class="info-line">
                        <td class="info">
                            <?= $invoice['translation']['vat_charget'] ?>
                        </td>
                        <td class="av-sum-figure">
                            <?= $invoice['no_vat_reason'] ?>
                        </td>
                    </tr>
                <?php } else { ?>
                    <tr class="info-line">
                        <td class="info">
                            <?= $invoice['translation']['percentage_vat'] ?> <?= $invoice['vat_percent'] ?>%
                        </td>
                        <td class="av-sum-figure">
                            <?= $invoice['vat_sum'] ?>
                        </td>
                    </tr>
                <?php } ?>
            </table>
            <?php /* the currency, stated once instead of after every figure */ ?>
            <p class="av-sum-currency"><?= html_escape($invoice['inv_currency']) ?></p>
        </div>
        <div class="clearfix"></div>
    </div>
    <?php
    /*
     * Same drawers as the Creative design. The stacked one-line paragraphs
     * pushed the remarks off the bottom of the sheet and read at body size,
     * which made reference detail - payment type, the beneficiary, who
     * prepared it - look as important as what the document is for.
     */
    $tsBeneficiary = $invoice['client']['is_to_person'] == 0
        ? $invoice['client']['recipient_name']
        : $invoice['client']['client_name'];
    /*
     * The beneficiary is the party the document is addressed to, and the
     * "Bill to" block has already named it. Printing it again under the totals
     * reads as a second payee, so it stays only when it is genuinely somebody
     * else - and it is folded shut so it does not compete with the figures.
     *
     * It used to be compared against the SENDER's name, which can never match:
     * the beneficiary is the client. So the duplicate was on every invoice.
     */
    $tsRecipientNamed = $invoice['client']['is_to_person'] == 0
        ? $invoice['client']['recipient_name']
        : $invoice['client']['client_name'];
    $tsShowBeneficiary = $invLayout['showBeneficiary'] === '1'
        && $tsBeneficiary !== ''
        && strcasecmp(trim($tsBeneficiary), trim((string) $tsRecipientNamed)) !== 0;
    ?>
    <div class="inv-drawers">
        <?php if ($tsShowBeneficiary) { ?>
            <details>
                <summary><?= $invoice['translation']['recipient'] ?></summary>
                <div class="inv-drawer-body"><p><?= html_escape($tsBeneficiary) ?></p></div>
            </details>
        <?php } ?>
        <?php if (!empty($invoice['payment_method'])) { ?>
            <details>
                <summary><?= $invoice['translation']['payment_type'] ?></summary>
                <div class="inv-drawer-body"><p><?= html_escape($invoice['payment_method']) ?></p></div>
            </details>
        <?php } ?>
        <?php if (!empty($invoice['composed'])) { ?>
            <details>
                <summary><?= $invoice['translation']['compiled'] ?></summary>
                <div class="inv-drawer-body"><p><?= html_escape($invoice['composed']) ?></p></div>
            </details>
        <?php } ?>
    </div>
            <?php
        },
        'signature' => function () use ($invoice) {
            ?>
    <div class="inv-drawers">
        <details>
            <summary><?= $invoice['translation']['signature'] ?></summary>
            <div class="inv-drawer-body">
                <?php /* a line to sign on; the summary above already says Signature */ ?>
                <span class="inv-sign-line"></span>
            </div>
        </details>
    </div>
            <?php
        },
        'remarks' => function () use ($invoice, $invLayout) {
            ?>
    <?php if ($invLayout['showRemarks'] === '1' && trim((string) $invoice['remarks']) !== '') { ?>
        <div class="remarks">
            <p class="remarks-cap"><?= $invoice['translation']['remarks'] ?></p>
            <p><?= nl2br(html_escape($invoice['remarks'])) ?></p>
        </div>
    <?php } ?>
    <?php
    $tsNote = trim((string) $invLayout['footerNote']);
    if ($tsNote === '') {
        $tsNote = 'The prices displayed are estimates, and are subject to change without previous notice.';
    }
    ?>
    <p class="inv-note"><?= html_escape($tsNote) ?></p>
    <?php /*
        The verification code moved to the letterhead, opposite the company
        details - see the header block above. One code per sheet.
    */ ?>
            <?php
        },
    );
    /*
     * Each section in its band on the page - see adomin_invoice_grid(), and
     * adomin_invoice_band_loop() for the loop itself. This template used to
     * walk the sections on its own; it shares the loop with the other designs
     * now, so a page that paginates paginates on all six.
     */
    $adominSheets = adomin_invoice_pages($invoice, $invLayout);
    foreach ($adominSheets as $adominSheet) {
        $adominGrid = adomin_invoice_grid_fit(
            adomin_invoice_grid($invLayout),
            adomin_invoice_item_lines(array('items' => $adominSheet['rows']))
        );
        adomin_invoice_band_loop($adominBlocks, $adominGrid, $invLayout, $adominPage, $adominSheet);
    }
?>
</div>