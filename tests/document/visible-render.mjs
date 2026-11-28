// Guard the real Creative template's wrapper and both page-generating paths.
import fs from 'node:fs';
const root = new URL('../../', import.meta.url).pathname;
const helper = fs.readFileSync(root + 'application/helpers/anvoice_doc_helper.php', 'utf8');
const template = fs.readFileSync(root + 'application/modules/users/views/invoices/templates/creative.php', 'utf8');
const list = fs.readFileSync(root + 'application/modules/users/views/invoices/index.php', 'utf8');
const js = fs.readFileSync(root + 'assets/users/js/general.js', 'utf8');
const checks = [
  ['creative uses the band loop', /adomin_invoice_band_loop\(\$adominBlocks/.test(template)],
  ['creative page wrapper does not clip absolute content', /\.invoice-box \.adv-page \.invoice-creative\s*\{[^}]*overflow:visible/.test(helper)],
  ['both page loops emit concrete dimensions for dompdf', (helper.match(/style="width:'\s*\.\s*\$pw\s*\.\s*'pt;height:/g) || []).length === 2],
  ['invoice rows contain an actual status select', /<select class="show-doc-statuses doc-status-select"/.test(list)],
  ['status writes are delegated and revert on failure', /on\('change', '\.doc-status-select'/.test(js) && /\$select\.val\(previous\)/.test(js)],
];
for (const [name, ok] of checks) console.log((ok ? '  ok   ' : '  FAIL ') + name);
const failures = checks.filter(([, ok]) => !ok).length;
console.log(`${failures ? failures + ' FAILED' : 'all pass'}  (${checks.length - failures} passed)`);
process.exit(failures ? 1 : 0);
