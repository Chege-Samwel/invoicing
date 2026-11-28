// Every design must print a whole sheet from a damaged document.
//
// missingdata.mjs proves the shared block builder survives a document with no
// translation, client or firm row. The designs do not all go through it: the
// Creative and Toner Save templates build their own blocks, and Banner builds
// its own header entirely - so each of them read the same snapshot directly,
// and each of them lost its letterhead to the same notice. Fixing the builder
// alone left three of the six designs broken, which is why this file renders
// all six and looks at what came out rather than at what was intended.
import { PhpNode } from 'php-wasm/PhpNode.mjs';
import fs from 'node:fs';

const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');

let pass = 0, fail = 0;
const check = (n, ok, d = '') => {
  if (ok) { pass++; console.log('  ok   ' + n); }
  else { fail++; console.log('  FAIL ' + n + (d ? '\n         ' + d : '')); }
};

const helper = fs.readFileSync(ROOT + '/application/helpers/anvoice_doc_helper.php', 'utf8')
  .replace(/^<\?php\s*/, '');

/*
 * The document a real account can actually hold: items, an amount, and nothing
 * else. No translation row (never written), no client row (the contact was
 * deleted), no firm row (an imported invoice).
 */
const bare = `array(
  'id' => 2, 'for_user' => 3, 'inv_type' => 'tax_inv', 'inv_number' => '0000000002',
  'inv_currency' => 'KES', 'date_create' => 1780000000, 'date_tax_event' => 1780000000,
  'exchange_rate' => 1, 'final_total' => '1160.00', 'vat_percent' => '16',
  'invoice_amount' => '1000.00', 'vat_sum' => '160.00', 'tax_base' => '1000.00',
  'discount' => '0', 'discount_type' => 'percent', 'status' => 'issued',
  'items' => array(
    array('item' => 'Widget', 'quantity' => '2', 'single_price' => '500.00', 'value' => '1000.00'),
    array('name' => 'Gadget', 'quantity' => '1', 'total_price' => '160.00'),
  ),
)`;

const prelude = `
define('BASEPATH', 1);
function base_url($p = '') { return 'https://x.test/' . ltrim((string) $p, '/'); }
function html_escape($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function lang($k) { return is_array($k) ? $k : $k; }
function adomin_conf($g, $k = NULL) { return ''; }
function adomin_qr_svg($i, $p = 74) { return '<svg class="av-qr"></svg>'; }
function adomin_qr_png($i, $p = 74) { return ''; }
function get_instance() { return NULL; }
require '/app/h.php';
$invoice = ${bare};
$firmInfo = array('id' => 1, 'name' => 'BONITECH LTD', 'show_logo' => 1);
`;

const designs = ['minimal', 'classic', 'modern', 'creative', 'toner-save', 'banner'];

for (const design of designs) {
  const php = new PhpNode();
  let out = '';
  php.addEventListener('output', (e) => { out += e.detail[0]; });

  await php.run('<?php @mkdir("/app", 0777, TRUE);');
  await php.writeFile('/app/h.php', '<?php\n' + helper);
  await php.writeFile('/app/tpl.php',
    fs.readFileSync(`${ROOT}/application/modules/users/views/invoices/templates/${design}.php`, 'utf8'));
  await php.writeFile('/app/run.php', `<?php
${prelude}
ob_start();
include '/app/tpl.php';
echo ob_get_clean();
`);

  await php.run('<?php require "/app/run.php";');

  const notice = (out.match(/(Fatal error|Parse error|Warning|Notice|Deprecated)[^\n]{0,120}/) || [''])[0];
  const bands = (out.match(/adv-band-(header|items|totals|signature|remarks)/g) || []);
  const uniq = new Set(bands);
  const text = out.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ');

  check(`${design}: renders without a notice`, notice === '', notice || 'clean');
  check(`${design}: prints the sheet`, /adv-page|invoice-box|invoice-toner-save/.test(out),
    'the design produced no page at all');
  check(`${design}: prints the letterhead`, uniq.has('adv-band-header') || /BONITECH|0000000002/.test(text),
    'no header band and no firm name or number in the text');
  check(`${design}: prints the item table`, uniq.has('adv-band-items') || /Widget/.test(text),
    'the rows are missing');
  check(`${design}: prints the totals`, uniq.has('adv-band-totals') || /1,?160|1160/.test(text),
    'no total on the sheet');
  check(`${design}: keeps the document number`, /0000000002/.test(text),
    'a document must always say which one it is');
  check(`${design}: labels fall back to English`, /Invoice|Number|Total/i.test(text),
    'an unlabelled page');
  check(`${design}: prints both rows`, /Widget/.test(text) && /Gadget/.test(text),
    'a row that carries `name`/`total_price` instead of `item`/`value` was dropped');
}

console.log(`\n${fail === 0 ? 'all pass ' : ''} (${pass} passed${fail ? ', ' + fail + ' failed' : ''})`);
process.exit(fail === 0 ? 0 : 1);
