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
                 'application/helpers/adomin_helper.php']) {
  await php.writeFile('/app/' + f, fs.readFileSync(R + '/' + f));
}

await php.writeFile('/app/run.php', `<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
define('BASEPATH',1); define('APPPATH','/app/application/'); define('FCPATH','/app/');
function base_url($p=''){ return 'https://x.test/'.ltrim((string)$p,'/'); }
function lang($k){ return $k; }
function html_escape($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function adomin_tz_abbr(){ return 'EAT'; }
function log_message($l,$m){ echo "LOG[$l] $m\\\\n"; }
class CfgStub {
  public $config = array();
  function load($f,$section=TRUE,$return=FALSE){ require APPPATH.'config/'.$f.'.php'; $this->config[$f] = $config; return TRUE; }
  function item($k,$section=NULL){ return isset($this->config[$section][$k]) ? $this->config[$section][$k] : NULL; }
}
class SessionStub { private $d=array(); function userdata($k){ return isset($this->d[$k])?$this->d[$k]:NULL; } function set_userdata($k,$v){ $this->d[$k]=$v; } }
class InputStub { function cookie($k){ return isset($_COOKIE[$k])?$_COOKIE[$k]:NULL; } }
class CILoad { function helper($n){} function library($n){ $GLOBALS['CI']->$n = new $n(); } }
class ResStub { private $rows; function __construct($rows){ $this->rows=$rows; } function row_array(){ return $this->rows ? $this->rows[0] : NULL; } }

// a database that answers the three queries entitlements makes
class DbStub {
  public $planRows = array(); public $invoicesThisMonth = 0; public $clients = 0;
  public $employees = 0; public $firms = 0; public $log = array();
  function query($sql, $binds = array()) {
    $this->log[] = $sql;
    if (strpos($sql, 'FROM firms_plans') !== FALSE) {
      return new ResStub($this->planRows);
    }
    if (strpos($sql, 'FROM invoices') !== FALSE) {
      return new ResStub(array(array('num' => $this->invoicesThisMonth)));
    }
    if (strpos($sql, 'FROM clients') !== FALSE) {
      return new ResStub(array(array('num' => $this->clients)));
    }
    if (strpos($sql, 'FROM employees') !== FALSE) {
      return new ResStub(array(array('num' => $this->employees)));
    }
    if (strpos($sql, 'FROM firms_users') !== FALSE) {
      return new ResStub(array(array('num' => $this->firms)));
    }
    return new ResStub(array());
  }
}
class CIStub { public $config; public $session; public $input; public $load; public $db;
  function __construct(){ $this->config=new CfgStub(); $this->session=new SessionStub(); $this->input=new InputStub(); $this->db=new DbStub();
    $this->load=new CILoad(); } }
require '/app/application/config/adomin.php';
$GLOBALS['CFG']=$config;
function adomin_conf($g,$k=NULL){ $c=isset($GLOBALS['CFG'][$g])?$GLOBALS['CFG'][$g]:array(); if($k===NULL) return $c; return isset($c[$k])?$c[$k]:NULL; }
require '/app/application/helpers/adomin_helper.php';
$GLOBALS['CI'] = new CIStub();
function &get_instance(){ return $GLOBALS['CI']; }
require '/app/application/libraries/AdominPricing.php';
$GLOBALS['CI']->adominpricing = new AdominPricing();
require '/app/application/libraries/AdominEntitlements.php';
define('USER_ID', 7);

file_put_contents(sys_get_temp_dir().'/adomin-rates.json', json_encode(array('fetched'=>time(),'rates'=>array(
 'USD'=>1.0,'KES'=>129.00,'EUR'=>0.92,'GBP'=>0.79))));

function say($l,$v){ echo "OUT $l=" . $v . "\n"; }

// --- 1. no plan row at all: the account is Free
$e = new AdominEntitlements();
say('noplan_key', $e->planKey());
say('noplan_source', $e->plan()['source']);
say('noplan_inv_limit', var_export($e->limit('invoices_per_month'), TRUE));
say('noplan_client_limit', var_export($e->limit('clients'), TRUE));
say('noplan_can_invoice', $e->canCreateInvoice() ? 'yes' : 'no');

// --- 2. Free account with 4 of 5 invoices used
$e->reset();
$GLOBALS['CI']->db->invoicesThisMonth = 4;
say('free4_used', $e->used('invoices_per_month'));
say('free4_remaining', var_export($e->remaining('invoices_per_month'), TRUE));
say('free4_can', $e->canCreateInvoice() ? 'yes' : 'no');

// --- 3. Free account at the cap
$e->reset();
$GLOBALS['CI']->db->invoicesThisMonth = 5;
say('free5_can', $e->canCreateInvoice() ? 'yes' : 'no');
say('free5_remaining', var_export($e->remaining('invoices_per_month'), TRUE));
say('free5_message', $e->limitMessage('invoices_per_month'));
// the message must say the real number and point somewhere that exists
say('free5_message_has_number', strpos($e->limitMessage('invoices_per_month'), '5') !== FALSE ? 'yes' : 'no');
say('free5_message_offers_upgrade', strpos($e->limitMessage('invoices_per_month'), 'Upgrade') !== FALSE ? 'yes' : 'no');


// --- 4. Free account over the cap (downgraded mid-month)
$e->reset();
$GLOBALS['CI']->db->invoicesThisMonth = 9;
say('free9_can', $e->canCreateInvoice() ? 'yes' : 'no');
say('free9_remaining', var_export($e->remaining('invoices_per_month'), TRUE));

// --- 5. a live PRO row: unlimited invoices, 2 users, 1 firm
$e->reset();
$GLOBALS['CI']->db->planRows = array(array('plan_type'=>'PRO','from_date'=>time()-86400,'to_date'=>time()+86400*30,
  'num_invoices'=>-1,'num_firms'=>1,'sponsored'=>0));
$GLOBALS['CI']->db->invoicesThisMonth = 5000;
say('pro_key', $e->planKey());
say('pro_inv_limit', var_export($e->limit('invoices_per_month'), TRUE));
say('pro_can_invoice', $e->canCreateInvoice() ? 'yes' : 'no');
say('pro_remaining', var_export($e->remaining('invoices_per_month'), TRUE));
say('pro_users_limit', var_export($e->limit('users'), TRUE));
say('pro_can_user', $e->canCreateUser() ? 'yes' : 'no');
say('pro_watermark', var_export($e->flag('watermark'), TRUE));
say('pro_recurring', var_export($e->flag('recurring'), TRUE));
say('pro_planunits_inv', $e->planUnits()['num_invoices']);
// an unlimited plan has no wall, so the message must not invent one, and it
// must never tell a Pro account to upgrade to Pro
say('pro_message_unlimited', $e->limitMessage('invoices_per_month'));
// Pro really does cap business profiles at one, so this one is a genuine wall
say('pro_firms_used_up', $e->limit('firms') . '/' . $e->used('firms'));
say('pro_firms_message', $e->limitMessage('firms'));
say('pro_unlimited', var_export($e->planUnits()['unlimited_invoices'], TRUE));

// --- 6. legacy plan names in the DB
$e->reset();
$GLOBALS['CI']->db->planRows = array(array('plan_type'=>'ADVANCED','from_date'=>time()-86400,'to_date'=>time()+86400,
  'num_invoices'=>-1,'num_firms'=>1,'sponsored'=>0));
say('legacy_advanced', $e->planKey());

$e->reset();
$GLOBALS['CI']->db->planRows = array(array('plan_type'=>'BASIC','from_date'=>time()-86400,'to_date'=>time()+86400,
  'num_invoices'=>20,'num_firms'=>1,'sponsored'=>0));
say('legacy_basic', $e->planKey());
say('legacy_basic_can', $e->canCreateInvoice() ? 'yes' : 'no');

// --- 7. an unknown plan type must not grant anything
$e->reset();
$GLOBALS['CI']->db->planRows = array(array('plan_type'=>'GOLDMINE','from_date'=>time()-86400,'to_date'=>time()+86400,
  'num_invoices'=>-1,'num_firms'=>-1,'sponsored'=>0));
say('unknown_key', $e->planKey());
say('unknown_source', $e->plan()['source']);
say('unknown_inv_limit', var_export($e->limit('invoices_per_month'), TRUE));

// --- 8. an expired row is not a plan
$e->reset();
$GLOBALS['CI']->db->planRows = array();   // the query filters expired rows out
say('expired_key', $e->planKey());

// --- 9. client cap
$e->reset();
$GLOBALS['CI']->db->clients = 4;
say('clients4_can', $e->canCreateClient() ? 'yes' : 'no');
$e->reset();
$GLOBALS['CI']->db->clients = 5;
say('clients5_can', $e->canCreateClient() ? 'yes' : 'no');

// --- 10. users include the owner
$e->reset();
$GLOBALS['CI']->db->employees = 0;
say('users_owner_only', $e->used('users'));
$e->reset();
$GLOBALS['CI']->db->employees = 1;
say('users_with_employee', $e->used('users'));
say('free_can_employee', $e->canCreateUser() ? 'yes' : 'no');

// --- 11. BUSINESS is all unlimited
$e->reset();
$GLOBALS['CI']->db->planRows = array(array('plan_type'=>'BUSINESS','from_date'=>time()-86400,'to_date'=>time()+86400*365,
  'num_invoices'=>-1,'num_firms'=>-1,'sponsored'=>0));
$GLOBALS['CI']->db->firms = 40;
say('biz_firm_limit', var_export($e->limit('firms'), TRUE));
say('biz_can_firm', $e->canCreateFirm() ? 'yes' : 'no');

// --- 12. caching: the plan query runs once per request
$e->reset();
$GLOBALS['CI']->db->log = array();
$e->planKey(); $e->planKey(); $e->planKey();
$planQueries = 0;
foreach ($GLOBALS['CI']->db->log as $q) { if (strpos($q, 'firms_plans') !== FALSE) $planQueries++; }
say('plan_query_count', $planQueries);
`);

