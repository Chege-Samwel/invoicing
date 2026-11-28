// The PDF is a separate path: it inlines a fixed set of stylesheets and does
// not load the templates' <link> tags, so a rule that exists only in a view is
// missing from the paper. These are the rules the sheet depends on.
import fs from 'node:fs';

// Harness paths. ROOT is the repository; HERE is this directory.
const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const HERE = new URL('./', import.meta.url).pathname.replace(/\/$/, '');
let pass = 0, fail = 0;
const check = (n, ok, d) => { if (ok) { pass++; console.log('  ok   ' + n); } else { fail++; console.log('  FAIL ' + n + (d ? ' — ' + d : '')); } };
const read = p => fs.readFileSync(ROOT + '/' + p, 'utf8');

const pdf = read('assets/users/css/pdf-base.css');
const docs = read('application/libraries/AdominDocs.php');

check('the PDF base carries the page sheet', /\.adv-page\s*\{[^}]*position:\s*relative/.test(pdf));
check('the PDF base carries the absolute bands', /\.adv-band\s*\{[^}]*position:\s*absolute/.test(pdf));
check('the PDF base sizes the sheet from the page variables', /width:\s*var\(--av-pw/.test(pdf) && /height:\s*var\(--av-ph/.test(pdf));
check('a finished page clips overflow', /adv-page-final \.adv-band\s*\{[^}]*overflow:\s*hidden/.test(pdf));

check('the PDF inlines the template stylesheet', /invoices-templates\.css/.test(docs));
check('the PDF inlines pdf-base', /pdf-base\.css/.test(docs));
check('invoices-templates is inlined before pdf-base, so base wins ties', (() => {
  const a = docs.indexOf('invoices-templates.css'), b = docs.indexOf('pdf-base.css');
  return a > 0 && b > 0 && a < b;
})());

const tplCss = read('assets/users/css/invoices-templates.css');
for (const sel of ['table.av-parties', 'table.av-meta', '.inv-sign-line', '.av-sum-currency', '.remarks-cap']) {
  check('the shared helper markup is styled: ' + sel, tplCss.includes(sel));
}
// the shared letterhead rules must not be scoped to a single design
const sharedBlock = tplCss.slice(tplCss.indexOf('adomin_invoice_parties()'));
check('the shared letterhead rules reach every design, not just creative',
  /\.invoice-box table\.av-parties \{/.test(sharedBlock));
const nOpen = (tplCss.match(/\{/g) || []).length;
const nClose = (tplCss.match(/\}/g) || []).length;
check('css braces balance', nOpen === nClose, nOpen + ' vs ' + nClose);

// the page sheet in the inline helper must match the two stylesheets
const helper = read('application/helpers/anvoice_doc_helper.php');
check('the layout helper emits the page sheet too', /adomin_invoice_layout_css/.test(helper) && /\.adv-band \{ position:absolute/.test(helper));
check('the layout helper clips a finished page', /adv-page-final \.adv-band \{ overflow:hidden/.test(helper));

console.log('\n' + (fail ? fail + ' FAILED' : 'all pass') + '  (' + pass + ' passed)');
process.exit(fail ? 1 : 0);
