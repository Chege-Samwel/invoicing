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
  <div class="adv-page" data-av-page="1"
       style="--av-pw:<?= (int) $adominPage[0] ?>pt;--av-ph:<?= (int) $adominPage[1] ?>pt;">
    <?php
    /* Rendered in the order chosen on the template canvas. */
    $adominBlocks = array(
        'header' => function () use ($invoice, $invLayout, $firmInfo) {
            ?>
    <div class="row-my-firm">
        <?php
        $adominLogo = function_exists('adomin_firm_logo_src') ? adomin_firm_logo_src($invoice, $firmInfo) : (($invoice['firm']['image'] != null && $firmInfo['show_logo'] == 1) ? base_url('attachments/companiesimages/' . $firmInfo['id'] . '/' . $invoice['firm']['image']) : '');
        $adominFirm = array(
            'name' => $invoice['firm']['name'],
            'bulstat' => $invoice['firm']['bulstat'],
            'vat_number' => $invoice['firm']['vat_number'],
            'is_vat_registered' => $invoice['firm']['is_vat_registered'],
            'address' => $invoice['firm']['address'],
            'city' => $invoice['firm']['city'],
            'accountable_person' => $invoice['firm']['accountable_person'],
        );
        // the code goes in the letterhead, opposite the company details
        echo adomin_invoice_letterhead(
            $invLayout,
            $adominFirm,
            $adominLogo,
            $invoice['translation'],
            adomin_invoice_qr_block($invoice, 66)
        );
        ?>
        <div class="clearfix"></div>
    </div>
    <?php if ($invLayout['currencyPos'] === 'header') { ?>
        <p class="av-doc-currency"><?= $invoice['translation']['currency'] ?? 'Currency' ?>:
            <b><?= $invoice['inv_currency'] ?></b></p>
    <?php } ?>
    <div class="row-invoice-info">
        <?php
        $to_inv = false;
        if ($invoice['inv_type'] == 'tax_inv') {
            $inv_type = $invoice['translation']['invoice'];
        }
        if ($invoice['inv_type'] == 'prof') {
            $inv_type = $invoice['translation']['pro_forma'];
        }
        if ($invoice['inv_type'] == 'debit') {
            $to_inv = true;
            $inv_type = $invoice['translation']['debit_note'];
        }
        if ($invoice['inv_type'] == 'credit') {
            $to_inv = true;
            $inv_type = $invoice['translation']['credit_note'];
        }
        ?>
        <span class="invoice-type"><?= $inv_type ?></span>
        <div class="info">
            <table>
                <?php if ($to_inv === true) { ?>
                    <tr>
                        <td class="head-td">
                            <?= $invoice['translation']['to_an_invoice'] ?>
                        </td>
                        <td>
                            <?= $invoice['to_inv_number'] ?>
                        </td>
                    </tr> 
                    <tr>
                        <td class="head-td">
                            <?= $invoice['translation']['from_date'] ?>
                        </td>
                        <td>
                            <?= date('d.m.Y', $invoice['to_inv_date']) ?>
                        </td>
                    </tr>

                <?php } ?>
                <tr>
                    <td class="head-td">
                        <?= $invoice['translation']['number'] ?>
                    </td>
                    <td>
                        <?= $invoice['inv_number'] ?>
                    </td>
                </tr>
                <tr>
                    <td class="head-td">
                        <?= $invoice['translation']['date_of_issue'] ?>
                    </td>
                    <td>
                        <?= date('d.m.Y', $invoice['date_create']) ?>
                    </td>
                </tr>
                <tr>
                    <td class="head-td">
                        <?= $invoice['translation']['a_date_of_a_tax_event'] ?>
                    </td>
                    <td>
                        <?= date('d.m.Y', $invoice['date_tax_event']) ?>
                    </td>
                </tr>
                <tr>
                    <td class="head-td">
                        <?= $invoice['translation']['amount'] ?>
                    </td>
                    <td>
                        <?= $invoice['final_total'] . ' ' . $invoice['inv_currency'] ?>
                    </td>
                </tr>
            </table>
            <div class="client-info">
                <p><?= $invoice['client']['client_name'] ?></p>
                <?php if ($invoice['client']['is_to_person'] == 0) { ?>
                    <p><?= $invoice['translation']['bulstat'] ?> <?= $invoice['client']['client_bulstat'] ?></p>
                <?php } else { ?>
                    <?= $invoice['client']['client_ident_num'] ?>
                <?php } if ($invoice['client']['client_vat_registered'] == 1) { ?>
                    <p><?= $invoice['client']['vat_number'] ?></p>
                <?php } ?>
                <p><?= $invoice['client']['client_address'] ?></p>
                <p><?= $invoice['client']['client_city'] ?></p>
                <p><?= $invoice['client']['client_country'] ?></p>
                <?php if ($invoice['client']['is_to_person'] == 0) { ?>
                    <p><?= $invoice['translation']['mol'] ?> <?= $invoice['client']['accountable_person'] ?></p> 
                <?php } ?>
            </div>
            <div class="clearfix"></div>
        </div>
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
        'totals' => function () use ($invoice) {
            ?>
    <div class="invoice-payments">
        <div class="invoice-totals">
            <table>
                <tr>
                    <td class="info">
                        <?= $invoice['translation']['amount'] ?>
                    </td>
                    <td>
                        <?= $invoice['invoice_amount'] . ' ' . $invoice['inv_currency'] ?>
                    </td>
                </tr>
                <tr>
                    <td class="info">
                        <?= $invoice['translation']['discount'] ?> 
                    </td>
                    <td>
                        <?= $invoice['discount'] . ' ' . $invoice['discount_type'] ?>
                    </td>
                </tr>
                <tr>
                    <td class="info">
                        <?= $invoice['translation']['tax_base'] ?>  
                    </td>
                    <td>
                        <?= $invoice['tax_base'] . ' ' . $invoice['inv_currency'] ?>
                    </td>
                </tr>
                <?php if ($invoice['no_vat'] == 1) { ?>
                    <tr>
                        <td class="info">
                            <?= $invoice['translation']['vat_charget'] ?> 
                        </td>
                        <td>
                            <?= $invoice['no_vat_reason'] ?>
                        </td>
                    </tr>
                <?php } else { ?>
                    <tr>
                        <td class="info">
                            <?= $invoice['translation']['percentage_vat'] ?>  -  <?= $invoice['vat_percent'] ?>%
                        </td>
                        <td>
                            <?= $invoice['vat_sum'] . ' ' . $invoice['inv_currency'] ?>
                        </td>
                    </tr>
                <?php } ?>
                <tr class="final-total">
                    <td class="info">
                        <?= $invoice['translation']['everything'] ?>  
                    </td>
                    <td class="total-price">
                        <?= $invoice['final_total'] . ' ' . $invoice['inv_currency'] ?>
                    </td>
                </tr>
            </table>
        </div>
        <div class="invoice-payment">
            <p><?= $invoice['translation']['payment_type'] ?>:</p>
            <p><?= $invoice['payment_method'] ?></p>
        </div>
        <div class="clearfix"></div>
    </div>
    <div class="recipient">
        <p class="rec"><?= $invoice['translation']['recipient'] ?>: 
            <?php if ($invoice['client']['is_to_person'] == 0) { ?>
                <?= $invoice['client']['recipient_name'] ?>
            <?php } else { ?>
                <?= $invoice['client']['client_name'] ?>
            <?php } ?>
        </p>
        <p class="comp"><?= $invoice['translation']['compiled'] ?>: <?= $invoice['composed'] ?></p>
        <div class="clearfix"></div>
    </div>
            <?php
        },
        'signature' => function () use ($invoice) {
            ?>
    <div class="signature">
        <p class="sign"><?= $invoice['translation']['signature'] ?>: ..................</p>
        <div class="clearfix"></div>
    </div>
            <?php
        },
        'remarks' => function () use ($invoice, $invLayout) {
            ?>
    <?php if ($invLayout['showRemarks'] === '1' && trim((string) $invoice['remarks']) !== '') { ?>
        <div class="remarks">
            <p><?= $invoice['translation']['remarks'] ?>:</p>
            <p><?= $invoice['remarks'] ?></p>
        </div>
    <?php } ?>
    <?php if (trim((string) $invLayout['footerNote']) !== '') { ?>
        <div class="av-doc-note"><?= nl2br(html_escape($invLayout['footerNote'])) ?></div>
    <?php } ?>
    <?php /*
        The verification code moved to the letterhead, opposite the company
        details - see the header block above. One code per sheet.
    */ ?>
            <?php
        },
    );
    /* Each section in its band on the page - see adomin_invoice_grid(). */
    $adominGrid = adomin_invoice_grid($invLayout);
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
            . 'top:' . round($adominY * $adominPage[0], 2) . 'pt;'
            . 'width:' . round($adominW * $adominPage[0], 2) . 'pt;'
            . 'height:' . round($adominH * $adominPage[0], 2) . 'pt;">';
        $adominBlocks[$adominSection]();
        echo '</div>';
    }
    ?>
  </div>
</div>