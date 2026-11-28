import { PhpNode } from 'php-wasm/PhpNode.mjs';
import fs from 'node:fs';

// Harness paths. ROOT is the repository; HERE is this directory.
const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const HERE = new URL('./', import.meta.url).pathname.replace(/\/$/, '');
const R = ROOT + '';
const php = new PhpNode(); let out = '';
php.addEventListener('output', e => { out += e.detail[0]; });

let pass = 0, fail = 0;
const check = (n, ok, d) => { if (ok) { pass++; console.log('  ok   ' + n); } else { fail++; console.log('  FAIL ' + n + (d ? ' — ' + d : '')); } };

for (const d of ['/app/application/config', '/app/application/libraries', '/app/application/helpers']) {
  await php.run('<?php @mkdir("' + d + '",0777,TRUE);');
}
for (const f of ['application/config/adomin_plans.php', 'application/config/adomin.php',
                 'application/libraries/AdominPricing.php', 'application/libraries/AdominEntitlements.php',
                 'application/libraries/AdominDocs.php', 'application/helpers/adomin_helper.php']) {
  await php.writeFile('/app/' + f, fs.readFileSync(R + '/' + f));
}

await php.writeFile('/app/run.php', `<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
define('BASEPATH',1); define('APPPATH','/app/application/'); define('FCPATH','/app/');
function base_url($p=''){ return 'https://x.test/'.ltrim((string)$p,'/'); }
function lang($k){ return $k; }
function html_escape($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function adomin_tz_abbr(){ return 'EAT'; }
function log_message($l,$m){}
class CfgStub { public $config = array(); }
class SessionStub { private $d=array(); function userdata($k){ return isset($this->d[$k])?$this->d[$k]:NULL; } function set_userdata($k,$v){ $this->d[$k]=$v; } }
class InputStub { function cookie($k){ return isset($_COOKIE[$k])?$_COOKIE[$k]:NULL; } }
// CodeIgniter's loader stores a library under its lowercased class name
class CILoad { function helper($n){} function library($n){ $i = strtolower($n); $GLOBALS['CI']->$i = new $n(); } }
class ResStub { private $rows; function __construct($rows){ $this->rows=$rows; } function row_array(){ return $this->rows ? $this->rows[0] : NULL; } }
class CILoad2 {}
class DbStub {
  public $planRows = array();
  function query($sql, $binds = array()) {
    if (strpos($sql, 'FROM firms_plans') !== FALSE) return new ResStub($this->planRows);
    return new ResStub(array(array('num' => 0)));
  }
}
class CIStub { public $config; public $session; public $input; public $load; public $db;
  function __construct(){ $this->config=new CfgStub(); $this->session=new SessionStub(); $this->input=new InputStub();
    $this->db=new DbStub(); $this->load=new CILoad(); } }
require '/app/application/config/adomin.php'; $GLOBALS['CFG']=$config;
function adomin_conf($g,$k=NULL){ $c=isset($GLOBALS['CFG'][$g])?$GLOBALS['CFG'][$g]:array(); if($k===NULL) return $c; return isset($c[$k])?$c[$k]:NULL; }
require '/app/application/helpers/adomin_helper.php';
$GLOBALS['CI'] = new CIStub(); function &get_instance(){ return $GLOBALS['CI']; }
require '/app/application/libraries/AdominPricing.php';
$GLOBALS['CI']->adominpricing = new AdominPricing();
require '/app/application/libraries/AdominEntitlements.php';
define('USER_ID', 3);
require '/app/application/libraries/AdominDocs.php';

// only the watermark() method is under test; the class needs no db
$docs = new AdominDocs();

function wm($label) {
  global $docs;

  $html = $docs->watermark(array());
  echo "WM $label=" . (strpos($html, 'adomin-watermark') !== FALSE ? 'yes' : 'no') . "\\n";
  echo "WMCOLOR $label=" . (preg_match('/fill-opacity="([0-9.]+)"/', $html, $m) ? $m[1] : 'none') . "\\n";
  echo "WMTEXT $label=" . (preg_match('/>([a-z]+)<\\/text>/i', $html, $m) ? $m[1] : 'none') . "\\n";
}

// 1. no plan row: Free, so the mark is there
wm('free');

// 2. Pro: no mark
$GLOBALS['CI']->db->planRows = array(array('plan_type'=>'PRO','from_date'=>time()-3600,
  'to_date'=>time()+86400*20,'num_invoices'=>-1,'num_firms'=>1,'sponsored'=>0));
$GLOBALS['CI']->adominentitlements = new AdominEntitlements();
wm('pro');

// 3. Business: no mark either
$GLOBALS['CI']->db->planRows = array(array('plan_type'=>'BUSINESS','from_date'=>time()-3600,
  'to_date'=>time()+86400*200,'num_invoices'=>-1,'num_firms'=>-1,'sponsored'=>0));
$GLOBALS['CI']->adominentitlements = new AdominEntitlements();
wm('business');

// 4. the brand requirement never depends on the plan
echo "CREDIT_ALWAYS=" . (strlen(adomin_pdf_credit(TRUE)) > 0 && strlen(adomin_pdf_credit(FALSE)) > 0 ? 'yes' : 'no') . "\\n";
echo "CREDIT_TEXT=" . adomin_pdf_credit_text() . "\\n";
`);

await php.run('<?php require "/app/run.php";');
const val = (k) => { const m = out.match(new RegExp('OUT ' + k + '=(.*)')); return m ? m[1].trim() : ''; };
const get = (k) => { const m = out.match(new RegExp(k + '=([^\\n]*)')); return m ? m[1].trim() : ''; };

check('a Free account gets the watermark', /WM free=yes/.test(out), (out.match(/WM free=[^\n]*/)||[])[0]);
check('Pro does not get the watermark', /WM pro=no/.test(out), (out.match(/WM pro=[^\n]*/)||[])[0]);
check('Business does not get the watermark', /WM business=no/.test(out), (out.match(/WM business=[^\n]*/)||[])[0]);
check('the mark is faint enough to sit behind figures', parseFloat(get('WMCOLOR free')) > 0 && parseFloat(get('WMCOLOR free')) < 0.12, get('WMCOLOR free'));
check('the mark is the brand name, letter safe', /^[a-z]+$/i.test(get('WMTEXT free')), get('WMTEXT free'));
check('the "generated by" credit is present on every plan', get('CREDIT_ALWAYS') === 'yes', get('CREDIT_ALWAYS'));
check('the credit names anvoice and Adomin Technologies', /anvoice/.test(get('CREDIT_TEXT')) && /Adomin Technologies/.test(get('CREDIT_TEXT')), get('CREDIT_TEXT'));

const phpErr = /(Warning|Notice|Fatal error)[^\n]*/.exec(out);
check('no PHP warnings', !phpErr, phpErr ? phpErr[0] : '');

console.log('\n' + (fail ? fail + ' FAILED' : 'all pass') + '  (' + pass + ' passed)');
process.exit(fail ? 1 : 0);
