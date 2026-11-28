// "The currency is mentioned once." It was mentioned ten times on a five-line
// invoice - after every figure - because the money formatter appended the code
// to each amount. This renders all six designs and counts.
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';

// Harness paths. ROOT is the repository; HERE is this directory.
const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const HERE = new URL('./', import.meta.url).pathname.replace(/\/$/, '');
let pass=0, fail=0;
const check=(n,ok,d)=>{ if(ok){pass++;console.log('  ok   '+n);} else {fail++;console.log('  FAIL '+n+(d?' — '+d:''));} };

const templates=['banner','creative','toner-save','minimal','classic','modern'];
for (const t of templates) {
  execFileSync('node', [HERE + '/qrender.mjs', t], { stdio: 'ignore' });
}
const read = t => fs.readFileSync(HERE + '/page-'+t+'.html','utf8');
const text = html => {
  const body = html.slice(html.lastIndexOf('</style>'));
  return body.replace(/<[^>]+>/g,'\n')
    .replace(/&#0?39;/g,"'").replace(/&amp;/g,'&').replace(/&quot;/g,'"').replace(/&lt;/g,'<').replace(/&gt;/g,'>')
    .split('\n').map(s=>s.trim()).filter(Boolean);
};

for (const t of templates) {
  const html = read(t);
  check(t+': rendered without a PHP error', !/Fatal error|Warning:|Notice:/.test(html),
    (html.match(/(?:Fatal error|Warning:|Notice:)[^\n<]*/)||[])[0] || '');
  const lines = text(html);
  const currencies = lines.filter(l => /^KES$/.test(l) || /KES/.test(l));
  check(t+': the currency is stated exactly once', currencies.length === 1, currencies.join(' | '));

  // and the figures are still there, bare
  const amounts = lines.filter(l => /^[\d,]+\.\d{2}$/.test(l));
  check(t+': the figures survive without the code', amounts.length >= 3, String(amounts.length));
  check(t+': no figure still carries the code', !lines.some(l=>/^[\d,]+\.\d{2} KES$/.test(l)),
    (lines.find(l=>/^[\d,]+\.\d{2} KES$/.test(l))||''));
}

// the declaration names what it is, rather than printing a bare "KES" in space
const minimal = text(read('minimal'));
const i = minimal.indexOf('KES');
check('the declaration is labelled', /Currency/i.test(minimal.slice(Math.max(0,i-3), i+1).join(' ')),
  minimal.slice(Math.max(0,i-3), i+1).join(' '));

// the helper's default must not reintroduce the suffix
const helper = fs.readFileSync(ROOT + '/application/helpers/anvoice_doc_helper.php','utf8');
check('the default money formatter is bare', /function \(\$value\) \{\s*return \$value;\s*\}/.test(helper));
check('no template appends inv_currency to an item price',
  !/\['(single_price|total_price)'\]\s*\.\s*' '\s*\.\s*\$invoice\['inv_currency'\]/.test(
    templates.map(t=>fs.readFileSync(ROOT + '/application/modules/users/views/invoices/templates/'+t+'.php','utf8')).join('\n')));

console.log('\n'+(fail?fail+' FAILED':'all pass')+'  ('+pass+' passed)');
process.exit(fail?1:0);
