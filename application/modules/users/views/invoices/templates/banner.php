<?php
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
$ibRefs = array();
$ibClientName = '';
if (isset($invoice['client']['client_name'])) {
    $ibClientName = $invoice['client']['client_name'];
} elseif (isset($invoice['client']['is_to_person'])) {
    $ibClientName = $invoice['client']['recipient_name'];
}
if ($ibClientName !== '') {
    $ibRefs[$invoice['translation']['recipient']] = $ibClientName;
}
if (!empty($invoice['to_inv_number'])) {
    $ibRefs[$invoice['translation']['to_an_invoice']] = $invoice['to_inv_number'];
}
if (!empty($invoice['client']['client_address'])) {
    $ibRefs[lang('firm_reg_address')] = $invoice['client']['client_address'];
}
if (!empty($invoice['client']['client_city'])) {
    $ibRefs[lang('firm_city')] = $invoice['client']['client_city'];
}
if (!empty($invoice['composed'])) {
    $ibRefs[$invoice['translation']['compiled']] = $invoice['composed'];
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
 * The verification code.
 *
 * Drawn here rather than in the footer strip's own field so it sits with the
 * document's identity. An SVG, because dompdf renders the path data directly
 * and there is therefore no image to write to a disk that may be read-only.
 * If the library or the key is missing this is '' and the footer simply has no
 * code - a document must never fail because a check cannot be drawn.
 */
$ibQr = function_exists('adomin_qr_svg') ? adomin_qr_svg($invoice, 78) : '';

if ($invoice['inv_type'] == 'prof') {
    $ibType = $invoice['translation']['pro_forma'];
} elseif ($invoice['inv_type'] == 'debit') {
    $ibType = $invoice['translation']['debit_note'];
} elseif ($invoice['inv_type'] == 'credit') {
    $ibType = $invoice['translation']['credit_note'];
} else {
    $ibType = $invoice['translation']['invoice'];
}

$ibMoney = function ($value) use ($invoice) {
    return $value . ' ' . $invoice['inv_currency'];
};
?>
<div class="main-inv-container">
    <div class="invoice-box">
        <div class="ib-doc av-doc-edge<?= !empty($invLayout['edge']) && $invLayout['edge'] !== 'none' ? ' has-edge' : '' ?>"
             style="--ib-accent: <?= $invAccent[0] ?>; --ib-tint: <?= $invAccent[1] ?>;">
            <?php
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
                'header' => function () use ($invoice, $invLayout, $firmInfo, $ibLogo, $ibRefs, $ibType, $ibMoney) {
                    /*
                     * Four independently placed pieces rather than one flow. The
                     * canvas moves each one and the printed document follows,
                     * because both go through adomin_invoice_positioned_letterhead()
                     * with the same positions.
                     */
                    $ibPieces = array();

                    if ($ibLogo !== '') {
                        $ibPieces['logo'] = '<img src="' . html_escape($ibLogo) . '" alt="'
                            . html_escape($invoice['firm']['name']) . '">';
                    }

                    $ibPieces['firm'] = '<p class="ib-firm" style="margin:0;">'
                        . html_escape($invoice['firm']['name']) . '</p>';

                    $ibAddr = '';
                    if ($invLayout['showAddress'] === '1' && !empty($invoice['firm']['address'])) {
                        $ibAddr .= '<p>' . html_escape($invoice['firm']['address']) . '</p>';
                    }
                    if ($invLayout['showContacts'] === '1' && !empty($firmInfo['city'])) {
                        $ibAddr .= '<p>' . html_escape($firmInfo['city']) . '</p>';
                    }
                    if ($invLayout['showFirmVat'] === '1' && !empty($invoice['firm']['bulstat'])) {
                        $ibAddr .= '<p>' . $invoice['translation']['bulstat'] . ': '
                            . html_escape($invoice['firm']['bulstat']) . '</p>';
                    }
                    if ($ibAddr !== '') {
                        $ibPieces['address'] = $ibAddr;
                    }

                    $ibDocNo = '<table>'
                        . '<tr><th>' . $invoice['translation']['number'] . '</th><td>'
                        . html_escape($invoice['inv_number']) . '</td></tr>'
                        . '<tr><th>' . $invoice['translation']['date_of_issue'] . '</th><td>'
                        . date('d.m.Y', $invoice['date_create']) . '</td></tr>'
                        . '<tr><th>' . $invoice['translation']['a_date_of_a_tax_event'] . '</th><td>'
                        . date('d.m.Y', $invoice['date_tax_event']) . '</td></tr>';
                    if ($invLayout['currencyPos'] === 'header') {
                        $ibDocNo .= '<tr><th>' . (isset($invoice['translation']['currency']) ? $invoice['translation']['currency'] : 'Currency')
                            . '</th><td>' . html_escape($invoice['inv_currency']) . '</td></tr>';
                    }
                    $ibPieces['docno'] = $ibDocNo . '</table>';

                    echo adomin_invoice_positioned_css($invLayout);
                    echo adomin_invoice_positioned_letterhead($invLayout, $ibPieces);
                    ?>
            <!-- the reference column, then the accent band that titles the document -->
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
                    <td class="ib-docno"></td>
                </tr>
            </table>

            <div class="ib-title-band">
                <h2 class="ib-title"><?= html_escape($ibType) ?></h2>
            </div>
                    <?php
                },
                'items' => function () use ($invoice, $ibMoney) {
                    ?>
            <table class="ib-items">
                <thead>
                    <tr>
                        <th class="ib-num">#</th>
                        <th class="ib-name"><?= $invoice['translation']['products_name'] ?></th>
                        <th class="ib-qty"><?= $invoice['translation']['quantity'] ?></th>
                        <th class="ib-unit">Unit</th>
                        <th class="ib-money"><?= $invoice['translation']['single_price'] ?></th>
                        <th class="ib-money"><?= $invoice['translation']['value'] ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $ibRow = 0;
                    foreach ($invoice['items'] as $item) {
                        /* a leading "Group:" line in the name becomes a section
                           band, the way the reference invoice groups pipes,
                           valves, fasteners and tools. It is optional: items
                           without it simply continue the table.
                           The row counter only advances for real items, so the
                           numbering stays 1..n across groups instead of
                           counting the bands. */
                        if (preg_match('/^\s*([^:]{2,40}):\s*$/', (string) $item['name'], $ibMatch)) {
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
                            <td class="ib-name"><?= html_escape($item['name']) ?></td>
                            <td class="ib-qty"><?= html_escape($item['quantity']) ?></td>
                            <td class="ib-unit"><?= html_escape($item['quantity_type']) ?></td>
                            <td class="ib-money"><?= html_escape($ibMoney($item['single_price'])) ?></td>
                            <td class="ib-money"><?= html_escape($ibMoney($item['total_price'])) ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
                    <?php
                },
                'totals' => function () use ($invoice, $ibMoney) {
                    ?>
            <table class="ib-totals">
                <tr>
                    <td class="ib-totals-note">
                        <?php if (!empty($invoice['payment_method'])) { ?>
                            <p style="margin:0 0 4px;">
                                <b><?= $invoice['translation']['payment_type'] ?>:</b>
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
                                <td><?= $invoice['translation']['amount'] ?></td>
                                <td><?= html_escape($ibMoney($invoice['invoice_amount'])) ?></td>
                            </tr>
                            <?php if ((float) $invoice['discount'] != 0) { ?>
                                <tr>
                                    <td><?= $invoice['translation']['discount'] ?></td>
                                    <td><?= html_escape($invoice['discount'] . ' ' . $invoice['discount_type']) ?></td>
                                </tr>
                            <?php } ?>
                            <tr>
                                <td><?= $invoice['translation']['tax_base'] ?></td>
                                <td><?= html_escape($ibMoney($invoice['tax_base'])) ?></td>
                            </tr>
                            <tr>
                                <td>
                                    <?php if ($invoice['no_vat'] == 1) { ?>
                                        <?= $invoice['translation']['vat_charget'] ?>
                                    <?php } else { ?>
                                        <?= $invoice['translation']['percentage_vat'] ?> - <?= $invoice['vat_percent'] ?>%
                                    <?php } ?>
                                </td>
                                <td><?= html_escape($ibMoney($invoice['vat_sum'])) ?></td>
                            </tr>
                            <tr class="ib-sum-total">
                                <td><?= $invoice['translation']['everything'] ?></td>
                                <td><?= html_escape($ibMoney($invoice['final_total'])) ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
                    <?php
                },
                'signature' => function () use ($invoice) {
                    ?>
            <table class="ib-sign">
                <tr>
                    <td>
                        <?php if (!empty($invoice['schiffer'])) { ?>
                            <div class="ib-sign-cap"><?= $invoice['translation']['schiffer'] ?>:
                                <?= html_escape($invoice['schiffer']) ?></div>
                        <?php } ?>
                    </td>
                    <td class="ib-sign-right">
                        <span class="ib-sign-line"><?= $invoice['translation']['signature'] ?></span>
                        <div class="ib-sign-cap">Prepared by <?= html_escape($invoice['composed']) ?></div>
                    </td>
                </tr>
            </table>
                    <?php
                },
                'remarks' => function () use ($invoice, $invLayout, $firmInfo, $ibQr) {
                    ?>
            <?php if ($invLayout['showRemarks'] === '1' && trim((string) $invoice['remarks']) !== '') { ?>
                <div class="ib-remarks">
                    <p><b><?= $invoice['translation']['remarks'] ?>:</b></p>
                    <p><?= nl2br(html_escape($invoice['remarks'])) ?></p>
                </div>
            <?php } ?>
            <!-- the footer strip: contact details, then the closing line -->
            <div class="ib-footer">
                <table class="ib-footer-top">
                    <tr>
                        <td class="ib-footer-words">
                            <p class="ib-footer-thanks">Thank you for your business</p>
                        </td>
                        <?php if ($ibQr !== '') { ?>
                            <td class="ib-footer-qr">
                                <?= $ibQr ?>
                                <span class="ib-qr-cap"><?= lang('verify_scan') ?></span>
                            </td>
                        <?php } ?>
                    </tr>
                </table>
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
                    $ibFoot[$invoice['translation']['mol']] = !empty($firmInfo['accountable_person'])
                        ? $firmInfo['accountable_person']
                        : $invoice['firm']['accountable_person'];
                }
                if ($invLayout['showFirmVat'] === '1' && !empty($invoice['firm']['vat_number'])) {
                    $ibFoot['VAT'] = $invoice['firm']['vat_number'];
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

            foreach (adomin_invoice_sections($invLayout) as $ibSection) {
                if (isset($ibBlocks[$ibSection])) {
                    $ibBlocks[$ibSection]();
                }
            }
            ?>
        </div>
    </div>
</div>
