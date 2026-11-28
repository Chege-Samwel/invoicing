// The three things reported after the copy button started working.
//
// 1. the share link 500s
// 2. the preview / print / download have no letterhead
// 3. the invoices list has no status dropdown
import fs from 'node:fs';

const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const read = (rel) => fs.readFileSync(ROOT + '/' + rel, 'utf8');

let pass = 0, fail = 0;
const check = (n, ok, d = '') => {
  if (ok) { pass++; console.log('  ok   ' + n); }
  else { fail++; console.log('  FAIL ' + n + (d ? '\n         ' + d : '')); }
};

// --- 1. the share page -------------------------------------------------
const home = read('application/controllers/Home.php');
const shareView = read('application/views/invoices_parts/invoice_share.php');

/*
 * $this->load->view($view, $data) extracts $data only. A controller method's
 * parameters are invisible to the view, and reading one is an Error in CI, which
 * is the 500 the log showed - no message, no controller trace.
 */
check('sharedInvoice passes its token to the view', /\$data\['uniqid'\] = \$uniqid;/.test(home));
check('publicAcceptInvoice does the same',
  (home.match(/\$data\['uniqid'\] = \$uniqid;/g) || []).length >= 2);
// strip every comment form, then look for a *use* - the guarded fallback
// `isset($uniqid) ? $uniqid : ''` is fine, an unguarded read is not
const shareCode = shareView
  .replace(/\/\*[\s\S]*?\*\//g, '')
  .replace(/^\s*\/\/.*$/gm, '')
  .replace(/^\s*\*.*$/gm, '');
const unguarded = (shareCode.match(/\$uniqid\b/g) || []).filter((_, i, all) => all.length > 0);
check('the view does not read the token unguarded',
  !/rawurlencode\(\$uniqid\)|=\s*\$uniqid\s*;/.test(shareCode),
  'the accept/refuse form built its action from an undefined variable');
check('the only $uniqid left is the isset() fallback',
  (shareCode.match(/\$uniqid/g) || []).length === 2,
  'found: ' + (shareCode.match(/\$uniqid/g) || []).length + ' uses');
check('the view normalises the token name',
  /\$shareToken = isset\(\$shareToken\) \? \$shareToken : \(isset\(\$uniqid\)/.test(shareView));

// --- 2. the letterhead ------------------------------------------------
const model = read('application/modules/users/models/NewInvoiceModel.php');
const helper = read('application/helpers/document_doctor_helper.php');

check('an invoice cannot be created without a firm snapshot',
  /if \(empty\(\$firm\)\)/.test(model) && /LEFT JOIN|'left'/.test(model),
  'an empty join row inserted empty strings, and the letterhead had nothing to print');
check('the firm insert is logged when it cannot happen',
  /would carry no sender/.test(model));

check('there is a way to ask the preview why a block is empty',
  /adomin_doc_band_report/.test(helper) && /docReport/.test(read('application/modules/users/views/invoices/view.php')));
check('the report is available without an environment token',
  /why=1|\$this->input->get\('why'\)/.test(read('application/modules/users/controllers/invoices/Invoiceview.php')),
  'the session is the credential; a token would block the person who can see the fault');
check('the report measures every band separately',
  /foreach \(\$blocks as \$name => \$fn\)/.test(helper));
check('the report survives a host without mbstring',
  /function_exists\('mb_substr'\)/.test(helper),
  'mb_substr unguarded lost the whole report on the first host without it');

// --- 3. the status dropdown -------------------------------------------
const listView = read('application/modules/users/views/invoices/index.php');
const controller = read('application/modules/users/controllers/invoices/Invoices.php');
const routes = read('application/config/routes.php');
const js = read('assets/users/js/general.js');

check('the status column is a changer, not a word',
  /show-doc-statuses/.test(listView) && /doc-status-changer/.test(listView));
check('the menu offers the statuses the library defines',
  /foreach \(\$settableStatuses as \$statusKey\)/.test(listView));
check('the controller supplies that list from the library',
  /\$data\['settableStatuses'\] = \$this->adominstatus->settable\(\)/.test(controller));
check('the endpoint re-checks the allowlist',
  /in_array\(\$toStatus, \$this->adominstatus->settable\(\), TRUE\)/.test(controller) ||
  /in_array\(\$toStatus, \$this->adominstatus->settable\(\), TRUE\)/.test(controller.replace(/\s+/g, ' ')),
  'a dropdown is a hint; the endpoint is the rule');
check('the endpoint is separate from the payment one',
  /public function setInvoiceStatus\(\)/.test(controller) &&
  /public function changeInvoiceStatus\(\$invoiceId, \$toStatus\)/.test(controller));
check('the route exists', /user\/setinvoicestatus/.test(routes));
check('the url is exposed to the page', /setInvoiceStatus:/.test(read('application/modules/users/views/parts/footer.php')));
check('the handler is delegated, so rows added later still work',
  /\$\(document\)\.on\('change', '\.doc-status-select'/.test(js));
check('a refused status reverts the row',
  /previous/.test(js) && /Nothing was changed/.test(js));

console.log(`\n${fail === 0 ? 'all pass ' : ''} (${pass} passed${fail ? ', ' + fail + ' failed' : ''})`);
process.exit(fail === 0 ? 0 : 1);
