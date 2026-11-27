<?php
/*
 * Classic — centred letterhead, serif headings, ruled table.
 *
 * The arrangement a customer expects from a printed invoice: the firm centred
 * at the top, a rule under it, the bill-to and the document meta side by side,
 * and a table with a border and a shaded head.
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

$invLayout = adomin_invoice_layout(NULL, isset($invoice['for_user']) ? (int) $invoice['for_user'] : NULL);
?>
<?= adomin_invoice_layout_css($invLayout) ?>
<div class="adoc adoc-classic">
    <?php adomin_invoice_render($invoice, $invLayout, $firmInfo, $origin); ?>
</div>
