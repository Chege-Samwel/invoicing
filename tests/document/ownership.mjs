// Pro is 6,000 KES paid once. This harness runs the real config and library,
// then checks every surface that quotes a price, so a stray "per month" is
// caught in the file that still says it rather than by a reader.
import { PhpNode } from 'php-wasm/PhpNode.mjs';
import fs from 'node:fs';

// Harness paths. ROOT is the repository; HERE is this directory.
const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const HERE = new URL('./', import.meta.url).pathname.replace(/\/$/, '');
let pass=0, fail=0;
const check=(n,ok,d)=>{ if(ok){pass++;console.log('  ok   '+n);} else {fail++;console.log('  FAIL '+n+(d?' — '+d:''));} };
const R=p=>fs.readFileSync(ROOT + '/'+p,'utf8');

// ---- real PHP: load the config and the library, print the facts
const php=new PhpNode(); let out='';
php.addEventListener('output',e=>{out+=e.detail[0];});
await php.run('<?php @mkdir("/app/application/config", 0777, TRUE);');
await php.writeFile('/app/plans.php', fs.readFileSync(ROOT + '/application/config/adomin_plans.php'));
await php.writeFile('/app/probe.php', `<?php
if (!defined('BASEPATH')) define('BASEPATH', '/app/');
$CI = NULL;
// minimal stand-ins
function log_message($a,$b){}
function getenv_stub(){}
$config = array();
$CI = new stdClass();
$config = array();
include '/app/plans.php';
$P = $config['pricing'];
$rows = array();
foreach ($P['plans'] as $k => $plan) {
    $rows[] = $k . '|' . (is_array($plan['price']) ? json_encode($plan['price']) : 'NULL');
}
echo "PLANS:" . implode(";", $rows) . "\\n";
echo "TERM:" . json_encode($P['term']) . "\\n";
echo "TRIAL:" . json_encode($P['trial']) . "\\n";
`);
await php.run('<?php require "/app/probe.php";');
const proLine = out.split('\n').find(l=>l.startsWith('PLANS:'));
const termLine = out.split('\n').find(l=>l.startsWith('TERM:'));
// the config must actually parse, not merely look right in a regex
check('the config parses as PHP', proLine !== undefined, out.slice(0,200));
check('the parsed Pro price is 600000',
  proLine && /PRO\|\{"once":600000\}/.test(proLine), proLine);
check('the parsed term is 30 years of ownership',
  termLine && /"owner_years":30/.test(termLine), termLine);

// ---- config facts
const cfg = R('application/config/adomin_plans.php');
check('Pro costs 600000 minor units (6,000 KES)', /'once'\s*=>\s*600000/.test(cfg));
check('Free costs 0', /'once'\s*=>\s*0/.test(cfg));
check('Business has no price', /'BUSINESS'[\s\S]{0,600}?'price'\s*=>\s*NULL/.test(cfg));
check('no monthly or yearly price remains', !/'(monthly|yearly)'\s*=>/.test(cfg));
check('the one-off price is explained', /paid once|one payment/i.test(cfg));

