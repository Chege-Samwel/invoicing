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
$defaults['edge'] = 'none';
$defaults['placement'] = '';
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

<?php
/*
 * The preview draws the same blocks in the same order as the document, from the
 * posted section list - a canvas that reordered nothing on screen would be a
 * diagram, not a preview.
 */
$blocks = array(
    'header' => function () use ($layout, $firm, $logo, $labels, $currency) {
        /*
         * The four letterhead pieces, placed by the canvas instead of by the
         * flow. The canvas adopts these elements by their data-avc-component
         * attribute, drags them about and posts the resulting geometry back, so
         * a component moved here is a component moved on the printed invoice.
         */
        $pieces = array();

        if ($logo !== '') {
            $pieces['logo'] = '<img src="' . html_escape($logo) . '" alt="">';
        }

        $pieces['firm'] = '<p style="margin:0;">'
            . html_escape(!empty($firm['name']) ? $firm['name'] : 'Your company name') . '</p>';

        $addr = '';
        if ($layout['showAddress'] === '1' && !empty($firm['address'])) {
            $addr .= '<p>' . html_escape($firm['address']) . '</p>';
        }
        if ($layout['showContacts'] === '1' && !empty($firm['city'])) {
            $addr .= '<p>' . html_escape($firm['city']) . '</p>';
        }
        if ($layout['showFirmVat'] === '1' && !empty($firm['bulstat'])) {
            $addr .= '<p>' . html_escape($labels['bulstat']) . ': ' . html_escape($firm['bulstat']) . '</p>';
        }
        if ($addr !== '') {
            $pieces['address'] = $addr;
        }

        $docno = '<table>'
            . '<tr><th>Number</th><td>0001</td></tr>'
            . '<tr><th>Issued</th><td>' . date('d.m.Y') . '</td></tr>';
        if ($layout['currencyPos'] === 'header') {
            $docno .= '<tr><th>Currency</th><td>' . html_escape($currency) . '</td></tr>';
        }
        $pieces['docno'] = $docno . '</table>';

        echo adomin_invoice_positioned_css($layout);
        echo adomin_invoice_edge_css($layout['edge'], adomin_invoice_accent($layout['accent'])) !== ''
            ? '<div class="av-edge" aria-hidden="true"></div>'
            : '';
        echo adomin_invoice_positioned_letterhead($layout, $pieces);
        ?>
        <?php
    },
    'items' => function () use ($layout, $currency) {
        ?>
        <div class="av-doc-parties">
            <div><span class="av-doc-k">Invoice</span><p class="av-doc-num">0001</p></div>
            <div><span class="av-doc-k">Issued</span><p><?= date('d.m.Y') ?></p></div>
            <div><span class="av-doc-k">Client</span><p>Client name</p></div>
        </div>
        <table class="av-doc-table">
            <thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
            <tbody><tr><td>Item description</td><td>1</td><td>1,000.00</td><td>1,000.00</td></tr></tbody>
        </table>
        <?php
    },
    'totals' => function () use ($layout, $currency) {
        ?>
        <div class="av-doc-totals">
            <table>
                <tr><td>Subtotal</td><td>1,000.00</td></tr>
                <tr><td>VAT</td><td>0.00</td></tr>
                <tr class="av-doc-grand"><td>Total<?php if ($layout['currencyPos'] === 'totals') { ?>
                    <span class="av-doc-cur"><?= html_escape($currency) ?></span><?php } ?></td>
                    <td>1,000.00</td></tr>
            </table>
        </div>
        <?php
    },
    'signature' => function () {
        ?>
        <p class="av-doc-sign">Signature: ..................</p>
        <?php
    },
    'remarks' => function () use ($layout) {
        ?>
        <?php if ($layout['showRemarks'] === '1') { ?>
            <p class="av-doc-remarks"><span class="av-doc-k">Remarks</span> Payment within 14 days.</p>
        <?php } ?>
        <?php if (trim((string) $layout['footerNote']) !== '') { ?>
            <div class="av-doc-note"><?= nl2br(html_escape($layout['footerNote'])) ?></div>
        <?php } ?>
        <?php
    },
);
?>
<?php
/*
 * Render the design that is actually selected, with a sample document, rather
 * than a generic wireframe.
 *
 * The canvas is where a template is chosen, so it has to show that template.
 * A hand-drawn mock of "an invoice" looks the same whichever design is
 * selected, and it cannot show the thing the user is judging: the letterhead,
 * the title band, the way the items table and the totals sit on the page.
 *
 * The sample is built from the same field names a stored invoice has, so the
 * template file runs down its real code path. Changing the canvas and saving
 * therefore shows what the printed document will be, because it is the same
 * file doing the drawing.
 */
