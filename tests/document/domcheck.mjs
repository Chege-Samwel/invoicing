// Lay the rendered document out in jsdom-like fashion isn't possible for
// absolute geometry, so this checks the structural contract instead: the QR is
// in the header row, parties are side by side, totals are the last loud figure.
import fs from 'node:fs';

// Harness paths. ROOT is the repository; HERE is this directory.
const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const HERE = new URL('./', import.meta.url).pathname.replace(/\/$/, '');
const tpl = process.argv[2] || 'creative';
const h = fs.readFileSync(HERE + '/page-' + tpl + '.html', 'utf8');
const body = h.slice(h.lastIndexOf('</style>'));
const fail = [];
const ok = (n, c, d) => { console.log((c?'  ok   ':'  FAIL ') + n + (c?'':' — '+(d||''))); if(!c) fail.push(n); };

// QR position: inside the header region, before the items table
const qr = Math.max(body.indexOf('av-doc-qr'), body.indexOf('ib-qr'));
// the item table is named differently per design family
const items = Math.min(
  ...[body.indexOf('items-table'), body.indexOf('adv-items'), body.indexOf('ib-items')]
    .filter(i => i > 0).concat([body.length])
);
ok('the QR is in the letterhead, above the items', qr > 0 && qr < items, `qr@${qr} items@${items}`);

// parties before items
const parties = Math.min(
  ...[body.indexOf('av-parties'), body.indexOf('av-place-firm'), body.indexOf('ib-firm'), body.indexOf('adv-head'), body.indexOf('adoc-head')]
    .filter(i => i > 0).concat([body.length])
);
ok('the parties come before the items', parties > 0 && parties < items);

// totals after items, one total figure
const totals = Math.min(
  ...[body.indexOf('total-price'), body.indexOf('adv-sum-total')]
    .filter(i => i > 0).concat([body.length])
);
ok('the total comes after the items', totals > items);

// no leftover float markers that broke the old layout
ok('no clearfix soup in the header', (body.split('clearfix').length - 1) <= 2, String(body.split('clearfix').length-1));

// nothing outside the sheet
const m = h.match(/--av-pw:([\d.]+)pt;--av-ph:([\d.]+)pt/);
const PH = +m[2], PW = +m[1];
const bands = [...h.matchAll(/style="left:([\d.]+)pt;top:([\d.]+)pt;width:([\d.]+)pt;height:([\d.]+)pt;"/g)]
  .map(x => ({l:+x[1],t:+x[2],w:+x[3],h:+x[4]}));
ok('every band fits the page', bands.every(b => b.l>=0 && b.t>=0 && b.l+b.w<=PW+.5 && b.t+b.h<=PH+.5), JSON.stringify(bands));

console.log(fail.length ? `\n${fail.length} FAILED` : '\nall pass');
process.exit(fail.length?1:0);
