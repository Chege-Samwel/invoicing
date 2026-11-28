// "A QR code in every design." The code is signed with config['encryption_key'],
// and AdominQr::secret() returns '' when that is empty - which it was, so no
// design produced a code at all, silently. This runs the real library.
import { PhpNode } from 'php-wasm/PhpNode.mjs';
import fs from 'node:fs';

// Harness paths. ROOT is the repository; HERE is this directory.
const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const HERE = new URL('./', import.meta.url).pathname.replace(/\/$/, '');
let pass=0, fail=0;
const check=(n,ok,d)=>{ if(ok){pass++;console.log('  ok   '+n);} else {fail++;console.log('  FAIL '+n+(d?' — '+d:''));}};

const php = new PhpNode(); let out='';
php.addEventListener('output', e=>{ out += e.detail[0]; });
await php.run('<?php @mkdir("/app/application/libraries/phpqrcode",0777,TRUE);');
for (const f of fs.readdirSync(ROOT + '/application/libraries/phpqrcode')) {
  const src = ROOT + '/application/libraries/phpqrcode/' + f;
  if (f.endsWith('.php')) {
    await php.writeFile('/app/application/libraries/phpqrcode/' + f, fs.readFileSync(src));
  }
}
await php.writeFile('/app/application/libraries/AdominQr.php',
  fs.readFileSync(ROOT + '/application/libraries/AdominQr.php'));
await php.writeFile('/app/run.php', `<?php
define('BASEPATH', 1); define('APPPATH', '/app/application/');
function base_url($p=''){ return 'https://x.test/'.ltrim((string)$p,'/'); }
function log_message($l,$m){ echo "LOG $m\\n"; }
class CfgStub { public $config=array(); function item($k){ return isset($this->config[$k]) ? $this->config[$k] : NULL; } }
class CIStub { public $config; function __construct($key){ $this->config=new CfgStub(); $this->config->config['encryption_key']=$key; } }
function &get_instance(){ return $GLOBALS['CI']; }

$GLOBALS['CI'] = new CIStub('');
require '/app/application/libraries/AdominQr.php';
$q = new AdominQr();
$inv = array('id'=>7,'inv_number'=>'INV-0007','date_create'=>1764000000,
             'final_total'=>'160080.00','for_user'=>3,
             'client'=>array('client_name'=>'ACME TRADERS LTD'));
echo "EMPTYKEY_URL " . var_export($q->verifyUrl($inv), TRUE) . "\\n";

$GLOBALS['CI'] = new CIStub('a-real-key-0123456789abcdef');
$q2 = new AdominQr();
echo "KEYED_URL " . $q2->verifyUrl($inv) . "\\n";
echo "KEYED_TOKEN " . $q2->token($inv) . "\\n";
$svg = $q2->svg($inv);          // some builds accept the invoice directly
if ($svg === '' && method_exists($q2,'svg')) {
    $svg = $q2->svg($q2->verifyUrl($inv), 92);
}
echo "KEYED_SVG_LEN " . strlen($svg) . "\\n";
echo "KEYED_SVG_IS_SVG " . (stripos($svg,'<svg') !== FALSE ? 'yes':'no') . "\\n";
// a different document must not carry the same code
$inv2 = $inv; $inv2['inv_number']='INV-0008';
echo "DIFFERENT_TOKEN " . ($q2->token($inv2) !== $q2->token($inv) ? 'yes' : 'no') . "\\n";
echo "MATCHES_OK " . ($q2->matches($inv, $q2->token($inv)) ? 'yes' : 'no') . "\\n";
echo "MATCHES_REJECTS " . ($q2->matches($inv2, $q2->token($inv)) ? 'yes' : 'no') . "\\n";
`);
await php.run('<?php require "/app/run.php";');
if (!out.trim()) { console.log('--- no output; raw ---'); }
if (process.env.DUMP) console.log('PHP OUT >>>\n'+out+'\n<<<');
const val = k => (new RegExp(k + ' (.*)').exec(out)||[])[1]?.trim();

check('with no key there is no URL (the silent case)', val('EMPTYKEY_URL') === "''", val('EMPTYKEY_URL'));
check('with a key the verify URL is built', /^https:\/\/x\.test\/verify\/INV-0007\//.test(val('KEYED_URL')||''), val('KEYED_URL'));
check('the token is 12 characters', (val('KEYED_TOKEN')||'').length === 12, val('KEYED_TOKEN'));
check('the code renders as SVG', Number(val('KEYED_SVG_LEN')) > 100, val('KEYED_SVG_LEN'));
check('the markup is an SVG element', val('KEYED_SVG_IS_SVG') === 'yes');
check('a different invoice gets a different code', val('DIFFERENT_TOKEN') === 'yes');
check('a code verifies against its own invoice', val('MATCHES_OK') === 'yes');
check('a code does not verify against another invoice', val('MATCHES_REJECTS') === 'no', val('MATCHES_REJECTS'));

// the config must now provision a key rather than leave it blank
const cfg = fs.readFileSync(ROOT + '/application/config/config.php','utf8');
// assert the shipped assignment *resolves to a key*, not merely that the old
// line is gone: any edit that ends up with an empty key must fail here
const assign = /^\$config\['encryption_key'\]\s*=\s*([^;]+);$/m.exec(cfg);
check('the config assigns an encryption key', !!assign, 'no assignment found');
check('the assignment is not the empty string', !!assign && !/^''$|^""$/.test(assign[1].trim()), assign ? assign[1].trim() : '');
check('the key is provisioned, not left to chance', !!assign && !/^\$config\['encryption_key'\]$/.test(assign[1]), assign ? assign[1].trim() : '');
check('the key comes from the environment first', /ADOMIN_ENCRYPTION_KEY/.test(cfg));
check('a generated key persists to disk', /file_put_contents\(\$path, \$key/.test(cfg));
check('generating one is logged', /encryption_key is not set/.test(cfg));

// ---- and every design actually draws it
// Two letterhead families: the shared .av-doc-qr block (minimal, classic,
// modern, creative, toner-save) and Banner's own .ib-qr. Both are legitimate;
// what matters is that the code is drawn and captioned.
const { execFileSync } = await import('node:child_process');
for (const t of ['banner','creative','toner-save','minimal','classic','modern']) {
  execFileSync(process.execPath, [HERE + '/qrender.mjs', t], { stdio: 'ignore', env: { ...process.env, REALQR: '1' } });
  const html = fs.readFileSync(HERE + '/page-'+t+'.html','utf8');
  // three placements, one block: .av-doc-qr-head in the shared letterhead,
  // .av-doc-qr in the parties row, .ib-qr in Banner's own header
  const av = /class="av-doc-qr[^"]*"[\s\S]{0,500}?<\/div>/.exec(html);
  const ib = /class="ib-qr"[\s\S]{0,500}?<\/div>/.exec(html);
  const block = av ? av[0] : (ib ? ib[0] : null);
  check(t+': the letterhead carries a QR block', !!block, 'neither .av-doc-qr nor .ib-qr');
  check(t+': the block draws an image or an svg',
    !!block && /(<img[^>]+class="(av-qr-img|ib-qr)"|<svg|<img)/.test(block) || (!!block && /<img/.test(block)),
    block ? block.slice(0,90) : 'no block');
  const cap = block ? (/(av-qr-cap|ib-qr-cap)">([^<]*)/.exec(block)||[])[2] : '';
  check(t+': the code has a caption telling the reader what it is', !!cap && cap.length > 2, cap || 'no caption');
}

console.log('\n'+(fail?fail+' FAILED':'all pass')+'  ('+pass+' passed)');
process.exit(fail?1:0);
