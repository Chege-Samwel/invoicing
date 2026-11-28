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

for (const d of ['/app/application/config', '/app/application/libraries', '/app/application/helpers', '/app/application/views/home', '/app/application/core']) {
  await php.run('<?php @mkdir("' + d + '",0777,TRUE);');
}
for (const f of ['application/config/adomin_plans.php', 'application/config/adomin.php',
                 'application/libraries/AdominPricing.php', 'application/helpers/adomin_helper.php',
                 'application/views/home/pricing.php']) {
  await php.writeFile('/app/' + f, fs.readFileSync(R + '/' + f));
}

await php.writeFile('/app/run.php', `<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
define('BASEPATH',1); define('APPPATH','/app/application/'); define('FCPATH','/app/');
function base_url($p=''){ return 'https://x.test/'.ltrim((string)$p,'/'); }
function lang($k){ return $k; }
function lang_url($p=''){ return base_url($p); }
function html_escape($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function adomin_tz_abbr(){ return 'EAT'; }
function log_message($l,$m){}
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

// offline rates
$rp = sys_get_temp_dir() . '/adomin-rates.json';
@unlink($rp);

function fakeRates() {
  $rp = sys_get_temp_dir() . '/adomin-rates.json';
  file_put_contents($rp, json_encode(array('fetched' => time(), 'rates' => array(
    'USD'=>1.0,'KES'=>129.00,'EUR'=>0.92,'GBP'=>0.79,'ZAR'=>18.20,'NGN'=>1550.0,
    'INR'=>84.0,'AED'=>3.67,'UGX'=>3750.0,'TZS'=>2650.0,'CAD'=>1.39,'AUD'=>1.53,
    'JPY'=>152.0,'CHF'=>0.88,'SEK'=>10.9,'BRL'=>5.8,'MXN'=>20.2,'PLN'=>4.0,
  ))));
}

// --- render, Kenyan visitor, both periods
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'KE';
fakeRates();
$p = new AdominPricing();
$pricing = $p;
$geography = $pricing->countryNames();
$countryName = isset($geography[$p->countryForVisitor()]) ? $geography[$p->countryForVisitor()] : 'your region';
$currencyGuessed = !$p->hasExplicitCurrency();
$ratesStale = $p->ratesAreStale();
$trial = $pricing->trial();
$term = $pricing->term();
$currencyAction = base_url('pricing');
$csrfName = 'csrf_test'; $csrfHash = 'abc123';
$registerUrl = base_url('registration'); $contactUrl = base_url('contacts');
$buyUrlBase = base_url('user/plan/');
$currencies = $pricing->supportedCurrencies();
$period = 'once';

$currency = $p->currencyForVisitor();
$quotes = $pricing->priceList('once', $currency);
ob_start();
require '/app/application/views/home/pricing.php';
$html = ob_get_clean();
echo "RENDERED once " . strlen($html) . "\n";
echo "PRICINGHTML_START_ONCE\n";
foreach (explode("\n", $html) as $n => $line) { echo "H" . $n . ":" . $line . "\n"; }
echo "PRICINGHTML_END\n";
echo "CUR " . $currency . "\n";

// --- a UK visitor, nothing chosen: the default must be GBP, not KES
unset($_SERVER['HTTP_CF_IPCOUNTRY']);
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'GB';
$p2 = new AdominPricing();
$cur2 = $p2->currencyForVisitor();
$q2 = $p2->priceList('once', $cur2);
echo "GB cur= " . $cur2 . " once= " . $q2['PRO']['formatted']
   .  " free= " . $q2['FREE']['formatted'] . "\n";
`);
await php.run('<?php require "/app/run.php";');
const grab = (period) => {
  const start = 'PRICINGHTML_START_' + period.toUpperCase();
  return (out.split(start).pop() || '')
    .split('PRICINGHTML_END')[0]
    .split('\n')
    .filter(l => /^H\d+:/.test(l))
    .map(l => l.replace(/^H\d+:/, ''))
    .join('\n');
};
const txt = grab('ONCE');

const phpErr = /(Warning|Notice|Fatal error)[^\n]*/.exec(out);
check('the page rendered without PHP errors', !phpErr, phpErr ? phpErr[0] : '');
check('quoted in KES for a Kenyan visitor', /CUR KES/.test(out), (out.match(/CUR (.*)/)||[])[1] || out.slice(0,300));
check('three plan cards', (txt.match(/class="pr-card[ "]/g) || []).length === 3, String((txt.match(/class="pr-card[ "]/g)||[]).length));

