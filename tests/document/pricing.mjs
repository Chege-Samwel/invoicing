import { PhpNode } from 'php-wasm/PhpNode.mjs';
import fs from 'node:fs';

// Harness paths. ROOT is the repository; HERE is this directory.
const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const HERE = new URL('./', import.meta.url).pathname.replace(/\/$/, '');

const php = new PhpNode(); let out = '';
php.addEventListener('output', e => { out += e.detail[0]; });

let pass = 0, fail = 0;
const check = (name, ok, detail) => {
  if (ok) { pass++; console.log('  ok   ' + name); }
  else { fail++; console.log('  FAIL ' + name + (detail ? ' — ' + detail : '')); }
};

for (const d of ['/app/application/config', '/app/application/libraries', '/app/application/helpers', '/app/application/core']) {
  await php.run('<?php @mkdir("' + d + '",0777,TRUE);');
}
await php.writeFile('/app/application/config/adomin_plans.php', fs.readFileSync(ROOT + '/application/config/adomin_plans.php'));
await php.writeFile('/app/application/libraries/AdominPricing.php', fs.readFileSync(ROOT + '/application/libraries/AdominPricing.php'));
await php.writeFile('/app/application/helpers/adomin_helper.php', fs.readFileSync(ROOT + '/application/helpers/adomin_helper.php'));
await php.writeFile('/app/application/config/adomin.php', fs.readFileSync(ROOT + '/application/config/adomin.php'));

await php.writeFile('/app/run.php', `<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
define('BASEPATH',1); define('APPPATH','/app/application/'); define('FCPATH','/app/');
function base_url($p=''){ return 'https://x.test/'.ltrim((string)$p,'/'); }
function lang($k){ return $k; }
function html_escape($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function adomin_tz_abbr(){ return 'EAT'; }
function log_message($l,$m){ echo "LOG[$l] $m\\n"; }
class CfgStub { public $config = array(); }
class SessionStub { private $d=array(); function userdata($k){ return isset($this->d[$k])?$this->d[$k]:NULL; } function set_userdata($k,$v){ $this->d[$k]=$v; } }
class InputStub { function cookie($k){ return isset($_COOKIE[$k])?$_COOKIE[$k]:NULL; } }
class CILoad { function helper($n){} }
class CIStub { public $config; public $session; public $input; public $load;
  function __construct(){ $this->config=new CfgStub(); $this->session=new SessionStub(); $this->input=new InputStub(); $this->load=new CILoad(); } }
require '/app/application/config/adomin.php';
$GLOBALS['CFG']=$config;
function adomin_conf($g,$k=NULL){ $c=isset($GLOBALS['CFG'][$g])?$GLOBALS['CFG'][$g]:array(); if($k===NULL) return $c; return isset($c[$k])?$c[$k]:NULL; }
require '/app/application/helpers/adomin_helper.php';
$GLOBALS['CI'] = new CIStub();
function &get_instance(){ return $GLOBALS['CI']; }
require APPPATH.'config/adomin_plans.php';
$GLOBALS['CI']->config->config['pricing'] = $config['pricing'];
require '/app/application/libraries/AdominPricing.php';

function say($label, $value){ echo "OUT $label=" . $value . "\\n"; }

$p = new AdominPricing();

// never touch the network in a test
$rates = array('USD'=>1.0,'KES'=>129.00,'EUR'=>0.92,'GBP'=>0.79,'NGP'=>0.0);
say('plans', implode(',', array_keys($p->plans())));
say('pro_once_kes', $p->format($p->quote('PRO','once','KES')['amount_minor'], 'KES'));
say('pro_once_minor', $p->quote('PRO','once','KES')['amount_minor']);
say('free_once', $p->quote('FREE','once','KES')['amount_minor']);
say('period_is_once', $p->quote('PRO','monthly','KES')['period'] . '/' . $p->normalisePeriod('yearly'));
say('pro_once_usd', $p->format($p->quote('PRO','once','USD')['amount_minor'], 'USD'));
say('pro_once_eur', $p->format($p->quote('PRO','once','EUR')['amount_minor'], 'EUR'));
say('pro_once_gbp', $p->format($p->quote('PRO','once','GBP')['amount_minor'], 'GBP'));
say('pro_once_inr', $p->format($p->quote('PRO','once','INR')['amount_minor'], 'INR'));
say('unknown_currency_falls_back', $p->quote('PRO','once','XYZ')['currency']);
say('business_sellable', $p->quote('BUSINESS','once','KES')['sellable'] ? 'yes' : 'no');
say('legacy_basic', $p->plan('BASIC')['key']);
say('legacy_advanced', $p->plan('ADVANCED')['key']);
say('case_insensitive', $p->plan('pro')['key']);
say('trial_days', $p->trial()['days']);
say('trial_plan', $p->trial()['plan_key']);
say('term_kind', $p->term()['kind']);
say('term_years', $p->term()['owner_years']);
say('term_label', $p->term()['label']);
$list = $p->priceList('once', 'KES');
say('list_has_no_saving', isset($list['PRO']['saving_minor']) ? 'yes' : 'no');
say('list_price', $list['PRO']['formatted']);

// geography
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'KE';
say('cc_ke', $p->countryForVisitor());
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'XX';
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-GB,en;q=0.9';
say('lang_gb', $p->countryForVisitor());
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'sw';
say('lang_sw', $p->countryForVisitor());
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = '';
$_SERVER['HTTP_X_TIMEZONE'] = 'Africa/Nairobi';
say('tz_ke', $p->countryForVisitor());
$_SERVER['HTTP_X_TIMEZONE'] = '';
say('none', $p->countryForVisitor() === NULL ? 'null' : $p->countryForVisitor());

$drift = array();
foreach (array('KES','USD','EUR','GBP','ZAR','NGN','INR','AED','UGX','TZS','CAD','AUD','JPY','CHF','SEK','BRL','MXN','PLN','NGN','INR','UGX','JPY') as $c) {
    $q = $p->quote('PRO','once',$c);
    echo "OUTDUMP $c|" . $q['formatted'] . "|\n";
    $drift[$c] = array(
        'once' => array('exact' => $q['exact_minor'], 'quoted' => $q['amount_minor']),
    );
}

// show every quote against its exact conversion, so drift is a number not a guess
foreach (array('KES','USD','EUR','GBP','ZAR','NGN','INR','AED','UGX','TZS','CAD','AUD','JPY','CHF','SEK','BRL','MXN','PLN') as $c) {
    foreach (array('once') as $per) {
        $q = $p->quote('PRO', $per, $c);
        if (!$q['exact_minor']) continue;
        $d = abs($q['amount_minor'] - $q['exact_minor']) / $q['exact_minor'] * 100;
        printf("DRIFT %-4s %-8s %10s -> %10s  %5.1f%%\n", $c, $per, $q['exact_minor'], $q['amount_minor'], $d);
    }
}

echo "OUTRATES " . json_encode($drift) . "\n";
`);
await php.run('<?php require "/app/run.php";');

