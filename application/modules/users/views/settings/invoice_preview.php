<?php
/**
 * Invoice layout preview.
 *
 * Rendered from posted values, not stored ones, so the pane shows what the form
 * currently says. It draws the letterhead and the totals the same way the
 * document does - both call adomin_invoice_letterhead() - so the preview cannot
 * promise something the printed page does not do.
 *
 * @var array $layout  logoPos / addressPos / showLogo / accent / currencyPos / footerNote ...
 * @var array $firm
 */
defined('BASEPATH') OR exit('No direct script access allowed');

$layout = isset($layout) && is_array($layout) ? $layout : array();
$firm = isset($firm) && is_array($firm) ? $firm : array();

// Normalise: this view is also reachable with a partial POST.
$defaults = array('logoPos' => 'left', 'addressPos' => 'below', 'showLogo' => '1',
    'showAddress' => '1', 'showContacts' => '1', 'showFirmVat' => '1', 'showRemarks' => '1',
    'footerNote' => '', 'accent' => 'blue', 'currencyPos' => 'totals', 'template' => 'creative');
$layout = array_merge($defaults, array_filter($layout, function ($v) {
    return $v !== '';
}));

$currency = !empty($firm['default_currency']) ? $firm['default_currency'] : 'KES';
$accent = adomin_invoice_accent($layout['accent']);
$labels = array('bulstat' => 'Reg.', 'vat_number' => 'VAT', 'mol' => 'For');

// The preview uses the mark, because a preview that depends on an uploaded
// logo would show nothing for a new account - the case it is most needed.
$logo = base_url('assets/public/imgs/adomin-mark.svg');
if ($layout['showLogo'] !== '1') {
    $logo = '';
}
?>
<?= adomin_invoice_layout_css($layout) ?>

<div class="av-doc-preview">
    <div class="av-doc-accent"></div>

    <?php if ($layout['currencyPos'] === 'header') { ?>
        <p class="av-doc-currency">Currency: <b><?= html_escape($currency) ?></b></p>
    <?php } ?>

    <?= adomin_invoice_letterhead($layout, $firm, $logo, $labels) ?>

    <div class="av-doc-parties">
        <div>
            <span class="av-doc-k">Invoice</span>
            <p class="av-doc-num">0001</p>
        </div>
        <div>
            <span class="av-doc-k">Issued</span>
            <p><?= date('d.m.Y') ?></p>
        </div>
        <div>
            <span class="av-doc-k">Client</span>
            <p>Client name</p>
        </div>
    </div>

    <table class="av-doc-table">
        <thead>
            <tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr>
        </thead>
        <tbody>
            <tr><td>Item description</td><td>1</td><td>1,000.00</td><td>1,000.00</td></tr>
        </tbody>
    </table>

    <div class="av-doc-totals">
        <table>
            <tr><td>Subtotal</td><td>1,000.00</td></tr>
            <tr><td>VAT</td><td>0.00</td></tr>
            <tr class="av-doc-grand"><td>Total<?php if ($layout['currencyPos'] === 'totals') { ?>
                <span class="av-doc-cur"><?= html_escape($currency) ?></span><?php } ?></td>
                <td>1,000.00</td></tr>
        </table>
    </div>

    <?php if ($layout['showRemarks'] === '1') { ?>
        <p class="av-doc-remarks"><span class="av-doc-k">Remarks</span> Payment within 14 days.</p>
    <?php } ?>

    <?php if (trim((string) $layout['footerNote']) !== '') { ?>
        <div class="av-doc-note"><?= nl2br(html_escape($layout['footerNote'])) ?></div>
    <?php } ?>
</div>

<style>
/* preview-only furniture; the shared letterhead rules come from the helper */
.av-doc-preview { font-size:12px; color:#2a323c; }
.av-doc-parties { display:flex; flex-wrap:wrap; gap:26px; margin:16px 0 14px;
                  padding-top:12px; border-top:1px solid #e8ebef; }
.av-doc-parties p { margin:2px 0 0; font-size:12.5px; font-weight:600; color:#111418; }
.av-doc-parties .av-doc-num { font-size:15px; }
.av-doc-table { width:100%; border-collapse:collapse; margin-bottom:12px; }
.av-doc-table th { text-align:left; font-size:10.5px; text-transform:uppercase; letter-spacing:.07em;
                   color:#6a7480; border-bottom:1px solid #dfe3e8; padding:6px 8px; }
.av-doc-table td { padding:7px 8px; border-bottom:1px solid #eef0f3; font-size:12px; }
.av-doc-table th:last-child, .av-doc-table td:last-child { text-align:right; }
.av-doc-totals { display:flex; justify-content:flex-end; }
.av-doc-totals table { border-collapse:collapse; min-width:200px; }
.av-doc-totals td { padding:5px 8px; font-size:12px; }
.av-doc-totals td:last-child { text-align:right; }
.av-doc-grand td { border-top:1px solid #dfe3e8; font-weight:700; font-size:13px; color:#111418; padding-top:8px; }
.av-doc-cur { font-weight:400; color:#6a7480; font-size:11px; margin-left:5px; }
.av-doc-remarks { margin:12px 0 0; font-size:11.5px; color:#4a545e; }
</style>
