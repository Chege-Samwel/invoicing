// Which route claims a public URL.
//
// A share link is a URL printed in an email and opened months later, so a 404
// has to be attributable: was the key not in the table at all, or was it
// declared after a broader key that already matched? Reproducing the route
// table here makes that answerable without booting the app.
import { rows, resolve } from './routematch.mjs';

let pass = 0, fail = 0;
const check = (n, ok, d = '') => {
  if (ok) { pass++; console.log('  ok   ' + n); }
  else { fail++; console.log('  FAIL ' + n + (d ? '  <- ' + d : '')); }
};

check('the route table parsed', rows.length > 90, rows.length + ' keys');

const cases = [
  ['invoice/3u6b0a857c5ef90', 'home/sharedInvoice/3u6b0a857c5ef90'],
  ['share/invoice/3u6b0a857c5ef90', 'home/sharedInvoice/3u6b0a857c5ef90'],
  ['invoice/3u6b0a857c5ef90/pdf', 'home/sharedInvoicePdf/3u6b0a857c5ef90'],
  ['share/invoice/3u6b0a857c5ef90/pdf', 'home/sharedInvoicePdf/3u6b0a857c5ef90'],
  ['accept/invoice/3u6b0a857c5ef90', 'home/publicAcceptInvoice/3u6b0a857c5ef90'],
  ['user/invoice/sharelink/7', 'users/invoices/invoiceview/shareLink/7'],
  ['user/invoice/revoke-share/7', 'users/invoices/invoices/revokeShare/7'],
];

for (const [uri, expected] of cases) {
  const r = resolve(uri);
  check(uri + ' -> ' + expected,
    !!r && r.target === expected,
    r ? r.key + '  ->  ' + r.target : 'no route');
}

/*
 * The language-prefixed form must reach the same handler. It is what the
 * browser sends after visiting the app with a locale, and what a copy from
 * the address bar can carry.
 */
const prefixed = resolve('en/invoice/3u6b0a857c5ef90');
check('a language prefix still resolves the share page',
  !!prefixed, 'no route');
if (prefixed) {
  // it rewrites to the unprefixed path, which must itself resolve
  const again = resolve(prefixed.target);
  check('the rewritten path resolves to the share handler',
    !!again && again.target === 'home/sharedInvoice/3u6b0a857c5ef90',
    again ? again.target : 'no route');
}

/*
 * Ordering, which is the failure this harness exists for: CI takes the first
 * match, so /pdf must be declared before the plain (:any) key - (:any) also
 * matches a slash, so it would otherwise swallow ".../pdf" and hand the
 * template "3u6b.../pdf" as the token.
 */
const plain = rows.findIndex(r => r.key === 'invoice/(:any)');
const pdf = rows.findIndex(r => r.key === 'invoice/(:any)/pdf');
check('the pdf key is declared before the plain key',
  pdf !== -1 && plain !== -1 && pdf < plain,
  `pdf at ${pdf}, plain at ${plain}`);

const shared = resolve('invoice/3u6b0a857c5ef90/pdf');
check('a pdf URL never resolves to the plain handler',
  !!shared && shared.target.startsWith('home/sharedInvoicePdf'),
  shared ? shared.target : 'no route');
check('the token passed to the pdf handler has no /pdf suffix',
  !!shared && !/\/pdf$/.test(shared.target),
  shared ? shared.target : 'no route');

console.log('\n' + (fail ? fail + ' FAILED' : 'all pass') + '  (' + pass + ' passed)');
process.exit(fail ? 1 : 0);
