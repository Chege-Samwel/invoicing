// The invoice sheet: parties side by side, QR once on the right, totals right-aligned
// with one loud figure, no repetition, nothing outside the page.
import { PhpNode } from 'php-wasm/PhpNode.mjs';
import fs from 'node:fs';

// Harness paths. ROOT is the repository; HERE is this directory.
const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const HERE = new URL('./', import.meta.url).pathname.replace(/\/$/, '');

let pass = 0, fail = 0;
const check = (n, ok, d) => { if (ok) { pass++; console.log('  ok   ' + n); } else { fail++; console.log('  FAIL ' + n + (d ? ' — ' + d : '')); } };

for (const tpl of ['creative', 'toner-save']) {
  const php = new PhpNode(); let out = '';
  php.addEventListener('output', e => { out += e.detail[0]; });
  for (const d of ['/app/application/helpers','/app/application/libraries','/app/application/views/invoices/templates'])
    await php.run('<?php @mkdir("'+d+'",0777,TRUE);');
  await php.writeFile('/app/application/helpers/anvoice_doc_helper.php', fs.readFileSync(ROOT + '/application/helpers/anvoice_doc_helper.php'));
  await php.writeFile('/app/tpl.php', fs.readFileSync(ROOT + '/application/modules/users/views/invoices/templates/'+tpl+'.php'));
  for (const f of ['anvoice_doc_helper','adomin_helper']) {
    try { await php.writeFile('/app/application/helpers/'+f+'.php', fs.readFileSync(ROOT + '/application/helpers/'+f+'.php')); } catch(e){}
  }
  // reuse the fixture from qrender
  const prog = fs.readFileSync(HERE + '/qpage_body.txt','utf8');
  await php.writeFile('/app/qr.php', `<?php if (!function_exists('adomin_qr_svg')) { function adomin_qr_svg($inv,$px=78){
  return '<svg width="'.$px.'" height="'.$px.'" viewBox="0 0 21 21"><rect width="21" height="21" fill="#fff"/><path d="M0 0h7v7H0zM9 0h3v3H9zM14 0h7v7h-7zM0 9h3v3H0zM5 5h3v3H5zM9 9h3v3H9zM14 9h7v3h-7zM0 14h7v7H0zM9 14h3v7H9zM14 14h7v7h-7z" fill="#000"/></svg>';
}}`);
  await php.writeFile('/app/run.php', prog);
  try { await php.run('<?php require "/app/run.php";'); } catch (e) { console.log('run err', e.message); }
  const html = out;

  console.log('\n=== ' + tpl + ' ===');
  const cssStart = html.indexOf('</style>');
  const body = cssStart >= 0 ? html.slice(html.lastIndexOf('</style>')) : html;

  // 1. parties: one table, three cells, in order
  const parties = body.match(/<table class="av-parties"[\s\S]*?<\/table>/);
  check(tpl + ': the two parties and the QR are one row', !!parties);
  if (parties) {
    const cells = [...parties[0].matchAll(/<td class="av-party ([^"]+)"/g)].map(m => m[1]);
    check(tpl + ': sender, receiver, QR in that order', cells.join(',') === 'av-party-firm,av-party-client,av-party-qr', cells.join(','));
  }

  // 2. QR: exactly one, on the right
  const qrs = (body.match(/class="av-doc-qr"/g) || []).length;
  check(tpl + ': the verification code appears exactly once', qrs === 1, String(qrs));

  // 3. address on one line each
  const firmLine = body.match(/<p class="av-party-line">([^<]*)<\/p>/);
  check(tpl + ': the sender address is one line', !!firmLine && firmLine[1].includes(',') && !firmLine[1].includes('<'), firmLine ? firmLine[1] : 'none');
  const clientBlock = parties ? parties[0].split('av-party-client')[1] : '';
  const clientAddr = (clientBlock.match(/<p class="av-party-line">([^<]*)<\/p>/) || [])[1] || '';
  check(tpl + ': the receiver address is one line', clientAddr.split(',').length >= 2, clientAddr);

  // 4. no "Signature: Signature"
  check(tpl + ': signature is a line, not a repeated word', !/Signature: *\.\.\./.test(body) && !/signature[^"]*<\/summary>\s*<div[^>]*>\s*<?[^>]*>?\s*Signature/i.test(body));

  // 5. currency stated once in the totals, not after every figure
  const totals = (body.match(/<div class="invoice-totals">[\s\S]*?<\/div>/) || [''])[0];
  const currencyHits = (totals.match(/KES/g) || []).length;
  check(tpl + ': the currency is not glued to every figure', currencyHits <= 1, String(currencyHits));

  // 6. exactly one total-price figure
  check(tpl + ': one loud total, not three', (body.match(/total-price/g) || []).length === 1, String((body.match(/total-price/g)||[]).length));

  // 7. subtotal is not repeated in the header
  const headerBlock = body.split('adv-band')[0] || '';
  check(tpl + ': the subtotal is not repeated above the items', !/subtotal/i.test(headerBlock));

  // 8. the note is printed once
  const noteHits = (body.match(/prices displayed are estimates/gi) || []).length;
  check(tpl + ': the standing note appears once', noteHits <= 1, String(noteHits));

  // 9. remarks printed once, at the end
  check(tpl + ': remarks appear once', (body.match(/class="remarks"/g) || []).length <= 1, String((body.match(/class="remarks"/g)||[]).length));

  // 10. geometry: bands inside the page, no overlaps
  const page = html.match(/--av-pw:([\d.]+)pt;--av-ph:([\d.]+)pt/);
  const PW = parseFloat(page[1]), PH = parseFloat(page[2]);
  const bands = [...html.matchAll(/class="adv-band[^"]*"[^>]*style="left:([\d.]+)pt;top:([\d.]+)pt;width:([\d.]+)pt;height:([\d.]+)pt;"/g)]
    .map(m => ({ left:+m[1], top:+m[2], w:+m[3], h:+m[4] }));
  const outside = bands.filter(b => b.left < 0 || b.top < 0 || b.left+b.w > PW+0.5 || b.top+b.h > PH+0.5);
  check(tpl + ': every band is inside the sheet', outside.length === 0, JSON.stringify(outside));
  let overlap = 0;
  for (let i=0;i<bands.length;i++) for (let j=i+1;j<bands.length;j++) {
    const a=bands[i], b=bands[j];
    if (Math.min(a.left+a.w,b.left+b.w) - Math.max(a.left,b.left) > 1 &&
        Math.min(a.top+a.h,b.top+b.h) - Math.max(a.top,b.top) > 1) overlap++;
  }
  check(tpl + ': no two bands overlap', overlap === 0, String(overlap));

  // 11. the sheet rules are present in the document itself (the PDF only inlines these)
  check(tpl + ': the page-sheet rules ship with the document', /\.adv-band \{ position:absolute/.test(html) && /\.adv-page \{ position:relative/.test(html));

  // 12. the letterhead is placed on the grid, not flowed at x=0
  check(tpl + ': the letterhead shares the grid inset', /\.adv-header \{ position:absolute; left:5\.5%/.test(html));

  const phpErr = /(Warning|Notice|Fatal error)[^\n]*/.exec(out);
  check(tpl + ': no PHP warnings', !phpErr, phpErr ? phpErr[0] : '');
}

console.log('\n' + (fail ? fail + ' FAILED' : 'all pass') + '  (' + pass + ' passed)');
process.exit(fail ? 1 : 0);
