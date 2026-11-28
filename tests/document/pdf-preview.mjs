// Exercise the PDF preview lifecycle in a DOM without relying on a PDF plugin.
import { JSDOM } from 'jsdom';
import fs from 'node:fs';
import { renderInvoicePdf } from '../../assets/users/js/invoice-pdf-preview.mjs';

let pass = 0, fail = 0;
function check(name, ok) { if (ok) pass++; else { fail++; console.log('  FAIL ' + name); } }
const dom = new JSDOM(`<div class="invoice-pdf-shell" data-invoice-pdf="/share/invoice/token/pdf" data-pdfjs-worker="/assets/worker.mjs">
  <p class="invoice-pdf-status"></p><div class="invoice-pdf-pages" hidden></div><div class="invoice-pdf-fallback">fallback invoice</div></div>`, { url: 'https://invoices.test/share/invoice/token' });
globalThis.document = dom.window.document; globalThis.window = dom.window;
dom.window.HTMLCanvasElement.prototype.getContext = () => ({});
const shell = document.querySelector('.invoice-pdf-shell');
let destroyed = false, urls = [], cleaned = 0;
const pdfjs = { GlobalWorkerOptions: {}, getDocument({url}) { urls.push(url); return { promise: Promise.resolve({
  numPages: 2, async getPage(n) { return {
    getViewport() { return { width: 1190, height: 1684 }; },
    render() { return { promise: Promise.resolve() }; },
    async getTextContent() { return { items: [{ str: 'Invoice page ' + n }, { str: 'TOTAL 100' }] }; },
    cleanup() { cleaned++; }
  }; }, async destroy() { destroyed = true; }
}) }; } };
await renderInvoicePdf(shell, pdfjs);
check('same-origin PDF endpoint is used', urls[0] === 'https://invoices.test/share/invoice/token/pdf');
check('all physical pages are shown', shell.querySelectorAll('.invoice-pdf-page').length === 2);
check('each canvas has accessible text', shell.querySelectorAll('.invoice-pdf-text').length === 2 && shell.textContent.includes('TOTAL 100'));
check('the fallback is replaced only after all pages render', shell.classList.contains('is-pdf-ready') && !shell.querySelector('.invoice-pdf-pages').hidden);
check('PDF resources are released', destroyed && cleaned === 2);

const bad = shell.cloneNode(true); bad.classList.remove('is-pdf-ready');
bad.querySelector('.invoice-pdf-pages').replaceChildren(); bad.querySelector('.invoice-pdf-pages').hidden = true;
let failureDestroyed = false;
const broken = { GlobalWorkerOptions: {}, getDocument() { return { promise: Promise.resolve({ numPages: 2,
  async getPage(n) { if (n === 2) throw new Error('page failed'); return { getViewport(){return {width:10,height:10};}, render(){return {promise:Promise.resolve()};}, async getTextContent(){return {items:[]};}, cleanup(){} }; },
  async destroy() { failureDestroyed = true; }
}) }; } };
try { await renderInvoicePdf(bad, broken); } catch (_) {}
check('render error preserves the complete HTML fallback', !bad.classList.contains('is-pdf-ready') && bad.querySelector('.invoice-pdf-pages').hidden && bad.querySelectorAll('canvas').length === 0);
check('resources are released after a page error', failureDestroyed);
bad.dataset.invoicePdf = 'https://evil.test/invoice.pdf';
let blocked = false; try { await renderInvoicePdf(bad, pdfjs); } catch (_) { blocked = true; }
check('cross-origin PDF URLs are refused', blocked && urls.length === 1);
const read = p => fs.readFileSync(new URL('../../' + p, import.meta.url), 'utf8');
for (const [name, file] of [['owner', 'application/modules/users/views/invoices/view.php'], ['share', 'application/views/invoices_parts/invoice_share.php']]) {
  const html = read(file);
  check(name + ' uses its own PDF URL', /data-invoice-pdf="<\?= html_escape\(\$pdfUrl\) \?>"/.test(html));
  check(name + ' keeps fallback and PDF link', /invoice-pdf-fallback/.test(html) && /href="<\?= html_escape\(\$pdfUrl\) \?>"[^>]*>Open PDF/.test(html));
}
check('module MIME type is configured', /AddType text\/javascript \.mjs/.test(read('.htaccess')));
console.log(`${fail ? fail + ' FAILED' : 'all pass'}  (${pass} passed)`);
process.exit(fail ? 1 : 0);
