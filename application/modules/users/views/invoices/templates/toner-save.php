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
  <div class="adv-page adv-page-final" data-av-page="1"
       style="--av-pw:<?= (int) $adominPage[0] ?>pt;--av-ph:<?= (int) $adominPage[1] ?>pt;">
    <?php
    /* Rendered in the order chosen on the template canvas. */
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
        'items' => function () use ($invoice) {
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
     * The beneficiary is the sender, and the sender is already named at the top
     * of the sheet. Printing the same name again under the totals reads as a
     * second payee. It stays only when it is a different entity - a trading
     * name that receives the money - and it is folded shut so it does not
     * compete with the figures.
     */
    $tsShowBeneficiary = $invLayout['showBeneficiary'] === '1'
        && $tsBeneficiary !== ''
        && strcasecmp(trim($tsBeneficiary), trim($invoice['firm']['name'])) !== 0;
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
    /* Each section in its band on the page - see adomin_invoice_grid(). */
    $adominGrid = adomin_invoice_grid_for($invoice, $invLayout);
    foreach (adomin_invoice_sections($invLayout) as $adominSection) {
        if (!isset($adominBlocks[$adominSection])) {
            continue;
        }
        if ($adominSection === 'header' || !isset($adominGrid[$adominSection])) {
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