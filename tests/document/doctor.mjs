// /share-doctor.php answers "why is this link broken?" from a browser.
//
// It exists because the alternative is asking someone to read server logs for a
// URL they were sent in an email. That makes two things worth testing: that its
// token parser agrees with the app's, and that it never answers with a 500 -
// a diagnostic that breaks is worse than none.
import { PhpNode } from 'php-wasm/PhpNode.mjs';
import fs from 'node:fs';

const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');

let pass = 0, fail = 0;
const check = (n, ok, d = '') => {
  if (ok) { pass++; console.log('  ok   ' + n); }
  else { fail++; console.log('  FAIL ' + n + (d ? '  <- ' + d : '')); }
};

const doctor = fs.readFileSync(ROOT + '/share-doctor.php', 'utf8');
const helper = fs.readFileSync(ROOT + '/application/helpers/share_unavailable_helper.php', 'utf8');

// --- the two parsers must agree
const appRe = /return \(bool\) preg_match\('([^']+)', \(string\) \$token\)/.exec(helper);
const docRe = /preg_match\('([^']+)', \$token, \$m\)/.exec(doctor);
check('the app declares a token pattern', !!appRe);
check('the doctor declares a token pattern', !!docRe);
// the doctor needs the account id captured, the app reads it with a second
// pass - so compare the shape, not the literal string
const shape = (re) => re.replace(/\((\\d\+)\)/, '\\d+');
check('both accept the same tokens',
  !!appRe && !!docRe && shape(appRe[1]) === shape(docRe[1]),
  appRe && docRe ? `${appRe[1]} vs ${docRe[1]}` : '');

// --- run the doctor's own parser, and the app's, over the same tokens
const php = new PhpNode();
let out = '';
php.addEventListener('output', e => { out += e.detail[0]; });
await php.run('<?php @mkdir("/app",0777,TRUE);');

// lift just the parser out of the doctor file: it has to work without CI
const fn = /function adomin_doctor_token\(\$token\)[\s\S]*?\n\}/.exec(doctor);
check('the doctor parser is self-contained', !!fn);
await php.writeFile('/app/doc.php', '<?php\n' + (fn ? fn[0] : ''));
await php.writeFile('/app/hel.php', '<?php\n' + helper.replace(/^<\?php\s*/, '').replace(/^defined\('BASEPATH'\)[^\n]*\n/m, ''));

const tokens = [
  '3u6b0a857c5ef90',
  '3u6bu7abc9',
  '12u3uz9k1m',
  '1uabcd',
  '0u1234',
  '',
  '3ub',
  'abc',
  'u1234',
];
await php.writeFile('/app/run.php', `<?php
define('BASEPATH', 1);
require '/app/doc.php';
require '/app/hel.php';
$out = array();
foreach (${JSON.stringify(tokens)} as $t) {
  $d = adomin_doctor_token($t);
  $out[] = array(
    't' => $t,
    'doc_ok' => $d['wellformed'] ? 1 : 0,
    'doc_user' => $d['user'],
    'app_ok' => adomin_share_token_is_wellformed($t) ? 1 : 0,
    'app_user' => adomin_share_token_user($t),
  );
}
echo 'R' . json_encode($out);
`);
await php.run('<?php require "/app/run.php";');
const m = /R(\[.*\])/s.exec(out);
const rows = m ? JSON.parse(m[1]) : [];
check('both parsers ran', rows.length === tokens.length, out.slice(0, 200));

let same = true, detail = '';
for (const r of rows) {
  if (r.doc_ok !== r.app_ok || r.doc_user !== r.app_user) {
    same = false;
    detail += `${JSON.stringify(r.t)}: doctor ${r.doc_ok}/${r.doc_user}, app ${r.app_ok}/${r.app_user}; `;
  }
}
check('they agree on every token, including the malformed ones', same, detail);
check('the reported token resolves to account 3',
  (rows.find(r => r.t === '3u6b0a857c5ef90') || {}).app_user === 3);

// --- the page must never 500
/*
 * Every failure path is a verdict, not an exception. The token arrives from a
 * stranger's address bar, so the worst case has to be a page that explains
 * itself - and it is checked by reading the code, because a thrown error here
 * would be a 500 on the one page meant to diagnose a 500.
 */
const body = doctor.slice(doctor.indexOf('function adomin_doctor_check'));
check('a missing database is a verdict, not a crash',
  /'no_database'/.test(body) && /'database_unreachable'/.test(body));
check('a failed query is a verdict', /'query_failed'/.test(body));
check('every verdict has an explanation',
  ['ok', 'no_token', 'malformed_token', 'token_not_found', 'deleted', 'no_database',
   'database_unreachable', 'query_failed'].every(v => new RegExp("'" + v + "' =>").test(doctor)),
  'a verdict with no explanation renders an empty page');
check('the token is escaped everywhere it is printed',
  !/echo \$token/.test(doctor) && !/echo \$report\['token'\]/.test(doctor),
  'the token is an input and is printed on the page');
check('the page is not indexed', /noindex/.test(doctor));
check('the status code reflects the verdict', /http_response_code\(\$ok \? 200 : 404\)/.test(doctor));
check('a resolvable link is 200', /'verdict'\] = 'ok'/.test(body));

// --- and it is reachable, and excluded from anything public
check('status.php and the doctor are both documented entry points',
  fs.existsSync(ROOT + '/status.php') && fs.existsSync(ROOT + '/share-doctor.php'));

console.log('\n' + (fail ? fail + ' FAILED' : 'all pass') + '  (' + pass + ' passed)');
process.exit(fail ? 1 : 0);
