// The service worker.
//
// A service worker can break every screen at once while every page still
// returns 200: it answers from a cache instead of the server, so a click does
// exactly what the *cached* page says it does. A status that never changes, a
// popup that opens empty, a preview that never updates are all the same
// symptom - a stale response - and none of them appear in a PHP log.
//
// These checks take the worker's own decision functions and exercise them.
import fs from 'node:fs';

const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const src = fs.readFileSync(ROOT + '/sw.js', 'utf8');

let pass = 0, fail = 0;
const check = (n, ok, d = '') => {
  if (ok) { pass++; console.log('  ok   ' + n); }
  else { fail++; console.log('  FAIL ' + n + (d ? '  <- ' + d : '')); }
};

// pull a top-level function out by brace matching, so the check runs the code
// the worker actually ships rather than a copy of it
const grab = (name) => {
  const i = src.indexOf('function ' + name);
  if (i < 0) return null;
  const j = src.indexOf('{', i);
  let depth = 0;
  for (let k = j; k < src.length; k++) {
    if (src[k] === '{') depth++;
    else if (src[k] === '}') {
      depth--;
      if (depth === 0) return new Function('return (' + src.slice(i, k + 1) + ')')();
    }
  }
  return null;
};

const isStatic = grab('isStatic');
const cacheablePage = grab('cacheablePage');
check('the worker declares its decision functions', !!isStatic && !!cacheablePage);

if (isStatic) {
  // shell assets: file types served from cache
  check('a script is a shell asset', isStatic('/assets/users/js/general.js') === true);
  check('a stylesheet is a shell asset', isStatic('/assets/users/css/general.css') === true);
  check('the manifest is a shell asset', isStatic('/manifest.json') === true);
  check('an image is a shell asset', isStatic('/assets/public/imgs/logo.png') === true);

  // ...and anything that answers a question is not
  check('a JSON endpoint is not a shell asset', isStatic('/api/data.json') === false);
  check('a status route is not a shell asset', isStatic('/en/user/invoices/changeinvoicestatus') === false);
  check('a share route is not a shell asset', isStatic('/en/user/invoices/sharelink/12') === false);
  check('an undefined path does not throw', isStatic(undefined) === false);
}

if (cacheablePage) {
  // This is the bug that made every signed-in screen look broken: pages are
  // addressed with a language prefix, so a check against the start of the path
  // saw '/en/user/...' and cached every one of them.
  const GET = { method: 'GET', headers: { get: () => null } };
  const page = (p) => cacheablePage(GET, new URL('https://x.test' + p));

  check('/en/user/ pages are not cached', page('/en/user/invoices') === false);
  check('a prefixed preview page is not cached', page('/en/user/invoice/preview/tax_inv/12') === false);
  check('a prefixed page in French is not cached', page('/fr/user/invoices') === false);
  check('the prefixed admin panel is not cached', page('/en/admin/settings') === false);
  check('/en/login is not cached', page('/en/login') === false);

  check('the landing page is cacheable', page('/en') === true);
  check('a public share link is cacheable', page('/invoice/abc123') === true);
}

// the shared fetch path must not store a response that answers a question
const tail = src.slice(src.indexOf('// PDFs and everything else'));
check('the shared fetch path only stores documents',
  /isDocument/.test(src) && /text\/html/.test(src),
  'a JSON or plain-text reply must not be written to the page cache');
check('the shared fetch path skips pages that change something',
  /mutating/.test(src));
check('nothing under /user/ is stored', /url\.pathname\.indexOf\('\/user\/'\) !== 0/.test(src));

// A new build has to drop the caches the old one filled, or a fixed bug stays
// broken on a device that already cached the broken page. That used to depend
// on someone remembering to edit a constant - so what is checked here is that
// the version is derived from the runtime's own bytes, not that it has a value.
check('the cache version is derived from the shell files',
  /fingerprint\(RUNTIME\)/.test(src) && /fingerprint\(STYLE\)/.test(src),
  'a hand-edited constant cannot retire a stale adomin-app.js');
const opens = src.match(/caches\.open\([^)]*\)/g) || [];
const unnamed = opens.filter(o => /caches\.open\((SHELL|PAGES)\)$/.test(o));
check('every cache is opened under the resolved build',
  opens.length >= 5 && unnamed.length === 0,
  unnamed.length ? unnamed.join(' ') : 'no cache opens found');
check('activate deletes caches from other builds', /caches\.delete/.test(src));

// the worker must not throw inside a fetch handler: that answers the whole
// navigation with a network error
check('a response is guarded before its headers are read',
  /response && response\.headers && response\.headers\.get/.test(src));

console.log('\n' + (fail ? fail + ' FAILED' : 'all pass') + '  (' + pass + ' passed)');
process.exit(fail ? 1 : 0);
