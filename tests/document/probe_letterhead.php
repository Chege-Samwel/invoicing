<?php
define('BASEPATH','/app/');
require '/app/application/helpers/anvoice_doc_helper.php';
$pl = adomin_invoice_placements(NULL);
$low = 0;
foreach ($pl as $k => $v) { $b = $v[1] + $v[3]; if ($b > $low) $low = $b; }
$g = adomin_invoice_grid(NULL);
echo "letterhead_bottom=$low\n";
echo "items_top=", $g['items'][1], "\n";
echo "clearance=", round($g['items'][1] - $low, 4), "\n";
echo "printable_h=", round(adomin_invoice_printable_height(), 1), "\n";
