// A document with missing data must still print.
//
// The reported symptom was "the main template is just displaying the products
// grid": an item table with no letterhead above it. The band renderer echoes
// each block inside ob_start(); a PHP notice raised inside one of those buffers
// is printed into the buffer, and the rest of the closure does not run - so a
// single missing label key costs the whole band, silently, and the page shows
// everything except that block.
//
// These render the real block builder with documents that are missing exactly
// the things a real one can be missing: no translation row, no client row, no
// firm address, a translation with only some keys.
import { PhpNode } from 'php-wasm/PhpNode.mjs';
import fs from 'node:fs';

const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');

let pass = 0, fail = 0;
const check = (n, ok, d = '') => {
  if (ok) { pass++; console.log('  ok   ' + n); }
  else { fail++; console.log('  FAIL ' + n + (d ? '  <- ' + d : '')); }
};

const helper = fs.readFileSync(ROOT + '/application/helpers/anvoice_doc_helper.php', 'utf8');

const PRELUDE = `<?php
define('BASEPATH', 1);
function base_url($p = '') { return 'https://x.test/' . ltrim((string) $p, '/'); }
function html_escape($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function lang($k) { return $k; }
function adomin_conf($g, $k = NULL) { return ''; }
function adomin_qr_svg($i, $p = 74) { return '<svg class="av-qr" aria-hidden="true"></svg>'; }
function adomin_qr_png($i, $p = 74) { return ''; }
function get_instance() { return NULL; }
`;

async function blocks(invoiceJson, opts = 'array()') {
  const php = new PhpNode();
  let out = '';
  php.addEventListener('output', e => { out += e.detail[0]; });
  await php.run('<?php @mkdir("/app",0777,TRUE);');
  await php.writeFile('/app/h.php', '<?php\n' + helper.replace(/^<\?php\s*/, ''));
  await php.writeFile('/app/run.php', PRELUDE + `
require '/app/h.php';
/* the JSON goes into the script as a PHP single-quoted string: only the
   quote character needs escaping, and double-encoding it would hand the
   renderer a string instead of an array. */
$invoice = json_decode('${JSON.stringify(invoiceJson).replace(/\\/g, '\\\\').replace(/'/g, "\\'")}', TRUE);
$layout = adomin_invoice_layout(NULL, NULL);
$firmInfo = array('id' => 1, 'name' => 'BONITECH LTD', 'show_logo' => 1);
$b = adomin_invoice_doc_blocks($invoice, $layout, $firmInfo, ${opts});
foreach ($b as $name => $fn) {
    ob_start();
    try { $fn(); } catch (Throwable $e) { echo 'THREW:' . $e->getMessage(); }
    $html = ob_get_clean();
    echo "@@BLOCK:" . $name . "@@" . base64_encode($html) . "\\n";
}
`);
  await php.run('<?php require "/app/run.php";');
  const blocks = {};
  for (const line of out.split('\n')) {
    const m = /@@BLOCK:(\w+)@@(.*)$/.exec(line);
    if (m) blocks[m[1]] = Buffer.from(m[2], 'base64').toString('utf8');
  }
  return { blocks, raw: out };
}

const base = {
  id: 2, for_user: 3, inv_type: 'tax_inv', inv_number: '0000000002',
  inv_currency: 'KES', date_create: 1780000000, date_tax_event: 1780000000,
  invoice_amount: '1000.00', vat_sum: '160.00', final_total: '1160.00',
  tax_base: '1000.00', vat_percent: '16', discount: '0', discount_type: 'percent',
  no_vat: 0, no_vat_reason: '', payment_method: 'Bank', payment_status: 'unpaid',
  remarks: '', status: 'issued',
  items: [
    { position: 1, item: 'Widget', quantity: '2', single_price: '500.00', value: '1000.00' },
  ],
};

const cases = [
  ['a complete document', {
    ...base,
    client: { is_to_person: 0, client_name: 'Jane Doe', recipient_name: 'Jane Doe',
      client_address: '12 Moi Avenue', client_city: 'Nairobi', client_country: 'Kenya',
      client_bulstat: 'P051234567X' },
    firm: { name: 'BONITECH LTD', address: 'Industrial Area', bulstat: 'P059876543Y' },
    translation: { invoice: 'Invoice', number: 'Number', date_of_issue: 'Date of issue',
      a_date_of_a_tax_event: 'Date of tax event', recipient: 'Recipient', mol: 'MOL',
      bulstat: 'KRA PIN', products_name: 'Item', quantity: 'Qty', single_price: 'Unit price',
      value: 'Amount', everything: 'Subtotal', amount: 'Total', percentage_vat: 'VAT',
      currency: 'Currency', signature: 'Signature and stamp', remarks: 'Remarks' },
  }],
  ['a document with NO translation row', {
    ...base,
    client: { is_to_person: 0, client_name: 'Jane Doe', recipient_name: 'Jane Doe' },
    firm: { name: 'BONITECH LTD' },
  }],
  ['a document with NO client row', {
    ...base,
    firm: { name: 'BONITECH LTD' },
    translation: { invoice: 'Invoice' },
  }],
  ['a translation with only some keys', {
    ...base,
    client: { is_to_person: 0, client_name: 'Jane Doe' },
    firm: { name: 'BONITECH LTD' },
    translation: { invoice: 'Invoice', number: 'Number' },
  }],
  ['no firm row either', {
    ...base,
    translation: { invoice: 'Invoice' },
  }],
];

for (const [label, invoice] of cases) {
  const { blocks: b, raw } = await blocks(invoice);

  check(label + ': every block renders', Object.keys(b).length === 5,
    'got ' + Object.keys(b).join(', '));
  check(label + ': no block threw',
    !Object.values(b).some(h => /THREW:/.test(h)),
    Object.entries(b).filter(([, h]) => /THREW:/.test(h)).map(([k, h]) => k).join(', '));
  check(label + ': no PHP notice in the output',
    !/(Warning|Notice|Undefined|Deprecated)[: ]/i.test(raw),
    (raw.match(/(Warning|Notice|Undefined)[^\n]{0,90}/i) || [''])[0]);

  // the letterhead: the block that was missing
  const header = b.header || '';
  check(label + ': the letterhead has content', header.trim().length > 120,
    header.trim().length + ' chars - an empty band is the reported bug');
  check(label + ': the letterhead names the firm', /BONITECH/.test(header) || /adv-firm/.test(header));
  check(label + ': the letterhead shows the document number',
    /0000000002/.test(header), 'a document with no number is not a document');
  check(label + ': the letterhead labels itself',
    /adv-doc-type/.test(header) || /Invoice|Pro forma/.test(header));

  const items = b.items || '';
  check(label + ': the item table still prints the line', /Widget/.test(items));
}

// and the labels fall back to English rather than to empty strings
const { blocks: bare } = await blocks({
  ...base,
  firm: { name: 'BONITECH LTD' },
});
const header = bare.header || '';
const all = Object.values(bare).join('');
for (const word of ['Number', 'Total', 'Currency', 'Item', 'Qty']) {
  check('a missing label falls back to "' + word + '"',
    new RegExp(word, 'i').test(all),
    'an empty <th> is an unlabelled column');
}

console.log('\n' + (fail ? fail + ' FAILED' : 'all pass') + '  (' + pass + ' passed)');
process.exit(fail ? 1 : 0);
