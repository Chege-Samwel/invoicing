// The share token.
//
// A token is `<userId>u<random>`:
//
//     3u6b0a857c5ef90
//
// uniqid() returns hex, so the random part contains the letter 'u' about once
// in seven tokens. The controllers split the token with explode('u', $token),
// which splits on *every* u, not the first - so those tokens were cut short
// and looked up as something that was never in the database. The link 404'd
// for those invoices and worked for the others, which reads like "the share
// link is broken" rather than "the parser is broken".
//
// These run the real helpers.
import { PhpNode } from 'php-wasm/PhpNode.mjs';
import fs from 'node:fs';

const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');

let pass = 0, fail = 0;
const check = (n, ok, d = '') => {
  if (ok) { pass++; console.log('  ok   ' + n); }
  else { fail++; console.log('  FAIL ' + n + (d ? '  <- ' + d : '')); }
};

const php = new PhpNode();
let out = '';
php.addEventListener('output', e => { out += e.detail[0]; });
await php.run('<?php @mkdir("/app",0777,TRUE);');
await php.writeFile('/app/share_unavailable_helper.php', fs.readFileSync(ROOT + '/application/helpers/share_unavailable_helper.php'));

// the tokens that matter: the one reported, and ones whose random part has a u
const tokens = [
  ['3u6b0a857c5ef90', 3,  '6b0a857c5ef90'],
  ['3u6bu7abc9',      3,  '6bu7abc9'],
  ['12u3uz9k1m',      12, '3uz9k1m'],
  ['1uabcu123',       1,  'abcu123'],
];

await php.writeFile('/t.php', `<?php
define('BASEPATH', 1);
require '/app/share_unavailable_helper.php';
$tokens = ${JSON.stringify(tokens.map(t => t[0]))};
$out = array();
foreach ($tokens as $t) {
  $parts = explode('u', $t);
  $out[] = array(
    'token' => $t,
    'user' => adomin_share_token_user($t),
    'wellformed' => adomin_share_token_is_wellformed($t) ? 1 : 0,
    'naive' => isset($parts[1]) ? $parts[1] : '',
  );
}
echo 'T' . json_encode($out);
`);
await php.run('<?php include "/t.php";');
const m = /T(\[.*\])/s.exec(out);
const rows = m ? JSON.parse(m[1]) : [];
check('the token helpers run', rows.length === tokens.length, out.slice(0, 200));

const byToken = Object.fromEntries(rows.map(r => [r.token, r]));

for (const [token, user, random] of tokens) {
  const r = byToken[token] || {};
  check('account id read from ' + token, r.user === user, String(r.user));
  check(token + ' is well formed', r.wellformed === 1);
}

// the bug, stated as a check: the old parser loses the rest of the token
const bad = byToken['3u6bu7abc9'];
if (bad) {
  check('the old parser truncated a token containing u',
    bad.naive === '6b', bad.naive);
  check('the new parser keeps the whole random part',
    bad.user === 3, String(bad.user));
}

// a report that a link does not work must be reproducible from the token alone
check('the reported token resolves to its account',
  (byToken['3u6b0a857c5ef90'] || {}).user === 3);

// malformed tokens still fail closed
const badTokens = ['', 'abc', 'u123', '0u', '3ub'];
await php.writeFile('/bad.php', `<?php
define('BASEPATH', 1);
require '/app/share_unavailable_helper.php';
$out = array();
foreach (${JSON.stringify(badTokens)} as $t) {
  $out[] = array($t, adomin_share_token_user($t), adomin_share_token_is_wellformed($t) ? 1 : 0);
}
echo 'B' . json_encode($out);
`);
out = '';
await php.run('<?php include "/bad.php";');
const bm = /B(\[.*\])/s.exec(out);
const badRows = bm ? JSON.parse(bm[1]) : [];
check('malformed tokens yield no account',
  badRows.length === badTokens.length && badRows.every(r => r[1] === 0),
  JSON.stringify(badRows));
check('a token with an empty account id is not well formed',
  badRows.every(r => !(r[0] === '0u' && r[2] === 1)));
check('a token with no random part yields no account',
  badRows.every(r => r[0] !== '3ub' || r[1] === 0),
  '3ub has a user id and nothing after it');
check('"0u123" is rejected even though it parses', badRows.every(r => r[0] !== '0u' || r[1] === 0));

// and no controller may go back to splitting on every u
const home = fs.readFileSync(ROOT + '/application/controllers/Home.php', 'utf8');
const homeCode = home.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/[^\n]*/g, '');
check('no controller splits the token on every u',
  !/explode\('u'/.test(homeCode),
  'explode(\'u\', ...) truncates a token whose random part contains u');
check('the share page uses the shared parser',
  /adomin_share_token_user\(\$uniqid\)/.test(home));
check('the accept page uses the shared parser',
  (home.match(/adomin_share_token_user\(/g) || []).length >= 2);

console.log('\n' + (fail ? fail + ' FAILED' : 'all pass') + '  (' + pass + ' passed)');
process.exit(fail ? 1 : 0);
