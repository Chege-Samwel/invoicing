// "A long invoice." A one-page sheet is only correct if every invoice line
// reaches the paper.
//
// The document is a fixed A4 sheet with absolutely positioned bands, so there
// is no natural break: before this, an invoice with more rows than a page holds
// simply lost the rest past the printable height, with no error and no marker.
// These checks run the splitter itself through php-wasm.
import fs from 'node:fs';
import { PhpNode } from 'php-wasm/PhpNode.mjs';

const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const HERE = new URL('./', import.meta.url).pathname.replace(/\/$/, '');

let pass = 0, fail = 0;
const check = (name, cond, detail = '') => {
  if (cond) { pass++; console.log('  ok   ' + name); }
  else { fail++; console.log('  FAIL ' + name + (detail ? '  <- ' + detail : '')); }
};

const helper = fs.readFileSync(ROOT + '/application/helpers/anvoice_doc_helper.php', 'utf8');

// ---- the arithmetic, taken from the helper rather than restated here
const fitBody = helper.slice(helper.indexOf('function adomin_invoice_grid_fit'));
const num = (re, d) => { const m = re.exec(fitBody); return m ? Number(m[1]) : d; };
const ROW = num(/\$row = (\d+);/, NaN);
const HEAD = num(/\$head = (\d+);/, NaN);
const PAD = num(/\$padding = (\d+);/, NaN);
const PRINTABLE = 768.3;
check('the row metrics are readable from the helper',
  Number.isFinite(ROW) && Number.isFinite(HEAD) && Number.isFinite(PAD),
  ROW + '/' + HEAD + '/' + PAD);

const need = rows => HEAD + rows * ROW + PAD;
console.log('\nrow height ' + ROW + 'pt, table head ' + HEAD + 'pt, printable ' + PRINTABLE + 'pt');

check('a twelve-row invoice fits with room to spare', need(12) < PRINTABLE * 0.5, need(12) + 'pt');
check('the opening sheet cannot hold rows the closing bands need', need(14) < PRINTABLE, need(14) + 'pt');

// ---- the splitter, run for real
const php = new PhpNode();
let out = '';
php.addEventListener('output', e => { out += e.detail[0]; });
await php.run('<?php @mkdir("/app",0777,TRUE);');
const src = helper
  .replace(/^\s*<\?php/, '')
  .replace(/\?>\s*$/, '');

const driver = `
<?php
function adomin_invoice_printable_height() { return ${PRINTABLE}; }
function adomin_invoice_page() { return array(595, 842); }
${src.replace(/<\?php/g, '').replace(/\?>/g, '')}
function rows($n) {
  $r = array();
  for ($i = 1; $i <= $n; $i++) { $r[] = array('name' => 'Item ' . $i, 'quantity' => '1', 'quantity_type' => 'pc', 'single_price' => '1', 'total_price' => '1'); }
  return $r;
}
$layout = array('currencyPos' => 'body');
$out = array();
foreach (array(0, 1, 12, 43, 44, 90) as $n) {
  $inv = array('items' => rows($n));
  $pages = adomin_invoice_pages($inv, $layout);
  $carried = 0;
  foreach ($pages as $p) { $carried += count($p['rows']); }
  $out[] = array(
    'n' => $n,
    'pages' => count($pages),
    'carried' => $carried,
    'per' => adomin_invoice_rows_per_page($layout),
    'cap' => adomin_invoice_page_capacity($layout),
    'offsets' => array_map(function ($p) { return $p['rowOffset']; }, $pages),
    'continued' => array_map(function ($p) { return $p['continued']; }, $pages),
  );
}
echo json_encode($out);
`;

// the helper is valid PHP as a file - it only defines functions - so include
// it rather than splicing it into a driver, which mangles its inline HTML
await php.run('<?php @mkdir("/app",0777,TRUE);');
await php.writeFile('/app/anvoice_doc_helper.php', helper);
await php.writeFile('/drive.php', `<?php
define('BASEPATH', 1);
define('APPPATH', '/app/application/');
require '/app/anvoice_doc_helper.php';
function rows($n) {
  $r = array();
  for ($i = 1; $i <= $n; $i++) { $r[] = array('name' => 'Item ' . $i, 'quantity' => '1', 'quantity_type' => 'pc', 'single_price' => '1', 'total_price' => '1'); }
  return $r;
}
$layout = array('currencyPos' => 'body');
$out = array();
foreach (array(0, 1, 12, 43, 44, 90) as $n) {
  $inv = array('items' => rows($n));
  $pages = adomin_invoice_pages($inv, $layout);
  $carried = 0;
  foreach ($pages as $p) { $carried += count($p['rows']); }
  $out[] = array(
    'n' => $n,
    'pages' => count($pages),
    'carried' => $carried,
    'per' => adomin_invoice_rows_per_page($layout),
    'cap' => adomin_invoice_page_capacity($layout),
    'offsets' => array_map(function ($p) { return $p['rowOffset']; }, $pages),
    'continued' => array_map(function ($p) { return $p['continued']; }, $pages),
  );
}
echo 'RESULT' . json_encode($out);
`);
out = '';
try {
  await php.run('<?php include "/drive.php";');
} catch (e) {
  check('the splitter runs', false, String(e).slice(0, 300));
}

