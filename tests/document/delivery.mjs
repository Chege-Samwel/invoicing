// A deploy must actually reach the browser.
//
// The copy button "not working" and the "missing components" in a preview
// screenshot were both reported on a deploy whose source was already correct.
// The reason is the same in both cases: a file was linked by bare URL, the
// browser (or the service worker) kept the previous copy, and the page ran new
// markup against old code. A missing class in old CSS looks exactly like a
// missing component.
//
// So these checks are about delivery, not about behaviour: every page-lifetime
// asset carries a version, and the worker's cache is keyed to file contents.
import fs from 'node:fs';

const ROOT = new URL('../../', import.meta.url).pathname;
let pass = 0, fail = 0;
const check = (n, ok, d = '') => {
  if (ok) { pass++; console.log('  ok   ' + n); }
  else { fail++; console.log('  FAIL ' + n + (d ? '  <- ' + d : '')); }
};

// the layouts that build a page: user panel, public pages, share/accept page
const footers = [
  'application/views/parts/footer.php',
  'application/modules/users/views/parts/footer.php',
  'application/views/parts/invaccept/footer.php',
];
const headers = [
  'application/views/parts/header.php',
  'application/modules/users/views/parts/header.php',
  'application/views/parts/invaccept/header.php',
];
/*
 * Layouts own the page lifetime; individual views may carry a one-off script.
 * A one-off is just as capable of surviving a deploy, so any asset reference in
 * the public and user trees is held to the same rule.
 */
const viewsUnder = (dir) => {
  const out = [];
  const walk = (d) => {
    for (const e of fs.readdirSync(ROOT + d, { withFileTypes: true })) {
      const rel = d + '/' + e.name;
      if (e.isDirectory()) walk(rel);
      else if (e.name.endsWith('.php')) out.push(rel);
    }
  };
  walk(dir);
  return out;
};
const scopes = ['application/views', 'application/modules/users/views'];

const read = (p) => fs.readFileSync(ROOT + p, 'utf8');

// every asset reference in a layout, whichever way it is written
const refs = (src) => {
  const out = [];
  const re = /<(script|link)[^>]*(?:src|href)="<\?=\s*([^?]+?)\s*\?>"[^>]*>/g;
  for (const m of src.matchAll(re)) {
    out.push({ tag: m[1], expr: m[2].trim(), full: m[0] });
  }
  return out;
};

/*
 * The runtime that binds the share modal and the sheets that give it its
 * appearance. Each of these was, at some point, linked without a version.
 */
const critical = [
  'assets/public/js/adomin-app.js',
  'assets/public/js/general.js',
  'assets/public/js/adomin-install.js',
  'assets/public/css/adomin-animations.css',
  'assets/public/css/adomin-brand.css',
  'assets/users/css/invoices-templates.css',
  'assets/users/css/invoice-adoc.css',
];

for (const page of [...headers, ...footers]) {
  const src = read(page);
  for (const file of critical) {
    if (!src.includes(file)) continue;
    const bare = new RegExp("base_url\\(\\s*'" + file.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + "'\\s*\\)");
    check(`${page.split('/').pop()} versions ${file.split('/').pop()}`,
      !bare.test(src),
      'linked by bare URL - a browser may keep the previous copy across a deploy');
  }
}

// and every other asset reference in those trees
const bareIn = (file) => {
  const src = read(file);
  const out = [];
  for (const m of src.matchAll(/base_url\(\s*'(assets\/[^']*\.(?:css|js))'\s*\)/g)) {
    out.push(m[1]);
  }
  return out;
};
const offenders = [];
for (const dir of scopes) {
  for (const file of viewsUnder(dir)) {
    for (const asset of bareIn(file)) offenders.push(file + ' -> ' + asset);
  }
}
check('no view links a stylesheet or script by bare URL',
  offenders.length === 0,
  offenders.slice(0, 4).join(' | '));

/*
 * adomin_app_js() is emitted by all three footers through one helper, so the
 * helper is the single place that has to be right.
 */
