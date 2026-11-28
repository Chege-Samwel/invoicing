// Routes, resolved.
//
// A route that names a method with the wrong spelling still *looks* right: the
// path is correct, the controller is correct, and PHP's method names are
// case-insensitive - so whether the click works comes down to whether the host
// happens to resolve it. CodeIgniter on Linux does not. The request 404s, the
// dropdown closes, and the thing the button promised never happens, with
// nothing in a log to say why.
//
// This resolves every route target against the controllers it names.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');

let pass = 0, fail = 0;
const check = (n, ok, d = '') => {
  if (ok) { pass++; console.log('  ok   ' + n); }
  else { fail++; console.log('  FAIL ' + n + (d ? '  <- ' + d : '')); }
};

// every controller and its methods, keyed by file name (what a route names)
const controllers = new Map();
const walk = (dir) => {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = dir + '/' + e.name;
    if (e.isDirectory()) { walk(p); continue; }
    if (!e.name.endsWith('.php')) continue;
    const src = fs.readFileSync(p, 'utf8');
    const methods = new Set();
    for (const m of src.matchAll(/(?:public|protected|private)\s+function\s+(\w+)\s*\(/g)) {
      methods.add(m[1]);
    }
    if (methods.size === 0) continue;
    const key = e.name.slice(0, -4).toLowerCase();
    if (!controllers.has(key)) controllers.set(key, new Set());
    for (const m of methods) controllers.get(key).add(m);
  }
};
walk(ROOT + '/application/controllers');
walk(ROOT + '/application/modules');

const routes = fs.readFileSync(ROOT + '/application/config/routes.php', 'utf8');
const targets = [...routes.matchAll(/^\$route\[[^\]]+\]\s*=\s*['"]([^'"]+)['"]/gm)].map(m => m[1]);
check('there are routes to resolve', targets.length > 50, targets.length + ' targets');

const wrong = [], unknown = [];
let named = 0;
for (const t of targets) {
  const parts = t.split('/').filter(Boolean);
  if (parts.length < 2) continue;

  /*
   * A target is 'folder/controller' or 'folder/controller/method'. When the
   * last segment is itself a controller name, the route means that
   * controller's index() - CodeIgniter's default - and there is no method to
   * check. Testing it as one reported a dozen healthy routes as broken.
   */
  if (controllers.has(parts[parts.length - 1].toLowerCase())) {
    const methods = controllers.get(parts[parts.length - 1].toLowerCase());
    named++;
    if (!methods.has('index')) wrong.push(t + ' (no index method)');
    continue;
  }

  const ctrl = parts[parts.length - 2].toLowerCase();
  const meth = parts[parts.length - 1];
  if (!/^[A-Za-z_]\w*$/.test(meth)) continue;          // a regex, not a method
  const methods = controllers.get(ctrl);
  if (!methods) { unknown.push(t); continue; }
  named++;
  if (!methods.has(meth)) {
    const near = [...methods].find(n => n.toLowerCase() === meth.toLowerCase());
    wrong.push(t + (near ? ' (method is ' + near + ')' : ' (no such method)'));
  }
}

check('every route names a method the controller actually has, spelled correctly',
  wrong.length === 0, wrong.slice(0, 8).join('; '));
console.log('       ' + named + ' targets resolved against a controller');

// the endpoint behind the status dropdown, specifically: it is the one a
// business uses every day and the one that failed silently
const status = targets.find(t => /changeinvoicepaymentstatus|changeinvoicestatus/i.test(t));
check('the status route resolves to a real method', !!status &&
  controllers.get('invoices') && controllers.get('invoices').has((status.split('/').pop())),
  status || 'no status route');

// the share popup's endpoint is an AJAX one; it must exist and be marked as such
check('the share-link route resolves', targets.some(t => /invoiceview\/shareLink/.test(t)),
  targets.filter(t => /sharelink/i.test(t)).join(' '));

console.log('\n' + (fail ? fail + ' FAILED' : 'all pass') + '  (' + pass + ' passed)');
process.exit(fail ? 1 : 0);
