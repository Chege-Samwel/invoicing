// Exercise the actual form submit handlers for issue, draft, errors and retries.
import fs from 'node:fs';
import { JSDOM } from 'jsdom';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const root = new URL('../../', import.meta.url);
const source = fs.readFileSync(new URL('assets/users/js/general.js', root), 'utf8');
const jquery = fs.readFileSync(new URL('assets/jquery/jquery.min.js', root), 'utf8');
let pass = 0, fail = 0;
function check(name, ok) { if (ok) pass++; else { fail++; console.log('  FAIL ' + name); } }
function setup() {
  const dom = new JSDOM(`<!doctype html><form id="setInvoiceForm" method="post">
  <input name="status" value="issued"><input type="radio" name="inv_type" value="tax_inv" checked>
  <input name="client_name" value="Customer"><input name="client_address" value="Address">
  <input name="inv_number" value="1"><input name="date_create" value="28.11.2026"><input name="date_tax_event" value="28.11.2026">
  <table><tbody class="body-items"><tr><td><input class="field-item-name" value="Item"><input class="quantity-field" value="2"><input class="price-field" value="4.50"></td></tr></tbody></table>
  <input class="text-discount" value="0"><input class="vat-field" value="16"><input type="checkbox" id="no-vat">
  <button type="submit" name="status" value="issued">Create</button><button type="submit" name="status" value="draft">Draft</button></form>`, { runScripts: 'outside-only', url: 'https://invoices.test' });
  const w = dom.window; w.eval(jquery); w.eval(source.slice(source.indexOf('var pattern_sums'), source.indexOf('var pattern_email')));
  w.border_color_fields = '#eee'; w.border_color_wrong = 'red'; w.lang = { errorCreateDocument: 'Invalid invoice' };
  w.createDocument = { calculatorStatus: 1 }; w.calculations = 0; w.createInvoiceCalculator = () => { w.calculations++; };
  w.errors = 0; w.showError = () => { w.errors++; }; w.jQuery.fn.animate = function () { return this; };
  w.eval(source.slice(source.indexOf('/* Submit through the form'), source.indexOf('/*\n * Store movement validator')));
  w.eval(source.slice(source.indexOf('function validateItems()'), source.indexOf('/*\n * Show alert box')));
  return dom;
}
function submit(dom, value) {
  const f = dom.window.document.querySelector('form');
  const btn = f.querySelector(`button[value="${value}"]`);
  const event = new dom.window.SubmitEvent('submit', { bubbles:true, cancelable:true, submitter:btn });
  f.dispatchEvent(event); return { f, event };
}
for (const status of ['issued','draft']) {
  const dom = setup(), {f, event} = submit(dom, status);
  check(status + ' submits', !event.defaultPrevented);
  check(status + ' retains selected status while buttons are disabled', f.querySelector('input[name=status]').value === status && [...f.querySelectorAll('button')].every(b=>b.disabled));
  check(status + ' recalculates totals before saving', dom.window.calculations === 1);
  check(status + ' prevents duplicate submissions', submit(dom, status).event.defaultPrevented);
  dom.window.dispatchEvent(new dom.window.Event('pageshow'));
  check(status + ' can be retried after navigating back', !f.querySelector('button').disabled && !f.dataset.submitting);
  dom.window.close();
}
for (const [selector,value] of [['[name=client_name]',''],['.quantity-field','0'],['.price-field','1,2'],['.price-field','1..2'],['.text-discount','bad']]) {
  const dom = setup(); dom.window.document.querySelector(selector).value = value;
  const {f,event} = submit(dom,'draft');
  check('invalid ' + selector + '=' + value + ' is blocked and remains editable', event.defaultPrevented && !f.dataset.submitting && dom.window.errors === 1 && !f.querySelector('button').disabled);
  dom.window.close();
}
// Run the real calculator with the bundled math library when VAT is toggled.
{
  const dom = setup(), w = dom.window, doc = w.document;
  const extra = doc.createElement('div');
  extra.innerHTML = `<select id="discount-value"><option value="%" selected>%</option></select>
    <span id="items-total"></span><input class="items-total"><span id="tax-base"></span><input class="tax-base">
    <span id="vat-sum"></span><input class="vat-sum"><span id="final-total"></span><input class="final-total">`;
  doc.querySelector('form').appendChild(extra);
  w.math = require(new URL('assets/plugins/math.min.js', root).pathname);
  w.createDocument.rountTo = 2;
  w.eval(source.slice(source.indexOf('function createInvoiceCalculator()'), source.indexOf('/*\n * Verify that filed for round totals')));
  w.createInvoiceCalculator();
  check('VAT calculator produces VAT before exemption', doc.querySelector('.vat-sum').value === '1.44');
  doc.querySelector('#no-vat').checked = true;
  w.createInvoiceCalculator();
  check('VAT exemption clears stale VAT and recalculates total', doc.querySelector('.vat-sum').value === '0.00' && doc.querySelector('.final-total').value === '9.00');
  dom.window.close();
}
const view = fs.readFileSync(new URL('application/modules/users/views/invoices/newinvoice.php',root),'utf8');
check('both controls are native submit buttons', /<button type="submit" name="status" value="issued"/.test(view) && /<button type="submit" name="status" value="draft"/.test(view));
check('form contains its own CSRF token', /id="setInvoiceForm"[^>]*>[\s\S]{0,80}adomin_csrf_field\(\)/.test(view));
console.log(`${fail ? fail + ' FAILED' : 'all pass'}  (${pass} passed)`); process.exit(fail ? 1 : 0);
