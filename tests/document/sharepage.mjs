// The share page belongs to the business that sent the invoice. Ours is the
// tool, not the sender: no logo above the document, one quiet credit, and a
// print that produces the invoice rather than a screenshot of a web page.
import fs from 'node:fs';

// Harness paths. ROOT is the repository; HERE is this directory.
const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const HERE = new URL('./', import.meta.url).pathname.replace(/\/$/, '');
let pass = 0, fail = 0;
const check = (n, ok, d) => { if (ok) { pass++; console.log('  ok   ' + n); } else { fail++; console.log('  FAIL ' + n + (d ? ' — ' + d : '')); } };
const R = p => fs.readFileSync(ROOT + '/' + p, 'utf8');
// comments explain the change and must not count as uses
const stripPhp = src => src.replace(/<\?(php)?[\s\S]*?\?>/g, m => m.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/.*$/gm, ''));
const stripJs  = src => src.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/.*$/gm, '');

const view = R('application/views/invoices_parts/invoice_share.php');
const brand = R('assets/public/css/adomin-brand.css');
const header = R('application/views/parts/invaccept/header.php');
const docs = R('application/libraries/AdominDocs.php');

// --- no logo above the document
check('no logo element above the document', !/class="logo"/.test(view), 'still has class="logo"');
check('adomin_logo_url is not used on the share page', !/adomin_logo_url/.test(view));
check('the logo asset is never referenced inline', !/adomin-mark\.svg/.test(view));

