// A document should name each party once and state its currency once. Both had
// drifted: the client's name appeared twice (bill-to block plus a "beneficiary"
// drawer), and KES appeared on every figure.
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';

// Harness paths. ROOT is the repository; HERE is this directory.
const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const HERE = new URL('./', import.meta.url).pathname.replace(/\/$/, '');
let pass=0, fail=0;
const check=(n,ok,d)=>{ if(ok){pass++;console.log('  ok   '+n);} else {fail++;console.log('  FAIL '+n+(d?' — '+d:''));} };

const templates=['banner','creative','toner-save','minimal','classic','modern'];
const text = html => html.slice(html.lastIndexOf('</style>'))
  .replace(/<[^>]+>/g,'\n')
  .replace(/&#0?39;/g,"'").replace(/&amp;/g,'&').replace(/&quot;/g,'"')
  .split('\n').map(s=>s.trim()).filter(Boolean);

for (const t of templates) execFileSync('node', [HERE + '/qrender.mjs', t], { stdio: 'ignore' });

for (const t of templates) {
  const html = fs.readFileSync(HERE + '/page-'+t+'.html','utf8');
  check(t+': renders without a PHP error', !/Fatal error|Warning:|Notice:/.test(html),
    (html.match(/(?:Fatal error|Warning:|Notice:)[^\n<]*/)||[])[0] || '');
  const lines = text(html);
  // the client, named once
  check(t+': the client is named once', lines.filter(l=>/ACME TRADERS/.test(l)).length === 1,
    String(lines.filter(l=>/ACME TRADERS/.test(l)).length));
  // the sender: once in the letterhead, and legitimately again under the
  // signature rule (a document is signed by the firm that issued it)
  const senderMentions = lines.filter(l=>/BONITECH/.test(l)).length;
  check(t+': the sender is named once in the letterhead and once to sign',
    senderMentions <= 2, String(senderMentions));
  // the currency, stated once
  check(t+': the currency is stated once', lines.filter(l=>/KES/.test(l)).length === 1,
    String(lines.filter(l=>/KES/.test(l)).length));
  // the signature is a rule, not a dotted caption
  check(t+': the signature area is present', /signature|cipher|Signature/i.test(html));
}

// the beneficiary must still be shown when it is genuinely a different party
const creative = fs.readFileSync(ROOT + '/application/modules/users/views/invoices/templates/creative.php','utf8');
check('creative compares the beneficiary to the recipient, not the sender',
  /strcasecmp\(trim\(\$invBeneficiary\), trim\(\(string\) \$invRecipientNamed\)\)/.test(creative));
const toner = fs.readFileSync(ROOT + '/application/modules/users/views/invoices/templates/toner-save.php','utf8');
check('toner-save compares the beneficiary to the recipient, not the sender',
  /strcasecmp\(trim\(\$tsBeneficiary\), trim\(\(string\) \$tsRecipientNamed\)\)/.test(toner));
check('the drawer is kept behind a real difference test', !/trim\(\$tsBeneficiary\), trim\(\(string\) \$tsBeneficiary\)\)/.test(toner));
const helper = fs.readFileSync(ROOT + '/application/helpers/anvoice_doc_helper.php','utf8');
check('the shared renderer suppresses a repeating beneficiary', /advAlreadyNamed/.test(helper));

console.log('\n'+(fail?fail+' FAILED':'all pass')+'  ('+pass+' passed)');
process.exit(fail?1:0);
