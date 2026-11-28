// Four small, separate defects that share one shape: a display value that
// came from somewhere else and was never meant to be read raw.
import fs from 'node:fs';

// Harness paths. ROOT is the repository; HERE is this directory.
const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const HERE = new URL('./', import.meta.url).pathname.replace(/\/$/, '');
let pass=0, fail=0;
const check=(n,ok,d)=>{ if(ok){pass++;console.log('  ok   '+n);} else {fail++;console.log('  FAIL '+n+(d?' — '+d:''));} };
const R=p=>fs.readFileSync(ROOT + '/'+p,'utf8');
const strip=src=>src.replace(/\/\*[\s\S]*?\*\//g,'').replace(/\/\/.*$/gm,'');

// --- N5: English is the default language for a new invoice
const newinv=R('application/modules/users/views/invoices/newinvoice.php');
const sel=[...newinv.matchAll(/<option value="(\d+)"([^>]*)>/g)].map(m=>({id:m[1],selected:/selected/.test(m[2])}));
const selectedIds=sel.filter(o=>o.selected).map(o=>o.id);
check('exactly one language is preselected', selectedIds.length===1, selectedIds.join(','));
check('the preselected language is English (id 1)', selectedIds[0]==='1', 'got '+selectedIds[0]);
check('French is still offered', sel.some(o=>o.id==='3'));
check('the model accepts 1 as a built-in id', /sysDefTransIds\s*=\s*array\(1,\s*2,\s*3\)/.test(R('application/modules/users/models/NewInvoiceModel.php')));

// --- A5: no language key is used that does not exist
const lang=R('application/language/english/users_lang.php');
check('adomin_copy_pdf_link is defined', /\$lang\['adomin_copy_pdf_link'\]\s*=/.test(lang));
check('it is not the bare key', !/=\s*'adomin_copy_pdf_link'/.test(lang));
check('the share modal uses it', /lang\('adomin_copy_pdf_link'\)/.test(R('application/modules/users/views/invoices/share_modal.php')));
check('the share page word "for" is defined', /\$lang\['for'\]\s*=/.test(lang));

// --- A1: no raw sentinel reaches the screen
const header=R('application/modules/users/views/parts/header.php');
const headerCode=strip(header);
check('the invoice badge is formatted', /adomin_plan_count\(\$planUnits\['num_invoices'\]\)/.test(headerCode));
check('the company badge is formatted', /adomin_plan_count\(\$firmsLeft\)/.test(headerCode));
check('no badge prints the counter raw', !/<span class="num"><\?=\s*\$planUnits\[/.test(headerCode));
check('the formatter maps the sentinel to Unlimited', />= 999999[\s\S]{0,80}lang\('unlimited'\)/.test(R('application/helpers/adomin_helper.php')));
check('unlimited is defined in English', /\$lang\['unlimited'\]\s*=/.test(lang));

// --- A4: every template offered can be saved
const ctrlRaw=R('application/modules/users/controllers/settings/Invoices.php');
const ctrl=strip(ctrlRaw);   // the docblock quotes the old list
const cfgMatch=/\$config\['templates'\]\s*=\s*array\(([^)]*)\)/s.exec(R('application/config/config.php'));
const cfgTemplates=cfgMatch[1].match(/'[^']+'/g).map(s=>s.replace(/'/g,''));
const offered=[...R('application/modules/users/controllers/settings/Invoices.php')
  .matchAll(/'(banner|creative|toner-save|minimal|classic|modern)'\s*=>\s*array\('/g)].map(m=>m[1]);
check('six designs are offered', offered.length===6, offered.join(','));
check('the save guard no longer hardcodes three', !/array\('creative', 'toner-save', 'banner'\)/.test(ctrl));
check('the save guard reads the config', /config->item\('templates'\)/.test(ctrl));
check('every offered design is in the config list',
  offered.every(t=>cfgTemplates.includes(t)), offered.filter(t=>!cfgTemplates.includes(t)).join(','));
check('every config template has a file',
  cfgTemplates.every(t=>fs.existsSync(ROOT + '/application/modules/users/views/invoices/templates/'+t+'.php')),
  cfgTemplates.filter(t=>!fs.existsSync(ROOT + '/application/modules/users/views/invoices/templates/'+t+'.php')).join(','));

console.log('\n'+(fail?fail+' FAILED':'all pass')+'  ('+pass+' passed)');
process.exit(fail?1:0);