const helper = read('application/helpers/adomin_helper.php');
check('adomin_app_js() stamps the runtime with its mtime',
  /adomin_asset\('assets\/public\/js\/adomin-app\.js'\)/.test(helper),
  'the unversioned URL was the reason a fixed copy handler stayed broken');

check('adomin_asset() adds the modification time',
  /\?v=/.test(helper) && /filemtime/.test(helper));

/*
 * The worker's own layer. A shell cache whose name is a constant survives
 * every deploy, so the previous runtime answers the next request.
 */
const sw = read('sw.js');
check('the worker derives its cache version from the files',
  /fingerprint\(RUNTIME\)/.test(sw) && /fingerprint\(STYLE\)/.test(sw));
check('the worker claims the runtime and the animations sheet',
  /const RUNTIME = '\.\/assets\/public\/js\/adomin-app\.js'/.test(sw) &&
  /const STYLE = '\.\/assets\/public\/css\/adomin-animations\.css'/.test(sw));
check('the worker retires caches from older builds',
  /indexOf\(SHELL\) === 0 \|\| k\.indexOf\(PAGES\) === 0/.test(sw) &&
  /caches\.delete/.test(sw));
check('no cache is opened under a fixed name',
  !/caches\.open\((SHELL|PAGES)\)/.test(sw));

/*
 * And the copy handler itself: it must say something when there is nothing to
 * copy, because doing nothing is indistinguishable from being broken.
 */
const app = read('assets/public/js/adomin-app.js');
check('a copy with no URL explains itself',
  /The link is still being created/.test(app) && /No link to copy yet/.test(app));
check('a successful copy changes the button, not just a class',
  /function copied\(btn\)/.test(app) && /data-adomin-copied/.test(app));
check('the copy animation exists in the animation sheet',
  /adomin-copy-ring/.test(read('assets/public/css/adomin-animations.css')));
/*
 * Revoke is the only control in the sheet that can act before the link exists,
 * and it must not be drawn as a dead one: its address comes from the invoice id
 * the page already holds, not from the request.
 */
check('revoke gets its address from the invoice id, before the request',
  /var revokeNow = modal\.querySelector\('\[data-share-action="revoke"\]'\)/.test(app) &&
  /revokeNow\.href = revokeUrl/.test(app),
  'it shipped as href="javascript:void(0)" and was only filled after a successful copy-link');
check('revoke is not marked as waiting on the request',
  /getAttribute\('data-share-action'\) === 'revoke'\) \{ return; \}/.test(app),
  'a control drawn as pending reads as broken');
check('the button says it rotates the link',
  /adomin_regenerate_link/.test(read('application/modules/users/views/invoices/share_modal.php')) &&
  /adomin_revoke_hint/.test(read('application/language/english/users_lang.php')),
  'revoke and regenerate are the same act and the reader needs both words');

// the waiting state has to exist on the stylesheet that always loads: the one
// it lived in is only linked when animations are switched on
check('the waiting state is styled without the motion layer',
  /\.adomin-share-pending\s*\{[^}]*opacity/.test(read('assets/public/css/adomin-brand.css')),
  'adomin-share-pending was only defined in adomin-animations.css');

// a failed request must leave the sheet open, not hide the one way back in
check('a failed link request keeps the sheet open',
  !/data\.status !== 'success'[\s\S]{0,200}modal\('hide'\)/.test(app),
  'the sheet hid itself on failure and left only a toast');
check('a late reply cannot fill the sheet with another invoice\'s link',
  /sharingFor !== null && id !== sharingFor/.test(app),
  'two invoices opened in sequence could cross replies');
check('the copy button cannot be pressed into silence while pending',
  /aria-disabled/.test(app) && /adomin-share-pending/.test(app));

check('the pending state is visible',
  /adomin-share-pending/.test(app) && /adomin-share-pending/.test(read('assets/public/css/adomin-animations.css')));

console.log('\n' + (fail ? fail + ' FAILED' : 'all pass') + '  (' + pass + ' passed)');
process.exit(fail ? 1 : 0);