if (process.env.DUMPOUT) console.log('---PHP OUT---\n' + out + '\n---END---');
const val = (k) => { const m = out.match(new RegExp('OUT ' + k + '=(.*)')); return m ? m[1].trim() : ''; };

check('three plans in display order', val('plans') === 'FREE,PRO,BUSINESS', val('plans'));
check('free is 0 in every currency', val('free_once') === '0', val('free_once'));

// ---- the price
check('Pro is 6,000 KES, paid once', val('pro_once_kes') === 'KSh 6,000', val('pro_once_kes'));
check('the raw figure is 600000 minor units', val('pro_once_minor') === '600000', val('pro_once_minor'));
check('every period resolves to the one price', val('period_is_once') === 'once/once', val('period_is_once'));
check('converts to a recognisable dollar price', /^\$ \d+(\.99|\.50|\.25|\.00|\.75)$/.test(val('pro_once_usd')), val('pro_once_usd'));
check('euro uses the euro sign', /^€ \d+(\.99|\.50|\.25|\.00|\.75)$/.test(val('pro_once_eur')), val('pro_once_eur'));
check('pound uses the pound sign', /^£ \d+(\.99|\.50|\.25|\.00|\.75)$/.test(val('pro_once_gbp')), val('pro_once_gbp'));
check('rupee uses the rupee sign', /^₹ /.test(val('pro_once_inr')), val('pro_once_inr'));
check('an unknown currency is never quoted as if it were valid', val('unknown_currency_falls_back') === 'KES', val('unknown_currency_falls_back'));
check('Business still has no online price', val('business_sellable') === 'no', val('business_sellable'));
check('the old BASIC key maps to Free', val('legacy_basic') === 'FREE', val('legacy_basic'));
check('the old ADVANCED key maps to Pro', val('legacy_advanced') === 'PRO', val('legacy_advanced'));
check('plan lookup is case-insensitive', val('case_insensitive') === 'PRO', val('case_insensitive'));
check('the trial is 30 days', val('trial_days') === '30', val('trial_days'));
check('the trial grants Pro', val('trial_plan') === 'PRO', val('trial_plan'));

// ---- ownership
check('the term is an ownership', val('term_kind') === 'owner', val('term_kind'));
check('the ownership term is 30 years', val('term_years') === '30', val('term_years'));
check('the term is described as "for life"', val('term_label') === 'for life', val('term_label'));
check('a price list carries no subscription saving', val('list_has_no_saving') === 'no', val('list_has_no_saving'));
check('the price list quotes the one price', val('list_price') === 'KSh 6,000', val('list_price'));

// ---- geography (unchanged by the pricing model, still worth guarding)
check('a Cloudflare country header is honoured', val('cc_ke') === 'KE', val('cc_ke'));
check('a language region is honoured', val('lang_gb') === 'GB', val('lang_gb'));
check('a bare language maps to its main market', val('lang_sw') === 'KE', val('lang_sw'));
check('a timezone is the last resort', val('tz_ke') === 'KE', val('tz_ke'));
check('with no signal at all it says so', val('none') === 'null', val('none'));

for (const line of (out.match(/OUTDUMP [^\n]*/g) || [])) console.log('  ' + line.replace('OUTDUMP ', ''));
check('no quote drifts more than 15% from the exact conversion', (() => {
  const m = out.match(/OUTRATES ([^\n]+)/);
  const rates = m ? JSON.parse(m[1]) : {};
  let worst = 0, worstCur = '';
  for (const [cur, vals] of Object.entries(rates)) {
    for (const [kind, r] of Object.entries(vals)) {
      if (!r.exact || r.quoted == null) continue;
      const drift = Math.abs(r.quoted - r.exact) / r.exact;
      if (drift > worst) { worst = drift; worstCur = cur + '/' + kind; }
    }
  }
  return { ok: worst <= 0.1500001, msg: 'worst drift ' + (worst * 100).toFixed(1) + '% at ' + worstCur + ' ($' + (worst*0+1) + ')' };
})());

const logs = out.match(/LOG\[error\][^\n]*/g) || [];
check('no PHP notices or warnings', !/Warning|Notice|Fatal/.test(out), (out.match(/(Warning|Notice|Fatal)[^\n]*/g) || []).join(' | '));

console.log('\n' + (fail ? fail + ' FAILED' : 'all pass') + '  (' + pass + ' passed)');
process.exit(fail ? 1 : 0);
