import { PhpNode } from '/tmp/node_modules/php-wasm/PhpNode.mjs';
import fs from 'node:fs';
const f = process.argv[2];
const php = new PhpNode(); let out = '';
php.addEventListener('output', e => { out += e.detail[0]; });
await php.run('<?php @mkdir("/chk",0777,TRUE);');
await php.writeFile('/chk.php', fs.readFileSync(f));
await php.writeFile('/checker.php', `<?php
$s = file_get_contents('/chk.php');
$s = preg_replace('/if\\s*\\(\\s*!\\s*defined\\(\\s*[\\x27"]BASEPATH[\\x27"]\\s*\\)\\s*\\)\\s*\\{.*?\\}/s', '', $s, 1);
// php-wasm stops the main script at a top-level construct; tokenise from a file instead
try { token_get_all($s, TOKEN_PARSE); echo 'OK'; } catch (Throwable $e) { echo 'SYNTAX ' . $e->getMessage(); }
`);
await php.run('<?php require "/checker.php";');
console.log(out.trim());