let results = null;
const m = /RESULT(\[.*\])/s.exec(out);
if (m) { try { results = JSON.parse(m[1]); } catch (e) { results = null; } }
check('the splitter produces readable output', !!results, out.slice(0, 300));

if (results) {
  const cap = results[0].cap;
  const per = cap.first;
  console.log('  the opening sheet holds ' + cap.first + ' rows, each sheet after it ' + cap.then);

  for (const r of results) {
    check(`all ${r.n} rows are carried across ${r.pages} page(s)`,
      r.carried === r.n, r.carried + ' of ' + r.n);
  }

  const one = results.find(r => r.n > 0 && r.n <= per);
  if (one) check(`an invoice of ${one.n} rows is one page`, one.pages === 1, one.pages + ' pages');

  const over = results.find(r => r.n === per + 1);
  if (over) check('one row past a sheet spills to a second page', over.pages === 2, over.pages + ' pages');

  const big = results.find(r => r.n === 90);
  if (big) {
    check('a 90-row invoice takes more than one page', big.pages >= 2, big.pages + ' pages');
    check('its offsets run consecutively',
      big.offsets.every((o, i) => i === 0 ? o === 0 : o > big.offsets[i - 1]),
      JSON.stringify(big.offsets));
    check('every page but the last says it continues',
      big.continued.every((c, i) => c === (i < big.pages - 1)), JSON.stringify(big.continued));
  }

  const empty = results.find(r => r.n === 0);
  if (empty) check('an invoice with no rows still renders one page', empty.pages === 1, empty.pages + ' pages');
}

// ---- every page's own bands must fit inside the printable area, without
// one running into the next. This runs the real fitter on each page's slice.
// the helper is already included on this instance; include_once keeps the
// functions from being declared twice
const geo = `
<?php
include_once '/app/anvoice_doc_helper.php';
function items($n, $long) {
  $r = array();
  for ($i = 0; $i < $n; $i++) {
    $r[] = array('name' => 'Item ' . $i, 'description' => $long ? str_repeat('detail ', 30) : 'short');
  }
  return $r;
}
$layout = array('currencyPos' => 'body');
$ph = adomin_invoice_page()[1];
$printable = adomin_invoice_printable_height();
$report = array();
foreach (array(array(12, FALSE), array(90, FALSE), array(90, TRUE)) as $case) {
  list($n, $long) = $case;
  $inv = array('items' => items($n, $long));
  $pages = adomin_invoice_pages($inv, $layout);
  $bad = array();
  foreach ($pages as $p) {
    $closing = ! empty($p['continued']) ? array() : array('totals', 'signature', 'remarks');
    $grid = adomin_invoice_grid($layout);
    foreach (array('totals', 'signature', 'remarks') as $k) { if (!in_array($k, $closing)) unset($grid[$k]); }
    $fit = adomin_invoice_grid_fit($grid, adomin_invoice_item_lines(array('items' => $p['rows'])));
    $itemsBottom = ($fit['items'][1] + $fit['items'][3]) * $ph;
    $prevBottom = $itemsBottom;
    foreach (array('totals', 'signature', 'remarks') as $k) {
      if (!isset($fit[$k])) continue;
      $top = $fit[$k][1] * $ph;
      $bottom = ($fit[$k][1] + $fit[$k][3]) * $ph;
      if ($top < $prevBottom - 0.5) { $bad[] = $k . ' starts at ' . round($top,1) . ' over items ending ' . round($prevBottom,1); }
      if ($bottom > $printable + 0.5) { $bad[] = $k . ' ends at ' . round($bottom,1) . ' past ' . round($printable,1); }
      $prevBottom = $bottom;
    }
    if ($itemsBottom > $printable + 0.5) { $bad[] = 'items end at ' . round($itemsBottom,1); }
  }
  $report[] = array('rows' => $n, 'long' => $long, 'pages' => count($pages), 'bad' => $bad);
}
echo 'GEO' . json_encode($report);
`;
await php.writeFile('/geo.php', geo);
out = '';
await php.run('<?php include "/geo.php";');
const gm = /GEO(\[.*\])/s.exec(out);
let reports = null;
if (gm) { try { reports = JSON.parse(gm[1]); } catch (e) { reports = null; } }
check('the page geometry is readable', !!reports, out.slice(0, 200));
if (reports) {
  for (const r of reports) {
    check(`${r.rows} rows${r.long ? ' with long descriptions' : ''}: no band collides or overflows in ${r.pages} page(s)`,
      r.bad.length === 0, (r.bad || []).join('; '));
  }

  // a long description is heavier than a short one, so it must take more pages
  const short = reports.find(r => r.rows === 90 && !r.long);
  const long = reports.find(r => r.rows === 90 && r.long);
  if (short && long) {
    check('long descriptions need more pages than short ones',
      long.pages > short.pages, short.pages + ' vs ' + long.pages);
  }
}

