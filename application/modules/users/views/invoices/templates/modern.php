<?php
/*
 * Modern — two-column header, one thin accent rule, tabular figures.
 *
 * The firm on the left, the document on the right, a single hairline between
 * them, and totals set as a right-aligned table with the figures in a
 * tabular-width face so the decimal points line up down the column.
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

$invLayout = adomin_invoice_layout(NULL, isset($invoice['for_user']) ? (int) $invoice['for_user'] : NULL);
$invAccent = adomin_invoice_accent($invLayout['accent']);
?>
<?= adomin_invoice_layout_css($invLayout) ?>
<div class="adoc adoc-modern" style="--adoc-accent: <?= $invAccent[0] ?>; --adoc-tint: <?= $invAccent[1] ?>;">
    <?php adomin_invoice_render($invoice, $invLayout, $firmInfo, $origin); ?>
</div>