await php.run('<?php require "/app/run.php";');
const val = (k) => { const m = out.match(new RegExp('OUT ' + k + '=(.*)')); return m ? m[1].trim() : ''; };

check('an account with no plan row is Free', val('noplan_key') === 'FREE', val('noplan_key'));
check('...and says so, not "error"', val('noplan_source') === 'free', val('noplan_source'));
check('Free invoices limit is 5', val('noplan_inv_limit') === '5', val('noplan_inv_limit'));
check('Free client limit is 5', val('noplan_client_limit') === '5', val('noplan_client_limit'));
check('...so a new account can invoice', val('noplan_can_invoice') === 'yes', val('noplan_can_invoice'));

check('a Free account with 4 used has 1 left', val('free4_remaining') === '1', val('free4_remaining'));
check('...and may create another', val('free4_can') === 'yes', val('free4_can'));
check('at the cap it may not invoice', val('free5_can') === 'no', val('free5_can'));
check('at the cap, remaining is 0 not -1', val('free5_remaining') === '0', val('free5_remaining'));
check('the cap message names the plan and the fix', /all 5 invoices this month/.test(val('free5_message')) && /Upgrade to Pro/.test(val('free5_message')), val('free5_message'));
check('an over-cap account cannot invoice', val('free9_can') === 'no', val('free9_can'));
check('an over-cap account shows 0 remaining, never negative', val('free9_remaining') === '0', val('free9_remaining'));