// ---- the renderer must use it, or the splitter is decoration
check('the renderer loops the pages it computed',
  /adomin_invoice_pages\(\$invoice, \$invLayout\)/.test(helper) &&
  /foreach \(\$pages as \$page\)/.test(helper));
check('a continued sheet leaves out totals, signature and remarks',
  /in_array\(\$name, array\('totals', 'signature', 'remarks'\)/.test(helper));
check('the item band is sized to the rows this page prints',
  /adomin_invoice_item_lines\(array\('items' => isset\(\$opts\['rows'\]\)/.test(helper));
check('numbering continues instead of restarting',
  /\$row = \(int\) \$rowOffset/.test(helper));
check('the table says it continues, with the page number',
  /adv-continued/.test(helper) && /\{page\}/.test(helper));

// ---- and the paper breaks between the sheets
const css = fs.readFileSync(ROOT + '/assets/public/css/adomin-brand.css', 'utf8');
check('a second sheet starts on a new piece of paper',
  /\.adv-page \+ \.adv-page \{[^}]*page-break-before: always/.test(css));

// ---- group headings are bands, not rows
check('a group heading does not consume a row a page could print',
  /preg_match\('\/\^\\s\*\[\^:\]\{2,40\}:\\s\*\$\/'/.test(helper) ||
  /'\/\^\\s\*\[\^:\]\{2,40\}:\\s\*\$\/'/.test(helper),
  'item_rows must skip group headings');

// ---- and every design, rendered for real, must carry all 30 rows
import { execFileSync } from 'node:child_process';
const N = 30;
for (const t of ['minimal', 'classic', 'modern', 'creative', 'toner-save', 'banner']) {
  let html = '';
  try {
    execFileSync(process.execPath, [HERE + '/qrender.mjs', t], {
      encoding: 'utf8', stdio: 'pipe', env: { ...process.env, NROWS: String(N) },
    });
    html = fs.readFileSync(HERE + '/page-' + t + '-' + N + '.html', 'utf8');
  } catch (e) {
    check(t + ': renders at all', false, String(e).slice(0, 160));
    continue;
  }

  check(t + ': renders without a PHP warning', !/Warning:|Fatal error/.test(html),
    (/Warning:[^<]*|Fatal error[^<]*/.exec(html) || [''])[0].slice(0, 120));

  const sheets = [...html.matchAll(/data-av-page="(\d+)"/g)];
  const chunks = sheets.map((m, i) => html.slice(m.index, i + 1 < sheets.length ? sheets[i + 1].index : html.length));
  // three designs put the row number in .adv-col-num, the other three name the
  // item cell and have no number column - count whichever the design uses
  // count numbered cells, not the header cell that carries the same class
  const rowOf = c => Math.max(
    (c.match(/class="adv-col-num">\s*\d+/g) || []).length,
    (c.match(/class="ib-num">\s*\d+/g) || []).length,
    (c.match(/class="item-name"/g) || []).length);
  const rows = chunks.map(rowOf);
  const printed = rows.reduce((a, b) => a + b, 0);

  check(t + ': every one of the ' + N + ' rows reaches the paper', printed === N,
    printed + ' printed across ' + chunks.length + ' sheet(s)');
  check(t + ': the invoice is carried onto more than one sheet', chunks.length > 1,
    chunks.length + ' sheet(s)');
  check(t + ': the first sheet says the table continues',
    chunks.length > 1 && /adv-continued/.test(chunks[0]));
  check(t + ': the closing blocks appear once, on the last sheet',
    chunks.length > 1 && !/adv-band-signature/.test(chunks[0]) && /adv-band-signature/.test(chunks[chunks.length - 1]));

  // A carried-over sheet must still look like this design. Each design draws
  // its frame and its letterhead with its own markup - an inner frame around
  // the content for three of them, the firm block for the rest, and the
  // document name (not the frame) once per document in Toner Save - so the
  // marker is per design rather than one class assumed to be everywhere.
  const identity = {
    'creative': { frame: /invoice-creative/, letterhead: /av-parties|row-my-firm/ },
    'toner-save': { frame: /av-parties/, letterhead: /av-parties|row-my-firm/ },
    'banner': { frame: /ib-doc/, letterhead: /av-doc-head|ib-title|av-parties/ },
    'minimal': { frame: /adv-band-header/, letterhead: /adv-firm-name|av-doc-firm|av-doc-head|av-parties/ },
    'classic': { frame: /adv-band-header/, letterhead: /adv-firm-name|av-doc-firm|av-doc-head|av-parties/ },
    'modern': { frame: /adv-band-header/, letterhead: /adv-firm-name|av-doc-firm|av-doc-head|av-parties/ },
  }[t];
  if (identity) {
    check(t + ': every sheet keeps the design\'s own frame',
      chunks.every(c => identity.frame.test(c)), chunks.map(c => identity.frame.test(c)).join(','));
    check(t + ': every sheet carries the letterhead',
      chunks.every(c => identity.letterhead.test(c)), chunks.map(c => identity.letterhead.test(c)).join(','));
  }
}

console.log('\n' + (fail ? fail + ' FAILED' : 'all pass') + '  (' + pass + ' passed)');
process.exit(fail ? 1 : 0);
