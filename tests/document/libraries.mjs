// A CodeIgniter library is assigned to the lowercased class name
// (system/core/Loader.php: `$object_name = strtolower($class)`). Reading it
// through any other spelling yields NULL, and `$this->x->y()` on NULL is a
// fatal - which is how `adomentitlements` (missing "in") took the whole /user
// area down with a 500 and no error message.
//
// This walks every $this->adomin*-> / $CI->adomin*-> reference in the tree and
// checks that the library it implies is actually loaded, spelled exactly like
// that.
import fs from 'node:fs';
import path from 'node:path';

// Harness paths. ROOT is the repository; HERE is this directory.
const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const HERE = new URL('./', import.meta.url).pathname.replace(/\/$/, '');

let pass = 0, fail = 0;
const check = (n, ok, d) => { if (ok) { pass++; console.log('  ok   ' + n); } else { fail++; console.log('  FAIL ' + n + (d ? ' — ' + d : '')); } };

const root = ROOT + '';
const files = [];
(function walk(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    if (e.name === 'node_modules' || e.name === '.git' || e.name === 'vendor') continue;
    const p = path.join(dir, e.name);
    if (e.isDirectory()) walk(p);
    else if (e.name.endsWith('.php')) files.push(p);
  }
})(path.join(root, 'application'));

const used = new Map();   // property -> [file:line]
const loaded = new Set(); // lowercased library names actually loaded

for (const f of files) {
  const src = fs.readFileSync(f, 'utf8');
  const rel = f.replace(root + '/', '');
  for (const m of src.matchAll(/(?:this|CI)->(adomin[a-z]+)->/g)) {
    if (!used.has(m[1])) used.set(m[1], []);
    used.get(m[1]).push(rel);
  }
  for (const m of src.matchAll(/load->library\s*\(\s*(?:array\s*\([^)]*\)|'([A-Za-z]+)')/g)) {
    const whole = m[0];
    for (const n of whole.matchAll(/'([A-Za-z]+)'/g)) loaded.add(n[1].toLowerCase());
  }
  if (/load->library/.test(src)) {
    for (const n of src.matchAll(/load->library\s*\(\s*array\s*\(([^)]*)\)/g)) {
      for (const q of n[1].matchAll(/'([A-Za-z]+)'/g)) loaded.add(q[1].toLowerCase());
    }
  }
}

console.log('  libraries loaded:', [...loaded].filter(x => x.startsWith('adomin')).join(', '));
console.log('');
for (const [prop, where] of [...used].sort()) {
  check('$' + prop + ' is a library the app loads', loaded.has(prop),
    'referenced in ' + [...new Set(where)].join(', '));
}

// the specific bug, asserted by name
check('no reference to the misspelt $adomentitlements',
  !files.some(f => fs.readFileSync(f, 'utf8').includes('adomentitlements')));
check('$adominentitlements is loaded where it is used',
  used.has('adominentitlements') && loaded.has('adominentitlements'));

console.log('\n' + (fail ? fail + ' FAILED' : 'all pass') + '  (' + pass + ' passed)');
process.exit(fail ? 1 : 0);