check('a live Pro row is Pro', val('pro_key') === 'PRO', val('pro_key'));
check('Pro invoices are unlimited', val('pro_inv_limit') === 'NULL', val('pro_inv_limit'));
check('Pro can invoice after 5,000 this month', val('pro_can_invoice') === 'yes', val('pro_can_invoice'));
check('Pro remaining is NULL, not a number', val('pro_remaining') === 'NULL', val('pro_remaining'));
check('Pro allows 2 users', val('pro_users_limit') === '2', val('pro_users_limit'));
check('Pro watermark is off', val('pro_watermark') === 'false', val('pro_watermark'));
check('Pro has recurring', val('pro_recurring') === 'true', val('pro_recurring'));
check('Pro planUnits reports unlimited', val('pro_unlimited') === 'true', val('pro_unlimited'));
check('Pro planUnits does not say -1', val('pro_planunits_inv') !== '-1', val('pro_planunits_inv'));
check('the cap message carries the number', val('free5_message_has_number') === 'yes', val('free5_message'));
check('the cap message offers the upgrade', val('free5_message_offers_upgrade') === 'yes', val('free5_message'));
check('an unlimited plan is not told it ran out', /no limit on invoices this month/.test(val('pro_message_unlimited')), val('pro_message_unlimited'));
check('a Pro account is never told to upgrade to Pro', !/Upgrade to Pro/.test(val('pro_firms_message')), val('pro_firms_message'));
check('a real Pro cap still names the plan and the thing', /1 business profiles on the Pro plan/.test(val('pro_firms_message')), val('pro_firms_message'));

check('the legacy ADVANCED name is Pro', val('legacy_advanced') === 'PRO', val('legacy_advanced'));
check('the legacy BASIC name is Free', val('legacy_basic') === 'FREE', val('legacy_basic'));
check('a legacy BASIC row cannot exceed 5 a month', val('legacy_basic_can') === 'no', val('legacy_basic_can'));

check('an unknown plan type falls back to Free', val('unknown_key') === 'FREE', val('unknown_key'));
check('...and is logged as unknown', val('unknown_source') === 'unknown', val('unknown_source'));
check('an unknown plan grants no overrides', val('unknown_inv_limit') === '5', val('unknown_inv_limit'));

check('an expired row is not a plan', val('expired_key') === 'FREE', val('expired_key'));

check('4 clients is under the cap', val('clients4_can') === 'yes', val('clients4_can'));
check('the 5th client fills the cap', val('clients5_can') === 'no', val('clients5_can'));

check('the owner counts as a user', val('users_owner_only') === '1', val('users_owner_only'));
check('an employee counts towards users', val('users_with_employee') === '2', val('users_with_employee'));
check('a Free account cannot add an employee', val('free_can_employee') === 'no', val('free_can_employee'));

check('Business firms are unlimited', val('biz_firm_limit') === 'NULL', val('biz_firm_limit'));
check('Business can add a 41st business', val('biz_can_firm') === 'yes', val('biz_can_firm'));

check('the plan query runs once per request', val('plan_query_count') === '1', val('plan_query_count'));

const phpErr = /(Warning|Notice|Fatal error)[^\n]*/.exec(out);
check('no PHP warnings', !phpErr, phpErr ? phpErr[0] : '');

console.log('\n' + (fail ? fail + ' FAILED' : 'all pass') + '  (' + pass + ' passed)');
process.exit(fail ? 1 : 0);
