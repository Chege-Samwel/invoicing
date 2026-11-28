import { PhpNode } from 'php-wasm/PhpNode.mjs';
import fs from 'node:fs';
const root = new URL('../../', import.meta.url).pathname;
const php = new PhpNode();
let out = '';
php.addEventListener('output', e => { out += e.detail[0]; });
await php.writeFile('/trace.php', fs.readFileSync(root + 'application/adomin_trace.php', 'utf8'));
await php.run(`<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/invoice/3usecrettoken?trace=1';
require '/trace.php';
adomin_trace('index.php: started');
adomin_trace('share: resolve');
echo 'RESULT:' . json_encode(adomin_trace_current());
`);
const match = /RESULT:(\[[^\n]*\])/.exec(out);
const lines = match ? JSON.parse(match[1]) : [];
const joined = lines.join(' | ');
const checks = [
  ['share GET has its own request banner', joined.includes('method=GET')],
  ['share trace records the current stage', joined.includes('share: resolve')],
  ['share trace does not expose token or query', !joined.includes('secrettoken') && !joined.includes('trace=1')],
];
for (const [name, ok] of checks) console.log((ok ? '  ok   ' : '  FAIL ') + name);
const failures = checks.filter(([, ok]) => !ok).length;
console.log(`${failures ? failures + ' FAILED' : 'all pass'}  (${checks.length - failures} passed)`);
process.exit(failures ? 1 : 0);
