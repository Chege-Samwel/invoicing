// End-to-end model transaction using PHP 7.4, CodeIgniter's real query builder
// and SQLite. Schema is derived from the checked-in MySQL tables; this exercises
// real JOIN execution and NOT NULL constraints, not a mocked query builder.
import { PHP } from '@php-wasm/universal';
import { loadNodeRuntime, createNodeFsMountHandler } from '@php-wasm/node';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';
const root = fileURLToPath(new URL('../../', import.meta.url));
const schema = fs.readFileSync(root + 'database.sql', 'utf8');
const tables = ['invoices','invoices_items','invoices_firms','invoices_clients','invoices_logs','invoices_languages','invoices_translations','items','clients','firms_users','firms_translations'];
const columns = {};
for (const table of tables) {
  const def = schema.match(new RegExp('CREATE TABLE `' + table + '` \\(([\\s\\S]*?)\\) ENGINE='));
  if (!def) throw new Error('missing schema for ' + table);
  columns[table] = [...def[1].matchAll(/^\s*`([^`]+)`[^\n]*/gm)].map(m => ({
    name: m[1], required: /\bNOT NULL\b/i.test(m[0]) && !/\bDEFAULT\b/i.test(m[0])
  }));
}
const php = new PHP(await loadNodeRuntime('7.4', { emscriptenOptions: { processId: 1 } }));
await php.mkdir('/app'); await php.mount('/app', createNodeFsMountHandler(root));
const script = `<?php
error_reporting(E_ALL & ~E_DEPRECATED); define('BASEPATH','/app/system/'); define('USER_ID',3); define('SELECTED_COMPANY_ID',9);
function log_message($a,$b){} function show_error($s){throw new Exception($s);} function lang($s){return $s;} function config_item($s){return NULL;}
function full_document_number($n){return str_pad((string)(int)$n,10,'0',STR_PAD_LEFT);}
require BASEPATH.'database/DB_driver.php'; require BASEPATH.'database/DB_query_builder.php'; class CI_DB extends CI_DB_query_builder {}
require BASEPATH.'database/drivers/sqlite3/sqlite3_driver.php'; class CI_Model {function __construct(){}} require '/app/application/modules/users/models/NewInvoiceModel.php';
class Cfg { function item($key){return array('issued','draft');} }
$db=new CI_DB_sqlite3_driver(array('database'=>':memory:','db_debug'=>false,'char_set'=>'utf8','dbcollat'=>'utf8_general_ci','pconnect'=>false));
if (!$db->initialize()) throw new Exception('cannot initialize SQLite');
$schema=json_decode('${JSON.stringify(columns)}',true);
foreach ($schema as $table=>$cols) {
  $parts=array();
  foreach ($cols as $col) {
    if ($col['name']==='id') $parts[]='"id" INTEGER PRIMARY KEY AUTOINCREMENT';
    else $parts[]='"'.$col['name'].'" TEXT '.($col['required'] ? 'NOT NULL' : "DEFAULT ''");
  }
  if (!$db->query('CREATE TABLE "'.$table.'" ('.implode(',', $parts).')')) throw new Exception('schema '.$table);
}
// Demonstrate why the previous condition was invalid in the real query builder.
$db->join('firms_translations','firms_translations.for_firm','left');
$oldSql=$db->get_compiled_select('firms_users');
if (strpos($oldSql, 'USING')===FALSE) throw new Exception('old join did not reproduce');
$db->insert('firms_users',array('id'=>9,'for_user'=>3,'bulstat'=>'PIN','is_vat_registered'=>1,'vat_number'=>'VAT','default_currency'=>'KES'));
$db->insert('firms_users',array('id'=>10,'for_user'=>4,'bulstat'=>'OTHER','is_vat_registered'=>0,'vat_number'=>'','default_currency'=>'USD'));
$db->insert('firms_translations',array('id'=>1,'for_firm'=>9,'name'=>'Firm','address'=>'Address','city'=>'Nairobi','mol'=>'Owner','image'=>'','trans_name'=>'English','is_default'=>1));
$db->insert('firms_translations',array('id'=>2,'for_firm'=>9,'name'=>'Firm French','address'=>'Adresse','city'=>'Nairobi','mol'=>'Owner','image'=>'','trans_name'=>'French','is_default'=>0));
$db->insert('firms_translations',array('id'=>3,'for_firm'=>10,'name'=>'Other Firm','address'=>'Other','city'=>'Other','mol'=>'Other','image'=>'','trans_name'=>'Other','is_default'=>1));
$lang=array();foreach($schema['invoices_languages'] as $c){if($c['name']!=='id') $lang[$c['name']]='label';}$lang['id']=1;$lang['for_user']=0;
if (!$db->insert('invoices_languages',$lang)) throw new Exception('seed language');
$m=new NewInvoiceModel();$m->db=$db;$m->config=new Cfg();
$post=array('status'=>'issued','inv_type'=>'tax_inv','inv_number'=>'3','inv_currency'=>'KES','date_create'=>'28.11.2026','date_tax_event'=>'28.11.2026','maturity_date'=>'','remarks'=>'','payment_method'=>'M-Pesa','to_inv_number'=>'','to_inv_date'=>'','invoice_amount'=>'10','discount'=>'0','discount_type'=>'%','tax_base'=>'10','vat_percent'=>'0','vat_sum'=>'0','no_vat_reason'=>'','final_total'=>'10','userInfo'=>array('user'=>array('name'=>'Owner')),'invoice_translation'=>1,'invoice_firm_translation'=>2,'items_names'=>array('Item'),'items_quantities'=>array('1'),'items_quantity_types'=>array('pcs'),'items_prices'=>array('10'),'items_totals'=>array('10'),'item_from_list'=>array('0'),'client_from_list'=>0,'editId'=>0,'client_name'=>'Customer','client_bulstat'=>'','vat_number'=>'','client_ident_num'=>'','client_address'=>'Address','client_city'=>'','client_country'=>'','accountable_person'=>'','recipient_name'=>'');
$m->setInvoice($post);
$first=$db->where('for_invoice',1)->get('invoices_firms')->row_array();
if ($first['name']!=='Firm French' || $first['bulstat']!=='PIN' || $first['vat_number']!=='VAT') throw new Exception('selected translation/tax snapshot');
$post['status']='draft';$post['invoice_firm_translation']=3;$post['inv_number']='4';$m->setInvoice($post);
$second=$db->where('for_invoice',2)->get('invoices_firms')->row_array();
if ($second['name']!=='Firm' || $second['bulstat']!=='PIN') throw new Exception('foreign translation fallback');
$db->where('id',1)->update('firms_translations',array('is_default'=>0));
$post['invoice_firm_translation']=999;$post['inv_number']='5';$m->setInvoice($post);
$third=$db->where('for_invoice',3)->get('invoices_firms')->row_array();
if ($third['name']!=='Firm') throw new Exception('non-default fallback');
$db->where('for_firm',9)->delete('firms_translations');
$post['inv_number']='6';try {$m->setInvoice($post);throw new Exception('missing firm accepted');} catch(Exception $e){if($e->getMessage()!=='database_error') throw $e;}
if ($db->count_all_results('invoices')!==3) throw new Exception('missing firm did not roll back');
echo 'REAL_SQLITE_OK';
`;
const result = await php.run({ code: script });
if (result.exitCode !== 0 || !result.text.includes('REAL_SQLITE_OK') || /Warning|Fatal error|Notice/.test(result.text)) {
  console.error('FAIL real query builder/SQLite integration: ' + result.text + ' ' + result.errors); process.exit(1);
}
console.log('real PHP 7.4 + CI query builder + SQLite: selected/default/fallback firm and rollback pass (5 passed)');
process.exit(0);
