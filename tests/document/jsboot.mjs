// The runtime must survive being handed the wrong thing.
//
// The reported console error was:
//   Uncaught SyntaxError: Failed to execute 'querySelectorAll' on 'Document':
//   '[object HTMLDivElement]' is not a valid selector.
//     at all (adomon-app.js:37) -> each (39) -> initShare (412) -> boot (641)
//
// initShare() called each([modal], fn), and each() passes its first argument
// straight to querySelectorAll - which wants a selector string. The throw
// happened inside boot(), so every section after it never ran: the copy button,
// revoke, WhatsApp, SMS and e-mail were never bound. One line in the console,
// a fully dead share sheet.
//
// These are static checks rather than a DOM run: the point is that the shapes
// the helpers accept, and the order they are started in, cannot regress.
import fs from 'node:fs';

const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const app = fs.readFileSync(ROOT + '/assets/public/js/adomin-app.js', 'utf8');

let pass = 0, fail = 0;
const check = (n, ok, d = '') => {
  if (ok) { pass++; console.log('  ok   ' + n); }
  else { fail++; console.log('  FAIL ' + n + (d ? '\n         ' + d : '')); }
};

const allFn = (/function all\(sel, ctx\) \{[\s\S]*?\n    \}/.exec(app) || [''])[0];

check('all() accepts an element as well as a selector',
  /typeof sel !== 'string'/.test(allFn),
  'querySelectorAll(el) throws: "[object HTMLDivElement]" is not a valid selector');
check('all() accepts a list of elements',
  /sel\.nodeType === 1/.test(allFn) && /Array\.prototype\.slice\.call\(sel\)/.test(allFn));
check('all() still takes each()\'s one-argument form',
  /function each\(sel, fn\) \{ all\(sel\)\.forEach\(fn\); \}/.test(app));

// no call site may hand each() an array literal again. Comments describe the
// bug that was fixed, so they must not count as calls.
const appCode = app.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');
const arrayCalls = (appCode.match(/each\(\[[^\]]*\]/g) || []);
check('no call site passes an array to each()',
  arrayCalls.length === 0,
  arrayCalls.join(', '));

// one section throwing must not stop the rest
check('sections are started through a guard', /function guarded\(name, fn\)/.test(app));
const boot = (/function boot\(\) \{([\s\S]*?)\n    \}/.exec(app) || ['', ''])[1];
const bare = boot.replace(/guarded\([^;]*;/g, '').match(/init[A-Z]\w+\(\)/g) || [];
check('every init*() in boot() is guarded',
  bare.length === 0,
  'unguarded: ' + bare.join(', '));
check('the share sheet starts before the cosmetics',
  boot.indexOf("guarded('share'") !== -1 &&
  boot.indexOf("guarded('share'") < boot.indexOf("guarded('toasts'"),
  'a reader is blocked by a dead copy button, not by a missing fade');

// the script still parses
check('the script has no unbalanced braces that a static check can see',
  (app.match(/\{/g) || []).length === (app.match(/\}/g) || []).length);

console.log(`\n${fail === 0 ? 'all pass ' : ''} (${pass} passed${fail ? ', ' + fail + ' failed' : ''})`);
process.exit(fail === 0 ? 0 : 1);