// --- one credit, quiet
const creditBlocks = (view.match(/adomin_foot\(|adomin_stamp_label\(|footer-text|adomin-share-credit/g) || []);
const viewCode = stripPhp(view);
check('exactly one credit element on the page',
  (viewCode.match(/adomin-share-credit|footer-text/g) || []).length === 1,
  (viewCode.match(/adomin_foot\(|adomin-share-credit|footer-text|adomin_stamp_label/g) || []).join(', '));
/*
 * The product leads, the company does not.
 *
 * "Powered by Adomin Technologies" was the only thing on the page, which puts
 * the company where the product belongs and says nothing about what made the
 * document. The product line is the credit; the company is in the PDF footer.
 */
check('the credit names the product, not the company',
  /adomin_share_credit\(\)/.test(view),
  'the page is a client\'s invoice: the product is what it should say');
/*
 * And the helper itself, rendered: three configurations, including a
 * white-label deploy that sets no app name and one that sets nothing at all.
 */
{
  const { PhpNode } = await import('php-wasm/PhpNode.mjs');
  const fs2 = await import('node:fs');
  const php = new PhpNode(); let out = '';
  php.addEventListener('output', e => { out += e.detail[0]; });
  await php.run('<?php @mkdir("/app",0777,TRUE);');
  await php.writeFile('/app/h.php', '<?php\n' + R('application/helpers/adomin_helper.php').replace(/^<\?php\s*/, ''));
  await php.writeFile('/app/t.php', `<?php
define('BASEPATH', 1);
$GLOBALS['brand'] = array('app_name' => 'anvoice', 'name' => 'anvoice', 'website' => 'https://adomin.co.ke');
function adomin_conf($g, $k = NULL) { return isset($GLOBALS['brand'][$k]) ? $GLOBALS['brand'][$k] : ''; }
function lang($k) { return 'Create and send invoices with'; }
require '/app/h.php';
echo 'A[' . adomin_share_credit() . ']' . "\n";
$GLOBALS['brand'] = array('app_name' => '', 'name' => 'Acme Billing', 'website' => '');
echo 'B[' . adomin_share_credit() . ']' . "\n";
$GLOBALS['brand'] = array('app_name' => '', 'name' => '', 'website' => '');
echo 'C[' . adomin_share_credit() . ']' . "\n";
`);
  await php.run('<?php require "/app/t.php";');
  check('the credit names anvoice', /A\[Create and send invoices with <a [^>]*>anvoice<\/a>\]/.test(out), out.trim());
  check('a white-label deploy gets its own name', /B\[Create and send invoices with <b>Acme Billing<\/b>\]/.test(out), out.trim());
  check('a deployment with no brand prints no empty phrase', /C\[\]/.test(out), out.trim());
}

check('the company is not the share page credit',
  !/adomin_stamp_label\(\)\s*\?>/.test(view),
  'adomin_stamp_label() is the whole "Powered by Adomin Technologies" phrase');
check('the render-time stamp is gone from the page', !/adomin_foot\(/.test(viewCode));
check('the credit is styled small and grey', /\.adomin-share-credit\s*\{[^}]*font-size:\s*11px/.test(brand));

// --- print produces only the document
const printBlock = brand.slice(brand.indexOf('@media print'));
check('print hides the page chrome', /\.adomin-share-lead/.test(printBlock) && /\.adomin-share-credit/.test(printBlock));
check('print hides any stray logo', /\.logo/.test(printBlock));
/*
 * And it must not hide the document's own credit while it hides the page's.
 * The two are both .adomin-stamp; only the page-level one is furniture.
 */
check('print keeps the credit that belongs to the document',
  /\.adomin-share-page \.invoice-box \.adomin-stamp[^{]*\{[^}]*display:\s*block/.test(printBlock),
  'the printed sheet loses the foot the PDF carries');
check('print still hides the page-level credit',
  /\.adomin-share-page \.adomin-stamp\s*,|[^{}]*\.adomin-share-page \.adomin-stamp\s*\{/.test(printBlock) ||
  /\.adomin-share-page \.footer-text\s*\{\s*display:\s*none/.test(printBlock));
check('print hides the horizontal rules', /hr/.test(printBlock));
check('print unclips the scrolled document box',
  /\.view-container\s*\{[^}]*overflow:\s*visible\s*!important/.test(printBlock.replace(/\n/g, ' ')));
check('print sets the same margins the PDF uses', /@page\s*\{[^}]*margin:\s*10mm 8mm 16mm/.test(printBlock));

// --- the PDF carries the credit once
// slice on the docblock, not on a brace: the method contains a regex literal
const stampStart = docs.indexOf('public function stampBlock');
const stampEnd = docs.indexOf('public function firmInfoFromInvoice');
const stampSrc = docs.slice(stampStart, stampEnd);
// drop comments by removing /* */ blocks, then look at code only
const stampBody = stampSrc.replace(/\/\*[\s\S]*?\*\//g, '')
                          .split('\n').filter(l => !l.trim().startsWith('//')).join('\n');
// count calls, not the function_exists() guard around them
const creditCalls = (stampBody.match(/adomin_pdf_credit\(/g) || []).length;
check('the PDF stamp prints one credit line', creditCalls === 1, creditCalls + ' calls');
check('the stamp prints one line total, not two',
  (stampBody.match(/<br\s*\/?>/g) || []).length === 0, 'still emits a <br> between two lines');
// the "Powered by" wording may only appear as the fallback when the credit
// helper is missing - never as a second line beside the real credit
const poweredIdx = stampBody.indexOf("$prefix) . ' ' . $brandHtml");
const elseIdx = stampBody.indexOf('} else {');
check('the "Powered by" wording is only a fallback, not a second line',
  poweredIdx === -1 || (elseIdx !== -1 && poweredIdx > elseIdx),
  'powered at ' + poweredIdx + ', else at ' + elseIdx);
check('the PDF credit names anvoice and Adomin Technologies',
  /PDF generated by/.test(R('application/helpers/adomin_helper.php')));

// --- the accept flow is untouched by this change
check('the accept form still posts a decision', /name="decision" value="accepted"/.test(view));
check('the accept form still says the business confirms', /business will record it/.test(view));
check('the print header still loads the template stylesheet', /invoices-templates\.css/.test(header));

// --- the browser and the PDF must be drawn with the same sheets
// A design that reads right in the PDF and wrong on the share page is the
// difference between "download it" and "look at it on your phone".
const printCss = [...(/private \$printCss = array\(([\s\S]*?)\);/.exec(docs)[1]).matchAll(/'([^']+\.css)'/g)].map(m=>m[1]);
check('the PDF declares its stylesheets', printCss.length === 4, printCss.join(', '));
for (const sheet of printCss) {
  const base = sheet.split('/').pop();
  // must be an actual <link ...>, not a mention in a comment
  const linked = new RegExp('<link[^>]*' + base.replace('.', '\\.') + '[^>]*>');
  check('the share page links ' + base, linked.test(header),
    'no <link> for it in parts/invaccept/header.php');
}
// and every sheet a design's money classes live in must be reachable
const adoc = R('assets/users/css/invoice-adoc.css');
check('the money column is right-aligned in adoc', /\.adv-col-money\s*\{[^}]*text-align:\s*right/.test(adoc));
check('the banner money column is right-aligned', /\.ib-items tbody td\.ib-money\s*\{[^}]*text-align:\s*right/.test(R('assets/users/css/invoice-banner.css')));
check('the totals figures are right-aligned', /\.av-sum-figure\s*\{[^}]*text-align:\s*right/.test(R('assets/users/css/invoices-templates.css')));

console.log('\n' + (fail ? fail + ' FAILED' : 'all pass') + '  (' + pass + ' passed)');
process.exit(fail ? 1 : 0);
