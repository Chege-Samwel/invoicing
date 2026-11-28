// The standalone diagnostic must supply the paths normally set by index.php.
import { PhpNode } from 'php-wasm/PhpNode.mjs';
import fs from 'node:fs';

const root = new URL('../../', import.meta.url).pathname;
const source = fs.readFileSync(root + 'render-doctor.php', 'utf8');
const bootstrap = source.slice(0, source.indexOf("require dirname(__FILE__) . '/docker/adomin-init-db.php';"));
const pdf = fs.readFileSync(root + 'application/libraries/AdominPdf.php', 'utf8');
const php = new PhpNode();
let output = '';
php.addEventListener('output', e => { output += e.detail[0]; });
await php.run('<?php mkdir("/site/application/libraries/dompdf", 0777, true);');
await php.writeFile('/site/bootstrap.php', bootstrap);
await php.writeFile('/site/application/libraries/AdominPdf.php', pdf);
await php.writeFile('/site/application/libraries/dompdf/autoload.inc.php', '<?php');
await php.run(`<?php require '/site/bootstrap.php'; require APPPATH . 'libraries/AdominPdf.php'; echo 'PATHS:' . FCPATH . '|' . APPPATH . '|' . (class_exists('AdominPdf') ? 'loaded' : 'missing');`);
if (!output.includes('PATHS:/site/|/site/application/|loaded')) {
  console.error('FAIL diagnostic PDF bootstrap: ' + output);
  process.exit(1);
}
console.log('diagnostic PDF bootstrap loads with standalone paths (1 passed)');
