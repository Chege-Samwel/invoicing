// "Accept by link" was reported broken. It is not the buttons: it is that the
// table the click is filed into only existed in database/adomin-updates.sql,
// which is applied by hand. On a host that ran only database.sql the client
// pressed I accept and was told it could not be sent - and the client is the
// one person who cannot run a migration.
import fs from 'node:fs';

// Harness paths. ROOT is the repository; HERE is this directory.
const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const HERE = new URL('./', import.meta.url).pathname.replace(/\/$/, '');
let pass=0, fail=0;
const check=(n,ok,d)=>{ if(ok){pass++;console.log('  ok   '+n);} else {fail++;console.log('  FAIL '+n+(d?' — '+d:''));} };
const R=p=>fs.readFileSync(ROOT + '/'+p,'utf8');
const strip=src=>src.replace(/\/\*[\s\S]*?\*\//g,'').replace(/\/\/.*$/gm,'');

// ---- the schema is complete on its own
const dump=R('database.sql');
const updates=R('database/adomin-updates.sql');
const tables=s=>new Set([...s.matchAll(/CREATE TABLE (?:IF NOT EXISTS )?`(\w+)`/g)].map(m=>m[1]));
const inDump=tables(dump), inUpdates=tables(updates);
const missing=[...inUpdates].filter(t=>!inDump.has(t));
check('every table the app needs is in database.sql', missing.length===0, missing.join(','));
check('the status-request table is in the dump', inDump.has('invoice_status_requests'));
check('the share-view table is in the dump', inDump.has('invoice_share_views'));

// the two definitions must match, or a host gets a different table depending
// on which file it ran
const def=(src,name)=>{const m=new RegExp('CREATE TABLE IF NOT EXISTS `'+name+'` \\((.*?)\\n\\) ENGINE','s').exec(src);return m?m[1]:'';};
for (const t of ['invoice_status_requests','invoice_share_views']) {
  const a=def(dump,t).replace(/\s+/g,' ').trim();
  const b=def(updates,t).replace(/\s+/g,' ').trim();
  check('the two definitions of '+t+' agree', a===b, a.slice(0,80)+' vs '+b.slice(0,80));
}

// ---- fresh-install blockers
const usersBlock=/CREATE TABLE `users` \((.*?)\n\) ENGINE/s.exec(dump)[1];
for (const col of ['name','phone','schiffer']) {
  check('users.'+col+' has a default (registration inserts it empty)',
    new RegExp('`'+col+'` [^,]*NOT NULL DEFAULT \'\'').test(usersBlock),
    (usersBlock.match(new RegExp('`'+col+'`[^,\\n]*'))||[])[0]);
}
check('firms_users.logo_url exists', /CREATE TABLE `firms_users` \((.*?)\n\) ENGINE/s.exec(dump)[1].includes('logo_url'));
check('invoices.uniqid is indexed', /ADD KEY `idx_invoices_uniqid`/.test(dump));

// ---- the library still guards for the table being absent
const model=R('application/models/StatusrequestsModel.php');
check('request() refuses when the table is missing', /if \(!\$this->available\(\)\) \{\s*return FALSE;/.test(model));
check('available() checks the real table', /table_exists\(\$this->table\)/.test(model));
check('a request supersedes the previous one', /resolve\(\$invoiceId, 'superseded'\)/.test(model));

// ---- the page never offers a control that cannot work
const home=R('application/controllers/Home.php');
const view=R('application/views/invoices_parts/invoice_share.php');
check('the controller tells the view whether a click can be recorded', /canRecord'\] = \$this->StatusrequestsModel->available\(\)/.test(home));
check('the form is gated on it', /canRequest\) && empty\(\$pendingRequest\) && !empty\(\$canRecord\)/.test(view));
check('the absence is explained to the client', /Accepting is not available on this link right now/.test(view));
check('the explanation is not printed', /adomin-no-print/.test(view));
check('the accept and refuse buttons still post a decision',
  /name="decision" value="accepted"/.test(view) && /name="decision" value="refused"/.test(view));
check('the form carries a CSRF token', /adomin_csrf_field\(\)/.test(view));
check('CSRF protection is on for this route', /csrf_protection'\] = TRUE/.test(R('application/config/config.php')));

// ---- the flow's shape: a click files a request, it does not set a status
const status=R('application/libraries/AdominStatus.php');
check('only accepted/refused may be requested',
  /requestable = array\('accepted', 'refused'\)/.test(status.replace(/\s+/g,' ')));
check('the share page does not change invoices.status itself',
  !/update.*invoices.*status/i.test(strip(view)));
check('the POST redirects back to a GET so a refresh cannot double-file',
  /redirect\(base_url\('share\/invoice\//.test(home));

console.log('\n'+(fail?fail+' FAILED':'all pass')+'  ('+pass+' passed)');
process.exit(fail?1:0);
