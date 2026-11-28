// The page model. Every band position is a fraction of the sheet: x and width of
// the width, y and height of the height. Reading the vertical pair against the
// width drew the whole document into the top 70% of the page - the "blank half
// sheet" - so these numbers are asserted rather than assumed.
import fs from 'node:fs';
import { PhpNode } from 'php-wasm/PhpNode.mjs';

// Harness paths. ROOT is the repository; HERE is this directory.
const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const HERE = new URL('./', import.meta.url).pathname.replace(/\/$/, '');

let pass = 0, fail = 0;
const check = (n, ok, d) => { if (ok) { pass++; console.log('  ok   ' + n); } else { fail++; console.log('  FAIL ' + n + (d ? ' — ' + d : '')); } };

const php = new PhpNode(); let out = '';
php.addEventListener('output', e => { out += e.detail[0]; });
await php.run('<?php @mkdir("/app/application/helpers",0777,TRUE);');
await php.writeFile('/app/application/helpers/anvoice_doc_helper.php', fs.readFileSync(ROOT + '/application/helpers/anvoice_doc_helper.php'));

const probe = `<?php
define('BASEPATH', '/app/');
require '/app/application/helpers/anvoice_doc_helper.php';
$page = adomin_invoice_page();
$g = adomin_invoice_grid(NULL);
echo json_encode(array('page'=>$page, 'grid'=>$g)), "\\n";

// the default design has to fill the sheet, not the top of it
$lowest = 0; $prev = NULL; $ordered = TRUE; $overlap = 0;
foreach (array('header','items','totals','signature','remarks') as $k) {
  list($x,$y,$w,$h) = $g[$k];
  if ($y + $h > $lowest) $lowest = $y + $h;
  if ($prev !== NULL && $y + 0.001 < $prev) $ordered = FALSE;
  $prev = $y + $h;
  echo "band $k y=$y h=$h bottom=", round($y+$h,3), "\\n";
}
echo "lowest=", round($lowest,3), " ordered=", (int)$ordered, "\\n";

// a three-line invoice and a thirty-line one must both fit
foreach (array(3 => 'short', 12 => 'mid', 30 => 'long') as $n => $label) {
  $items = array();
  for ($i=0; $i<$n; $i++) { $items[] = array('name'=>'line '.$i); }
  $fit = adomin_invoice_grid_fit($g, $n);
  $bottom = 0;
  foreach (array('items','totals','signature','remarks') as $k) {
    if ($fit[$k][1] + $fit[$k][3] > $bottom) $bottom = $fit[$k][1] + $fit[$k][3];
  }
  echo "$label rows=$n itemsH=", round($fit['items'][3]*$page[1],1), " lowest=", round($bottom,3), "\\n";
}
echo "lines3=", adomin_invoice_item_lines(array('items'=>array(array('name'=>'a'),array('name'=>'b'),array('name'=>'c')))), "\\n";
echo "lines0=", adomin_invoice_item_lines(array()), "\\n";
`;
await php.writeFile('/app/probe.php', probe);
await php.run('<?php require "/app/probe.php";');

console.log(out.trim().split('\n').map(l => '  ' + l).join('\n') + '\n');
const lowest = parseFloat((out.match(/lowest=([\d.]+)/) || [])[1]);
const ordered = /ordered=1/.test(out);
check('the default design reaches the foot of the sheet', lowest > 0.85, String(lowest));

// ---- the letterhead must clear the item table -------------------------------
// The letterhead is positioned by adomin_invoice_placements(); its lowest
// component sets the floor the item band must start under. Measured, not
// assumed: shrinking the header band once buried the address in the table.
/*
 * In a fresh PHP instance. The helper defines its functions unconditionally
 * when required a second time, so re-requiring it in the instance above is a
 * redeclaration fatal - and the fatal replaced the probe output with an error
 * string, which is why these three checks read NaN while the numbers were
 * actually correct.
 */
