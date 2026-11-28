// Lint every PHP file the branch touched. A fatal in one shared include takes
// down pages that have nothing to do with the change, so this is the first
// thing to run when several screens stop working at once.
import { PhpNode } from 'php-wasm/PhpNode.mjs';
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';

const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const list = execFileSync('git', ['-C', ROOT, 'diff', '--name-only', process.argv[2] || 'fd82e8d..HEAD', '--', '*.php'], { encoding: 'utf8' })
  .split('\n').filter(Boolean);

let bad = 0, ok = 0;
for (const rel of list) {
  const p = ROOT + '/' + rel;
  if (!fs.existsSync(p)) continue;
  const php = new PhpNode();
  let out = '';
  php.addEventListener('output', e => { out += e.detail[0]; });
  await php.run('<?php @mkdir("/chk",0777,TRUE);');
  await php.writeFile('/f.php', fs.readFileSync(p));
  out = '';
  await php.run(`<?php
$src = file_get_contents('/f.php');
try { token_get_all($src, TOKEN_PARSE); echo 'PARSE_OK'; }
catch (ParseError $e) { echo 'PARSE_ERROR ' . $e->getMessage() . ' line ' . $e->getLine(); }
`);
  if (!out.includes('PARSE_OK')) { bad++; console.log('  FAIL ' + rel + '  ' + out.trim().slice(0, 140)); }
  else ok++;
}
console.log('\n' + (bad ? bad + ' file(s) do not parse' : 'all pass') + '  (' + ok + ' files parse)');
process.exit(bad ? 1 : 0);
