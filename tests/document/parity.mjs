// The preview, the share page and the PDF are one document.
//
// AdominDocs::invoiceHtml() builds the PDF: watermark, then the template, then
// the credit stamp. Every other surface that shows an invoice has to draw the
// same three things in the same order, or the sender and the client are looking
// at different documents - and the difference always turns up on the surface
// nobody tested.
import fs from 'node:fs';

const ROOT = new URL('../../', import.meta.url).pathname;
const R = (p) => fs.readFileSync(ROOT + p, 'utf8');

let pass = 0, fail = 0;
const check = (n, ok, d = '') => {
  if (ok) { pass++; console.log('  ok   ' + n); }
  else { fail++; console.log('  FAIL ' + n + (d ? '  <- ' + d : '')); }
};

const docs = R('application/libraries/AdominDocs.php');
const preview = R('application/modules/users/views/invoices/view.php');
const share = R('application/views/invoices_parts/invoice_share.php');
const css = R('assets/users/css/invoices-templates.css');

// --- the PDF, as the reference implementation
const html = /public function invoiceHtml[\s\S]*?\n    \}/.exec(docs)[0];
const order = ['watermark', 'body', 'stampBlock'].map(n => html.indexOf('$this->' + n) !== -1 ? html.indexOf('$this->' + n) : html.indexOf('$' + n));
check('the PDF draws the watermark', /watermark\(\$invoice\)/.test(html));
check('the PDF draws the credit after the template',
  /\. \$this->stampBlock\(\)/.test(html) || html.indexOf('stampBlock') > html.indexOf('$body'));

// --- the in-app preview
check('the preview draws the watermark', /\$this->adomindocs->watermark\(\$invoice\)/.test(preview));
check('the preview draws the credit', /\$this->adomindocs->stampBlock\(\)/.test(preview));
const pv = preview.indexOf('watermark($invoice)');
const pt = preview.indexOf('include $templateFile');
const ps = preview.indexOf('stampBlock()');
check('the preview keeps the PDF order',
  pv !== -1 && pt !== -1 && ps !== -1 && pv < pt && pt < ps,
  `watermark ${pv}, template ${pt}, stamp ${ps}`);

// --- the page the client opens
check('the share page draws the watermark', /->watermark\(\$invoice\)/.test(share));
check('the share page draws the credit', /->stampBlock\(\)/.test(share));
const sv = share.indexOf('watermark($invoice)');
const st = share.search(/include \$templateFile/);
const ss = share.indexOf('stampBlock()');
check('the share page keeps the PDF order',
  sv !== -1 && st !== -1 && ss !== -1 && sv < st && st < ss,
  `watermark ${sv}, template ${st}, stamp ${ss}`);
check('the share page reads the library through get_instance()',
  /get_instance\(\)/.test(share) && /is_object\(\$adominCI->adomindocs\)/.test(share),
  'a view must not use $this - it is the loader, and only inside CodeIgniter');

// --- and the mark must be positioned on screen, not only in the PDF
/*
 * printOverridesCss() has always positioned it for dompdf. Nothing positioned
 * it in a browser, so the mark was a 360x260 block in normal flow - the first
 * thing on the page, above the letterhead - and on the share page it was absent
 * altogether. Both read as "the preview is missing components".
 */
check('the mark is absolutely positioned for screen',
  /\.adomin-watermark\s*\{[^}]*position:\s*absolute/.test(css),
  'only .adomin-print .adomin-watermark was positioned');
check('the mark does not take space in the flow',
  /\.adomin-watermark\s*\{[^}]*pointer-events:\s*none/.test(css));
check('the page box is the mark\'s containing block',
  /\.invoice-box\s*\{[^}]*position:\s*relative/.test(css));
check('the document paints above the mark',
  /\.invoice-box > :not\(\.adomin-watermark\)\s*\{[^}]*z-index:\s*1/.test(css));

/*
 * In print the mark is fixed to the page instead (dompdf and wkhtmltopdf repeat
 * a fixed background on every page). The two rules must not fight.
 */
check('print still uses the fixed arrangement',
  /\.adomin-print \.adomin-watermark\s*\{[^}]*position:\s*fixed/.test(docs));

/*
 * --- the QR, which the document needs in every design
 *
 * Five designs draw it through the shared letterhead helper
 * (adomin_invoice_parties() emits the party cell that holds it); banner has its
 * own layout and draws it in its meta block. So the check is that each design
 * reaches one of those two, not that each file contains a particular call.
 */
const designs = ['minimal', 'classic', 'modern', 'creative', 'toner-save', 'banner'];
for (const d of designs) {
  const src = R('application/modules/users/views/invoices/templates/' + d + '.php');
  const own = /adomin_invoice_qr_html\(|adomin_invoice_qr_block\(/.test(src);
  const viaLetterhead = /adomin_invoice_parties\(/.test(src) || /adomin_invoice_header\(/.test(src);
  // minimal, classic and modern are band designs: every block, the code
  // included, comes from the renderer they delegate to
  const viaBands = /adomin_invoice_render\(\$invoice, \$invLayout/.test(src);
  check(d + ' draws the verification code',
    own || viaLetterhead || viaBands,
    'none of: its own code, the letterhead, the band renderer');
}

const helper = R('application/helpers/anvoice_doc_helper.php');
check('the letterhead puts the code in the party row',
  /av-party-qr[\s\S]{0,80}adomin_invoice_qr_block/.test(helper));
// the band designs: render -> page body -> blocks, and the blocks' header cell
// is where the code is drawn
const blocks = /function adomin_invoice_doc_blocks[\s\S]*?\n\t\}\n\}/.exec(helper);
check('the band renderer builds the code',
  !!blocks && /adomin_invoice_qr_html\(/.test(blocks[0]),
  'the three band designs would print without a verification code');
check('the band header cell prints it',
  !!blocks && /'header' =>[\s\S]{0,3000}\$qrHtml/.test(blocks[0]),
  'the code is built but never placed in the letterhead');
check('the QR block wraps the code in the class the sheets style',
  /av-doc-qr/.test(helper) && /\.av-doc-qr/.test(css));
check('the banner styles its own code',
  /\.ib-qr/.test(R('assets/users/css/invoice-banner.css')));

console.log('\n' + (fail ? fail + ' FAILED' : 'all pass') + '  (' + pass + ' passed)');
process.exit(fail ? 1 : 0);
