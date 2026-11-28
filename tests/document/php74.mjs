// The image runs PHP 7.4 (Dockerfile BASE_IMAGE=php:7.4-apache), but the
// harnesses here run 8.4, so a construct that is new in 8 parses in testing
// and fatals on the server. This checks the touched files for the syntax that
// only parses from 8.0 on.
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';

const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const files = execFileSync('git', ['-C', ROOT, 'diff', '--name-only', 'fd82e8d..HEAD', '--', '*.php'], { encoding: 'utf8' })
  .split('\n').filter(Boolean);

let pass = 0, fail = 0;
const check = (n, ok, d = '') => { if (ok) { pass++; console.log('  ok   ' + n); } else { fail++; console.log('  FAIL ' + n + (d ? '  <- ' + d : '')); } };

// PHP 8.0+ only
const banned = [
  [/\?->/, 'nullsafe operator (8.0)'],
  [/\bstr_contains\s*\(/, 'str_contains (8.0)'],
  [/\bstr_starts_with\s*\(/, 'str_starts_with (8.0)'],
  [/\bstr_ends_with\s*\(/, 'str_ends_with (8.0)'],
  [/(?<!pre)(?<!\w)match\s*\(\s*\$?\w+\s*(\)|\{)/, 'match expression (8.0)'],
  [/^\s*(public|protected|private)\s+(readonly)\b/m, 'readonly (8.1)'],
  [/\)\s*:\s*static\b/, 'static return type (8.0)'],
  [/\bnew\s+\w+\s*\(\s*\w+\s*:\s*/, 'named arguments (8.0)'],
  [/\[\s*\w+\s*:\s*[^\]]*\]\s*[,)]/, 'array spread with keys / 8.1 string keys'],
  /* an arrow function has a parameter list: `fn ($x)`, `fn($x) =>`. `$fn()`
     is a call to a variable, which is fine on any version - requiring the
     paren to follow a space or an identifier keeps the check honest. */
  [/(?<![$\w])fn\s*\(\s*(?:\$|&?\$\)|[^)]*\)\s*=>)/, 'arrow function (7.4 ok, flagging for review)'],
];

const bad = [];
for (const rel of files) {
  const p = ROOT + '/' + rel;
  if (!fs.existsSync(p)) continue;
  const src = fs.readFileSync(p, 'utf8');
  for (const [re, why] of banned) {
    // strip comments and strings roughly, so prose about an operator is not a hit
    const code = src.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/[^\n]*/g, '');
    if (re.test(code)) bad.push(rel + ': ' + why);
  }
}

check('no PHP 8-only syntax in the files this branch touched', bad.length === 0, bad.slice(0, 6).join('; '));

// and the runtime the image actually ships
const docker = fs.readFileSync(ROOT + '/Dockerfile', 'utf8');
check('the deploy image is PHP 7.4', /BASE_IMAGE=php:7\.4/.test(docker));

console.log('\n' + (fail ? fail + ' FAILED' : 'all pass') + '  (' + pass + ' passed)');
process.exit(fail ? 1 : 0);
