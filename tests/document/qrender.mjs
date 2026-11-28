import { PhpNode } from 'php-wasm/PhpNode.mjs';
import fs from 'node:fs';

// Harness paths. ROOT is the repository; HERE is this directory.
const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const HERE = new URL('./', import.meta.url).pathname.replace(/\/$/, '');
const php=new PhpNode(); let out='';
php.addEventListener('output',e=>{out+=e.detail[0];});
const prog = fs.readFileSync(HERE + '/qpage_body.txt','utf8');
for (const d of ['/app/application/helpers','/app/application/libraries','/app/application/views/invoices/templates']) {
  await php.run('<?php @mkdir("'+d+'",0777,TRUE);');
}
await php.writeFile('/app/application/helpers/anvoice_doc_helper.php', fs.readFileSync(ROOT + '/application/helpers/anvoice_doc_helper.php'));
await php.writeFile('/app/tpl.php', fs.readFileSync(ROOT + '/application/modules/users/views/invoices/templates/'+process.argv[2]+'.php'));
if (process.env.REALQR) {
  // draw the code with the real library rather than a stub, so what is
  // rendered here matches what a client receives
  await php.run('<?php @mkdir("/app/application/libraries/phpqrcode",0777,TRUE);');
  for (const f of fs.readdirSync(ROOT + '/application/libraries/phpqrcode')) {
    if (f.endsWith('.php')) await php.writeFile('/app/application/libraries/phpqrcode/'+f, fs.readFileSync(ROOT + '/application/libraries/phpqrcode/'+f));
  }
  await php.writeFile('/app/application/libraries/AdominQr.php', fs.readFileSync(ROOT + '/application/libraries/AdominQr.php'));
}
await php.writeFile('/app/qr.php', `<?php if (!function_exists('adomin_qr_svg')) { function adomin_qr_svg($inv,$px=78){
  return '<svg width="'.$px.'" height="'.$px.'" viewBox="0 0 21 21"><rect width="21" height="21" fill="#fff"/><path d="M0 0h7v7H0zM9 0h3v3H9zM14 0h7v7h-7zM0 9h3v3H0zM5 5h3v3H5zM9 9h3v3H9zM14 9h7v3h-7zM0 14h7v7H0zM9 14h3v7H9zM14 14h7v7h-7z" fill="#000"/></svg>';
}}`);
let prog2 = prog;
if (process.env.PIN === '0') {
  // the harness already reads $GLOBALS['PIN'] when it builds the layout
  prog2 = prog;;
}
try { await php.run('<?php @unlink("/app/pinoff"); @unlink("/app/benoff");'); } catch (e) {}
if (process.env.PIN === '0') await php.writeFile('/app/pinoff', '1');
if (process.env.BEN === '0') await php.writeFile('/app/benoff', '1');
await php.writeFile('/app/run.php', prog2);
await php.run('<?php require "/app/run.php";');
const suffix = (process.env.PIN === '0' ? '-nopin' : '') + (process.env.BEN === '0' ? '-noben' : '');
fs.writeFileSync(HERE + '/page-'+process.argv[2]+suffix+'.html', out);
console.log('wrote /tmp/h/page-'+process.argv[2]+suffix+'.html', out.length, 'bytes');