// ---- library
const lib = R('application/libraries/AdominPricing.php');
check('quote() defaults to the single period', /function quote\(\$planKey, \$period = 'once'/.test(lib));
check('the period normaliser ignores its input', /function normalisePeriod\(\$period = NULL\)\s*\{\s*return 'once';/.test(lib.replace(/\n\s*/g,' ')));
check('priceList() reads the one price', /priceList\(\$period = 'once'/.test(lib));
check('no monthly-equivalent arithmetic survives', !/monthly_equivalent|free_months|saving_minor/.test(lib.replace(/\/\*[\s\S]*?\*\//g,'')));
check('term() exposes the ownership length', /function term\(\)/.test(lib));

// ---- the grant writes an ownership term, not a lease
const req = R('application/modules/admin/models/RequestsModel.php');
check('approval grants the ownership term', /\$term = \$this->adominpricing->term\(\)/.test(req));
check('approval no longer computes years from plan_period', !/in_array\(\$period, array\(1, 2, 3, 4\)/.test(req.replace(/\/\*[\s\S]*?\*\//g,'')));

// ---- surfaces
const pricing = R('application/views/home/pricing.php');
const pricingCode = pricing.replace(/<\?php\s*\/\*[\s\S]*?\*\/\s*\?>/g,'');
check('pricing page has no period switch links', !/switchPeriod/.test(pricingCode));
check('pricing page shows a one-off price',
  /once, <?= html_escape\(\$term\['label'\]\) \?>|<\?= html_escape\(\$term\['label'\]\) \?>/.test(pricing));
check('the price is not followed by /month or /year',
  !/<span class="pr-per">\/(month|year)</.test(pricing));
check('pricing page CTA does not imply a checkout', !/Choose <?= ?html_escape\(\$plan\['name'\]\)/.test(pricingCode));
check('pricing page says "request an activation"', /Request an activation/.test(pricing));
check('pricing page does not claim a card form', !/visa|mastercard|paypal/i.test(pricing));

const plansIdx = R('application/modules/users/views/plans/index.php');
check('in-panel PRO card shows the one-off price', /PRICE_FORMATTED/.test(plansIdx) && !/\/<\?= lang\('plans_year'\)/.test(plansIdx));
check('an owned plan does not show a renewal date', /plans_owned_no_end/.test(plansIdx));

const period = R('application/modules/users/views/plans/period.php');
check('the period picker is gone', !/name="period">\s*<\?php/.test(period) && !/<option value="24"/.test(period));
check('it still posts a period value for the NOT NULL column', /name="period" value="0"/.test(period));

const request = R('application/modules/users/views/plans/request.php');
check('the request page dropped the card-brand wall', !/visa-debit-card|master-card-icon|paypal-payment/.test(request));
check('the request page says a person replies', /plan_how_to_pay_text/.test(request));

const ctrl = R('application/modules/users/controllers/plans/PlansUsers.php');
check('amount due is the price, not price x years', /\$data\['mustPayAmount'\] = \(int\) \$data\['plans'\]/.test(ctrl));

// ---- language keys exist for every new string
const lang = R('application/language/english/users_lang.php');
for (const k of ['plans_once','plans_owned_no_end','plan_what_you_get','plan_how_to_pay','plan_how_to_pay_text','plan_amount_due','plan_req_ref','plan_request_what_next','plan_request_what_next_text']) {
  check('lang key '+k+' is defined', new RegExp("\\$lang\\['"+k+"'\\]").test(lang));
}

// ---- no view still quotes a monthly price
const monthlyViews = ['application/views/home/index.php','application/controllers/Home.php','application/views/registration/terms.php'];
for (const f of monthlyViews) {
  const src=R(f).replace(/\/\*[\s\S]*?\*\//g,'').replace(/<!--[\s\S]*?-->/g,'');
  check(f.split('/').pop()+' has no "a month" price', !/formatted'\]\s*\?> ?\/ ?month|\?> a month when/.test(src));
}

// ---- claims: a feature advertised as built must be built
const built = R('application/config/adomin_plans.php');
const builtBlock = built.slice(built.indexOf("'built' => array("), built.indexOf('/*\n     * How long a purchase lasts'));
const builtTrue = [...builtBlock.matchAll(/'(\w+)'\s*=>\s*(TRUE|FALSE)/g)].reduce((a,m)=>(a[m[1]]=m[2]==='TRUE',a),{});
check('the built map exists', Object.keys(builtTrue).length >= 12, JSON.stringify(builtTrue));
check('custom branding is marked built (it exists)', builtTrue.custom_branding === true);
check('reminders are marked unbuilt', builtTrue.reminders === false);
check('recurring is marked unbuilt', builtTrue.recurring === false);
check('e-invoicing is marked unbuilt', builtTrue.e_invoicing === false);
check('read receipts are marked unbuilt', builtTrue.read_receipts === false);
check('roles are marked unbuilt', builtTrue.roles === false);
check('the platform fee is marked unbuilt', builtTrue.platform_fee === false);

// the watermark is the one flag with a call site, so it must be marked built
check('watermark is genuinely enforced', (() => {
  const hits = [];
  const walk = d => fs.readdirSync(d,{withFileTypes:true}).forEach(e=>{
    const p=d+'/'+e.name;
    if(e.isDirectory()) walk(p);
    else if(e.name.endsWith('.php') && !p.includes('config/adomin_plans'))
      if(/['"]watermark['"]/.test(fs.readFileSync(p,'utf8'))) hits.push(p);
  });
  walk(ROOT + '/application');
  return hits.length >= 1;
})());

// ---- the page uses the map rather than a hardcoded list
const page = R('application/views/home/pricing.php');
check('the page asks the library what is built', /\$pricing->unbuiltRows\(\)/.test(page));
check('the badge is rendered on rows', /class="pr-soon"/.test(page));
check('the badge is styled', /\.pr-soon\s*\{/.test(R('assets/public/css/adomin-pricing.css')));

// ---- no feature text promises a gateway that does not exist
// "no platform fee" is a true statement; "2% platform fee" would be a claim
check('no feature row advertises a platform fee',
  !/\d+(\.\d+)?%\s*platform fee|platform fee[^.,]*\d+%/.test(
    built.slice(built.indexOf("'plans' => array(")).replace(/\/\*[\s\S]*?\*\//g,'')));
check('the payment row describes what exists', /Record payments by hand/.test(built));
check('there is no payment gateway in the codebase',
  !/paynow|mpesa|stripe|paypal/i.test(R('application/modules/users/controllers/plans/PlansUsers.php')));

// ---- the tax default is Kenyan, not Bulgarian
check('the VAT default is 16', /'vat_default'\s*=>\s*adomin_env\('ADOMIN_VAT_DEFAULT', '16'\)/.test(R('application/config/adomin.php')));
for (const f of ['invoices/newinvoice.php','protocols/addprotocol.php','store/addmovement.php']) {
  check('no hardcoded 20% in '+f.split('/')[1],
    !/vat_percent"\]\s*:\s*'20'/.test(R('application/modules/users/views/'+f)));
}

console.log('\n'+(fail?fail+' FAILED':'all pass')+'  ('+pass+' passed)');
process.exit(fail?1:0);
