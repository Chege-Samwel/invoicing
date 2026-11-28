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
 Layout from the settings pane - same rules as the Creative design. */
/* the account, not the viewer: a share link must show the same document */
/*
 * Fill in the fields this document does not carry.
 *
 * Each design builds its own bands, so each reads the snapshot directly. One
 * declared field list, applied on the way in, is what keeps a partial document
 * from costing a band - see adomin_invoice_fields().
 */
$invoice = adomin_invoice_fields($invoice);
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
        'header' => function () use ($invoice, $invLayout, $firmInfo, $adomLabel) {
            /*
             * Same letterhead as every other design: sender, receiver and the
             * verification code in one row, from anvoice_doc_helper.php. The
             * customer used to be a separate stack under the invoice numbers,
             * one field per line, with the code nowhere on some designs.
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
            echo adomin_invoice_parties($invoice, $invLayout, $adominLogo, 66);
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
    <table class="table items-table">
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
            $tsRowNo = (int) $adominSheet['rowOffset'];
            foreach ($adominSheet['rows'] as $item) {
                if (preg_match('/^\s*[^:]{2,40}:\s*$/', (string) (isset($item['name']) && $item['name'] !== '' ? $item['name'] : (isset($item['item']) ? $item['item'] : '')))) {
                    /* a group heading is a band, not a numbered row */
                    ?>
                    <tr class="adv-group"><td colspan="4"><?= html_escape(rtrim(trim((isset($item['name']) && $item['name'] !== '' ? $item['name'] : (isset($item['item']) ? $item['item'] : ''))), ':')) ?></td></tr>
                    <?php
                    continue;
                }
                $tsRowNo++;
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
                        <?= $adomLabel('everything', 'Subtotal') ?>
                    </td>
                    <td class="total-price">
                        <?= $invoice['final_total'] ?>
                    </td>
                </tr>
                <tr class="info-line">
                    <td class="info">
                        <?= $adomLabel('amount', 'Total') ?>
                    </td>
                    <td class="av-sum-figure">
                        <?= $invoice['invoice_amount'] ?>
                    </td>
                </tr>
                <tr class="info-line">
                    <td class="info">
                        <?= $adomLabel('discount', 'Discount') ?>
                    </td>
                    <td class="av-sum-figure">
                        <?= $invoice['discount'] . ' ' . $invoice['discount_type'] ?>
                    </td>
                </tr>
                <tr class="info-line">
                    <td class="info">
                        <?= $adomLabel('tax_base', 'Tax base') ?>
                    </td>
                    <td class="av-sum-figure">
                        <?= $invoice['tax_base'] ?>
                    </td>
                </tr>
                <?php if ($invoice['no_vat'] == 1) { ?>
                    <tr class="info-line">
                        <td class="info">
                            <?= $adomLabel('vat_charget', 'VAT') ?>
                        </td>
                        <td class="av-sum-figure">
                            <?= $invoice['no_vat_reason'] ?>
                        </td>
                    </tr>
                <?php } else { ?>
                    <tr class="info-line">
                        <td class="info">
                            <?= $adomLabel('percentage_vat', 'VAT') ?> <?= $invoice['vat_percent'] ?>%
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
    $tsBeneficiary = (isset($invoice['client']['is_to_person']) ? $invoice['client']['is_to_person'] : '') == 0
        ? (isset($invoice['client']['recipient_name']) ? $invoice['client']['recipient_name'] : '')
        : (isset($invoice['client']['client_name']) ? $invoice['client']['client_name'] : '');
    /*
     * The beneficiary is the party the document is addressed to, and the
     * "Bill to" block has already named it. Printing it again under the totals
     * reads as a second payee, so it stays only when it is genuinely somebody
     * else - and it is folded shut so it does not compete with the figures.
     *
     * It used to be compared against the SENDER's name, which can never match:
     * the beneficiary is the client. So the duplicate was on every invoice.
     */
    $tsRecipientNamed = (isset($invoice['client']['is_to_person']) ? $invoice['client']['is_to_person'] : '') == 0
        ? (isset($invoice['client']['recipient_name']) ? $invoice['client']['recipient_name'] : '')
        : (isset($invoice['client']['client_name']) ? $invoice['client']['client_name'] : '');
    $tsShowBeneficiary = $invLayout['showBeneficiary'] === '1'
        && $tsBeneficiary !== ''
        && strcasecmp(trim($tsBeneficiary), trim((string) $tsRecipientNamed)) !== 0;
    ?>
    <div class="inv-drawers">
        <?php if ($tsShowBeneficiary) { ?>
            <details open>
                <summary><?= $adomLabel('recipient', 'Recipient') ?></summary>
                <div class="inv-drawer-body"><p><?= html_escape($tsBeneficiary) ?></p></div>
            </details>
        <?php } ?>
        <?php if (!empty($invoice['payment_method'])) { ?>
            <details open>
                <summary><?= $adomLabel('payment_type', 'Payment') ?></summary>
                <div class="inv-drawer-body"><p><?= html_escape($invoice['payment_method']) ?></p></div>
            </details>
        <?php } ?>
        <?php if (!empty($invoice['composed'])) { ?>
            <details open>
                <summary><?= $invoice['translation']['compiled'] ?></summary>
                <div class="inv-drawer-body"><p><?= html_escape($invoice['composed']) ?></p></div>
            </details>
        <?php } ?>
    </div>
            <?php
        },
        'signature' => function () use ($invoice, $adomLabel) {
            ?>
    <div class="inv-drawers">
        <details open>
            <summary><?= $adomLabel('signature', 'Signature') ?></summary>
            <div class="inv-drawer-body">
                <?php /* a line to sign on; the summary above already says Signature */ ?>
                <span class="inv-sign-line"></span>
            </div>
        </details>
    </div>
            <?php
        },
        'remarks' => function () use ($invoice, $invLayout, $adomLabel) {
            ?>
    <?php if ($invLayout['showRemarks'] === '1' && trim((string) $invoice['remarks']) !== '') { ?>
        <div class="remarks">
            <p class="remarks-cap"><?= $adomLabel('remarks', 'Remarks') ?></p>
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