$previewInvoice = array(
    'for_user' => defined('USER_ID') ? USER_ID : 0,
    'inv_type' => 'tax_inv',
    'inv_number' => '0001',
    'inv_currency' => $currency,
    'date_create' => time(),
    'date_tax_event' => time(),
    'inv_amount' => '0.00',
    'invoice_amount' => '1000.00',
    'discount' => '0',
    'discount_type' => '%',
    'tax_base' => '1000.00',
    'vat_percent' => '0',
    'vat_sum' => '0.00',
    'final_total' => '1000.00',
    'no_vat' => 1,
    'no_vat_reason' => '',
    'payment_method' => '',
    'remarks' => '',
    'composed' => '',
    'schiffer' => '',
    'to_inv_number' => '',
    'firm' => array(
        'name' => $firm['name'],
        'bulstat' => $firm['bulstat'],
        'vat_number' => $firm['vat_number'],
        'is_vat_registered' => $firm['is_vat_registered'],
        'address' => $firm['address'],
        'city' => $firm['city'],
        'accountable_person' => $firm['accountable_person'],
        'image' => $firm['image'],
    ),
    'client' => array(
        'client_name' => 'Client name',
        'is_to_person' => 0,
        'client_bulstat' => '000000000',
        'client_ident_num' => '',
        'client_vat_registered' => 0,
        'vat_number' => '',
        'client_address' => 'Client address',
        'client_city' => 'Client city',
        'client_country' => '',
        'accountable_person' => '',
        'recipient_name' => 'Client name',
    ),
    'items' => array(
        array('name' => 'Item description', 'quantity' => '1', 'quantity_type' => 'pcs',
              'single_price' => '1,000.00', 'total_price' => '1,000.00'),
    ),
    'translation' => array(
        'invoice' => 'Invoice', 'pro_forma' => 'Pro forma', 'debit_note' => 'Debit note',
        'credit_note' => 'Credit note', 'number' => 'Invoice no.', 'date_of_issue' => 'Date',
        'a_date_of_a_tax_event' => 'Tax event', 'products_name' => 'Item description',
        'quantity' => 'Qty', 'single_price' => 'Unit price', 'value' => 'Amount',
        'amount' => 'Subtotal', 'discount' => 'Discount', 'tax_base' => 'Tax base',
        'percentage_vat' => 'VAT', 'vat_charget' => 'VAT', 'everything' => 'Total',
        'recipient' => 'Client', 'remarks' => 'Remarks', 'signature' => 'Signature',
        'schiffer' => 'Cipher', 'payment_type' => 'Payment', 'compiled' => 'Prepared by',
        'bulstat' => 'Reg. no.', 'currency' => 'Currency', 'to_an_invoice' => 'Against invoice',
        'mol' => 'Contact',
    ),
);

/* The logo the user uploaded, not the placeholder mark: the canvas is where
   they judge whether the logo and the firm name sit well together. */
$previewInvoice['firm']['image'] = !empty($firm['image']) ? $firm['image'] : '';
$previewFirm = $firm;
$previewFirm['show_logo'] = $layout['showLogo'] === '1' ? 1 : 0;
$previewFirm['logo_url'] = $logo;
$previewOrigin = 'original';

$previewFile = FCPATH . 'application/modules/users/views/invoices/templates/' . $layout['template'] . '.php';
if (!is_file($previewFile)) {
    $previewFile = FCPATH . 'application/modules/users/views/invoices/templates/creative.php';
}
?>
<div class="av-doc-preview av-doc-live">
    <?php
    /*
     * $invLayout is what the templates consult for accents, block order and the
     * toggles. It is set from the posted values here so the preview follows the
     * canvas without writing anything to the settings store.
     */
    $invLayout = $layout;
    $invoice = $previewInvoice;
    $firmInfo = $previewFirm;
    $origin = $previewOrigin;
    include $previewFile;
    ?>
</div>

<link rel="stylesheet" href="<?= adomin_asset('assets/users/css/invoices-templates.css') ?>">
<link rel="stylesheet" href="<?= adomin_asset('assets/users/css/invoice-banner.css') ?>">