// ---- the price
check('Free is 0', txt.includes('KSh 0'), '');
check('Pro is 6,000', txt.includes('KSh 6,000'), '');
check('the price says it is paid once', /once, for life/.test(txt), (txt.match(/pr-per">[^<]*/)||[])[0]);
// "invoices/month" is a real limit, not a billing period: the free plan is
// capped per calendar month. Only a price suffix would be a subscription.
check('no price is suffixed with a period', !/pr-per">\/(month|year)|KSh [\d,]+<\/span>\s*<span class="pr-per">\//.test(txt),
  (txt.match(/pr-per">[^<]*/)||[])[0] || '');
check('the page does not offer a billing period', !/aria-pressed/.test(txt), 'still has a period switch');
check('the page states there is no renewal', /No renewal/.test(txt), '');
check('no subscription saving is claimed', !/cheaper than paying monthly|months free/.test(txt), (txt.match(/[^>]*months free[^<]*/)||[])[0] || '');

// ---- the offer, honestly labelled
check('Business is coming soon, not "let us talk"', /Coming soon/.test(txt), '');
check('the Pro CTA says what the link does', /Create an account - 30 days of Pro included/.test(txt), '');
check('the Pro CTA does not imply a checkout', !/Choose Pro|Buy now|Pay now/.test(txt), '');
check('no card-brand logos are shown', !/visa|mastercard|paypal/i.test(txt), '');
check('the trial is advertised', /30 days|Try it free for 30/.test(txt), '');
check('the page says you pay once, in the small print', /You pay once/.test(txt), '');

// ---- unchanged structure
// the row label may be followed by a "coming soon" badge inside the same th
check('matrix has all seven rows',
  ['Volume','Branding','Automation','Team','Payments','Tax','Reporting'].every(r => new RegExp('>\\s*' + r + '\\b').test(txt)),
  ['Volume','Branding','Automation','Team','Payments','Tax','Reporting'].filter(r => !new RegExp('>\\s*' + r + '\\b').test(txt)).join(','));
check('unbuilt rows are marked on the matrix', (txt.match(/class="pr-soon"/g) || []).length >= 5,
  String((txt.match(/class="pr-soon"/g)||[]).length) + ' badges');
check('the fee-bearing note is gone', !/carry a platform fee/.test(txt), '');
check('currency picker lists currencies', /pr-currency-select/.test(txt) && txt.includes('KES'), '');
check('CSRF token is in the currency form', txt.includes('csrf_test'), '');
check('the guess is disclosed', /guessed from your location/.test(txt), '');
check('KES visitor in <select> is marked selected', /value="KES" selected/.test(txt), '');
check('no double-escaped entities', !/&amp;#|&amp;quot;/.test(txt), (txt.match(/&amp;#\d+[^<]*/)||[])[0] || '');
check('no inline <style> leaking', !/<style/.test(txt), '');
check('free CTA goes to registration', /href="https:\/\/x.test\/registration"[^>]*>Start free/.test(txt), '');
// an anonymous visitor is not sent into the app; registration is where the
// trial starts and where a request gets an account to attach to
check('pro CTA sends an anonymous visitor to registration',
  /Create an account - 30 days of Pro/.test(txt), (txt.match(/<a class="btn btn-primary btn-block"[\s\S]{0,120}/)||[])[0] || '');
check('pro CTA carries no period parameter', !/user\/plan\/pro\?period=/.test(txt), (txt.match(/user\/plan\/pro\?[^"]*/)||[])[0] || '');
check('Business CTA asks to be told when it lands', /Ask to be told when it lands/.test(txt), '');

// ---- the matrix still describes the plans
check('Pro reads Unlimited invoices', /Unlimited invoices/.test(txt), '');
check('Free reads 5 clients', /5 clients/.test(txt), '');

// ---- another currency
const gb = (out.match(/GB cur= [^\n]*/)||[''])[0];
check('a UK visitor is quoted in pounds', /GB cur= GBP/.test(gb), gb);
check('the pound figure is a recognisable price', /once= £ \d+(\.99|\.50|\.25|\.00|\.75)/.test(gb), gb);
check('GB free is still zero', /free= £ 0/.test(gb), gb);
check('no pound saving is invented', !/saving/.test(gb), gb);

console.log('\\n' + (fail ? fail + ' FAILED' : 'all pass') + '  (' + pass + ' passed)');
process.exit(fail ? 1 : 0);
