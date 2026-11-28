// CodeIgniter's route matching, reproduced.
//
// CI3 asks the route table in order and the first key whose pattern matches
// wins. A route that is declared after a broader one is dead code, and the
// symptom is a 404 for a URL that looks correct in the file.
import fs from 'node:fs';

const src = fs.readFileSync(new URL('../../application/config/routes.php', import.meta.url), 'utf8');

const rows = [];
let inBlock = false;
for (let line of src.split('\n')) {
  let s = line;
  if (inBlock) {
    const close = s.indexOf('*/');
    if (close === -1) continue;
    s = s.slice(close + 2);
    inBlock = false;
  }
  const open = s.indexOf('/*');
  if (open !== -1) {
    s = s.slice(0, open);
    inBlock = true;
  }
  s = s.replace(/(^|;)\s*\/\/.*$/, '$1');
  for (const m of s.matchAll(/\$route\[\s*(['"])(.*?)\1\s*\]\s*=\s*(['"])(.*?)\3/g)) {
    const key = m[2];
    if (['default_controller', '404_override', 'translate_uri_dashes'].includes(key)) continue;
    rows.push({ key, target: m[4] });
  }
}

const toRegex = (key) => new RegExp('^' + key.replace(/:any/g, '.+').replace(/:num/g, '[0-9]+') + '$');

function resolve(uri) {
  for (let i = 0; i < rows.length; i++) {
    let re;
    try { re = toRegex(rows[i].key); } catch (e) { continue; }
    const m = re.exec(uri);
    if (!m) continue;
    // $1 .. $n substitution, as CI does for the matched groups
    const target = rows[i].target.replace(/\$(\d)/g, (_, n) => m[Number(n)] ?? '');
    return { index: i, key: rows[i].key, target, groups: m.slice(1) };
  }
  return null;
}

export { rows, resolve };

if (process.argv[2] === '--run') {
  const urls = process.argv.slice(3);
  console.log(`route table: ${rows.length} keys\n`);
  for (const uri of urls) {
    const r = resolve(uri);
    console.log(uri.padEnd(34), r ? `#${r.index}  ${r.key}  ->  ${r.target}` : 'NO ROUTE (segments fall through to the router)');
  }
}