const php2 = new PhpNode(); let out2 = '';
php2.addEventListener('output', e => { out2 += e.detail[0]; });
await php2.run('<?php @mkdir("/app/application/helpers",0777,TRUE);');
await php2.writeFile('/app/application/helpers/anvoice_doc_helper.php',
  fs.readFileSync(ROOT + '/application/helpers/anvoice_doc_helper.php'));
await php2.writeFile('/app/probe2.php', fs.readFileSync(HERE + '/probe_letterhead.php', 'utf8'));
await php2.run('<?php require "/app/probe2.php";');
const num = (k) => { const m = out2.match(new RegExp(k + '=(-?[0-9.]+)')); return m ? parseFloat(m[1]) : NaN; };
const lb  = num('letterhead_bottom');
const it  = num('items_top');
const cl  = num('clearance');
const prh = num('printable_h');
check('the letterhead placements were measured', !isNaN(lb) && lb > 0, String(lb));
check('the item table starts BELOW the letterhead', cl > 0, 'letterhead ' + lb + ' vs items ' + it + ' = ' + cl);
check('the printable height excludes the renderer margins', prh > 700 && prh < 800, String(prh));
check('the bands are stacked in reading order', ordered);
const m30 = out.match(/long rows=30 itemsH=([\d.]+)/);
const m3 = out.match(/short rows=3 itemsH=([\d.]+)/);
check('a three-line table gets a three-line box', m3 && parseFloat(m3[1]) < 100, m3 && m3[1]);
const m12 = out.match(/mid rows=12 itemsH=([\d.]+)/);
check('a twelve-line table gets a taller box than a three-line one',
  m12 && m3 && parseFloat(m12[1]) > parseFloat(m3[1]) + 100, m12 && m12[1]);
check('a thirty-line table takes all the room the page has',
  m30 && m12 && parseFloat(m30[1]) >= parseFloat(m12[1]), m30 && m30[1]);
check('the long invoice still fits the page', /long rows=30[^\n]*lowest=([\d.]+)/.test(out)
  && parseFloat(out.match(/long rows=30[^\n]*lowest=([\d.]+)/)[1]) <= 0.96);
check('item lines counts the rows', /lines3=3/.test(out));
check('an invoice with no items counts zero', /lines0=0/.test(out));

// the templates and the editor have to agree with the helper
const tpl = fs.readFileSync(ROOT + '/application/modules/users/views/invoices/templates/creative.php', 'utf8');
check('the canvas template uses the fitted grid', /adomin_invoice_grid_for\(\$invoice, \$invLayout\)/.test(tpl));
check('the canvas template scales height by the page height', /adominH \* \$adominPage\[1\]/.test(tpl));
check('the canvas template scales top by the page height', /adominY \* \$adominPage\[1\]/.test(tpl));
check('the canvas template scales width by the page width', /adominW \* \$adominPage\[0\]/.test(tpl));

const js = fs.readFileSync(ROOT + '/assets/users/js/invoice-page-editor.js', 'utf8');
check('the editor draws top against the page height', /top'\)\s*=\s*\(box\[1\] \* PAGE_H\)/.test(js) || /style\.top = \(box\[1\] \* PAGE_H\)/.test(js));
check('the editor draws height against the page height', /style\.height = \(box\[3\] \* PAGE_H\)/.test(js));
check('the editor saves top against the page height', /top \/ PAGE_H/.test(js));
check('the editor saves height against the page height', /height \/ PAGE_H/.test(js));
check('the editor drags vertically against the page height', /dy = \(p\.y - drag\.startY\) \/ scale \/ \(PAGE_H/.test(js));

const helper = fs.readFileSync(ROOT + '/application/helpers/anvoice_doc_helper.php', 'utf8');
check('the renderer scales top by the page height', /'top:' \. round\(\$y \* \$ph, 2\)/.test(helper));
check('the renderer scales height by the page height', /'height:' \. round\(\$h \* \$ph, 2\)/.test(helper));
check('the letterhead places top against the page height', /'top:' \. round\(\$y \* \$page\[1\], 2\)/.test(helper));

console.log('\n' + (fail ? fail + ' FAILED' : 'all pass') + '  (' + pass + ' passed)');
process.exit(fail ? 1 : 0);
