// Render the real share template twice - with the request table present and
// absent - and read what a client would actually see. A harness that only reads
// the source cannot catch "the button is drawn but the click fails".
import { PhpNode } from 'php-wasm/PhpNode.mjs';
import fs from 'node:fs';

// Harness paths. ROOT is the repository; HERE is this directory.
const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const HERE = new URL('./', import.meta.url).pathname.replace(/\/$/, '');

const tpl = fs.readFileSync(ROOT + '/application/views/invoices_parts/invoice_share.php','utf8');

async function render(canRecord){
  const php = new PhpNode(); let out='';
  php.addEventListener('output', e=>{ out += e.detail[0]; });
  await php.run('<?php @mkdir("/app",0777,TRUE);');
  await php.writeFile('/app/share.php', tpl);
  await php.writeFile('/app/run.php', `<?php
define('BASEPATH',1);
function base_url($p=''){ return 'https://x.test/'.ltrim((string)$p,'/'); }
function lang($k){ return $k; }
function html_escape($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function adomin_csrf_field(){ return '<input type="hidden" name="csrf_test" value="abc">'; }
function adomin_stamp_label(){ return 'Powered by anvoice'; }
function adomin_share_credit(){ return 'Create and send invoices with <a href="#">anvoice</a>'; }
function adomin_conf($g,$k=NULL){ return $k==='website' ? 'https://x.test' : 'anvoice'; }
function adomin_stamp(){ return 'stamp'; }
$invoice = array(
  'id'=>7,'for_user'=>3,'status'=>'issued','inv_currency'=>'KES',
  'date_create'=>time(),'date_tax_event'=>time(),
  'firm'=>array('name'=>'BONITECH LTD'),
  'client'=>array('client_name'=>'Jane Doe'),
);
$firmName='BONITECH LTD'; $currency='KES';
$templateFile='/app/none.php';
$shareToken='tok123';
$pdfUrl='https://x.test/share/invoice/tok123/pdf';
$pendingRequest=NULL; $justRequested='';
$canRequest=TRUE; $canRecord=${canRecord?'TRUE':'FALSE'};
ob_start(); include '/app/share.php'; echo ob_get_clean();
`);
  await php.run('<?php require "/app/run.php";');
  return out;
}

let pass=0, fail=0;
const check=(n,ok,d)=>{ if(ok){pass++;console.log('  ok   '+n);} else {fail++;console.log('  FAIL '+n+(d?' — '+d:''));} };

const withTable = await render(true);
const noTable   = await render(false);

// --- table present: the client can ask
check('with the table, the accept form is shown', /name="decision" value="accepted"/.test(withTable));
check('with the table, both answers are offered',
  /value="accepted"/.test(withTable) && /value="refused"/.test(withTable));
check('with the table, no apology is printed', !/not available on this link/.test(withTable));
check('with the table, the form carries CSRF', /csrf_test/.test(withTable));

// --- table absent: no dead control, and a sentence the client can act on
check('without the table, no accept button is drawn', !/value="accepted"/.test(noTable));
check('without the table, no refuse button is drawn', !/value="refused"/.test(noTable));
check('without the table, the client is told', /Accepting is not available on this link right now/.test(noTable));
check('without the table, the client is given a next step', /Reply to the\s+business/.test(noTable));
check('without the table, the document still renders', /BONITECH LTD/.test(noTable));
check('without the table, nothing claims a decision was taken',
  !/Your acceptance has been sent/.test(noTable) && !/recorded as accepted/.test(noTable));

// --- both: the document is whole either way
for (const [label, html] of [['with', withTable], ['without', noTable]]) {
  check(label+' the table: no PHP error in the output', !/Fatal|Warning|Notice/.test(html),
    (html.match(/(Fatal|Warning|Notice)[^\n]*/)||[])[0] || '');
  check(label+' the table: no company logo above the document', !/adomin_logo_url|class="logo"/.test(html));
  check(label+' the table: one credit line', (html.match(/adomin-share-credit/g)||[]).length===1);
  check(label+' the table: print hides the controls', /adomin-no-print/.test(html));
}

console.log('\n'+(fail?fail+' FAILED':'all pass')+'  ('+pass+' passed)');
process.exit(fail?1:0);
