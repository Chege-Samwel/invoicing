<?php
/*
 * Minimal — type only.
 *
 * No rules, no fills, no bands. The document is held together by spacing and
 * one weight change per level, which is what makes it print well on a cheap
 * printer and read well on a phone: there is nothing to lose in the dither.
 *
 * The blocks themselves come from adomin_invoice_doc_blocks(), so the figures,
 * the currency, the verification code and the section order behave exactly as
 * they do on every other design.
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

$invLayout = adomin_invoice_layout(NULL, isset($invoice['for_user']) ? (int) $invoice['for_user'] : NULL);
?>
<?= adomin_invoice_layout_css($invLayout) ?>
<div class="adoc adoc-minimal">
    <?php adomin_invoice_render($invoice, $invLayout, $firmInfo, $origin); ?>
</div>
