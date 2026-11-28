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
    <?php adomin_invoice_render($invoice, $invLayout, $firmInfo); ?>
</div>
