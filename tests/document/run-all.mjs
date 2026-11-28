// Runs every harness and reports one line each. Fails if any fails.
//
// These are the checks behind the claims in the commit messages: what the
// document renders, what it costs, what the share page does when its table is
// missing, and whether the QR code is actually drawn.
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';

const HERE = new URL('./', import.meta.url).pathname;
// generators the other harnesses drive, not checks in their own right
const skip = new Set(['run-all.mjs', 'qrender.mjs', 'lintone.mjs', 'lintall.mjs']);
const checks = fs.readdirSync(HERE)
  .filter(f => f.endsWith('.mjs') && !skip.has(f))
  .sort();

let failed = 0, passed = 0, total = 0;
for (const f of checks) {
  const r = spawnSync(process.execPath, [HERE + f], { encoding: 'utf8' });
  const out = (r.stdout || '') + (r.stderr || '');
  const summary = out.trim().split('\n').pop() || '';
  const ok = r.status === 0;
  if (ok) passed++; else failed++;
  const n = /\((\d+) passed\)/.exec(out);
  if (n) total += Number(n[1]);
  console.log(`${ok ? ' ok  ' : ' FAIL'} ${f.padEnd(20)} ${summary.replace(/^\\n/, '')}`);
  if (!ok) {
    for (const line of out.split('\n').filter(l => l.includes('FAIL '))) console.log('       ' + line.trim());
  }
}

console.log(`\n${failed ? failed + ' harness(es) failed' : 'all harnesses pass'} — ${total} assertions across ${checks.length} files`);
process.exit(failed ? 1 : 0);
