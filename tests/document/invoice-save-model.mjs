// Execute the real model's transaction against the checked-in NOT NULL schema.
// This catches missing required columns under strict MySQL/TiDB, unlike a
// controller-only mock that never enters setInvoice().
import { PhpNode } from 'php-wasm/PhpNode.mjs';
import fs from 'node:fs';
const root = new URL('../../', import.meta.url);
const schema = fs.readFileSync(new URL('database.sql', root), 'utf8');
const tables = ['invoices','invoices_items','invoices_firms','invoices_clients','invoices_logs','invoices_languages','invoices_translations','items','clients'];
const required = {};
for (const table of tables) {
  const def = schema.match(new RegExp('CREATE TABLE `' + table + '` \\(([\\s\\S]*?)\\) ENGINE='));
  if (!def) throw new Error('missing schema for ' + table);
  required[table] = [...def[1].matchAll(/^\s*`([^`]+)`[^\n]*\bNOT NULL\b[^\n]*/gm)]
    .filter(m => m[1] !== 'id' && !/\bDEFAULT\b/i.test(m[0])).map(m => m[1]);
}
const php = new PhpNode(); let out=''; php.addEventListener('output',e=>{out+=e.detail[0];});
await php.writeFile('/model.php', fs.readFileSync(new URL('application/modules/users/models/NewInvoiceModel.php',root)));
await php.writeFile('/test.php', `<?php
error_reporting(E_ALL & ~E_DEPRECATED);
define('BASEPATH',1); define('USER_ID',3); define('SELECTED_COMPANY_ID',9);
class CI_Model { function __construct(){} }
class Cfg { function item($key){return array('issued','draft');} }
class Result { private $row; function __construct($r){$this->row=$r;} function row_array(){return $this->row;} }
class DB {
 public $required; public $status=true; public $records=array(); public $fail=''; public $rolled=false; public $committed=false; public $err=0;
 function __construct($required){$this->required=$required;}
 function trans_begin(){return true;} function trans_status(){return $this->status;} function trans_rollback(){$this->rolled=true; return true;} function trans_commit(){$this->committed=true;return true;}
 function error(){return array('code'=>$this->err,'message'=>'test failure');}
 function insert_id(){return 42;}
 function insert($table,$row){
   $this->records[]=array($table,$row);
   if ($this->fail===$table) {$this->status=false;$this->err=1234;return false;}
   foreach ($this->required[$table] as $key) if (!array_key_exists($key,$row)) {$this->status=false;$this->err=1364;return false;}
   return true;
 }
 function where(){return $this;} function join(){return $this;}
 function get($table){
   if ($table==='invoices_languages') { $r=array_fill_keys($this->required[$table], 'label'); $r['id']=1; $r['for_user']=0; return new Result($r); }
   if ($table==='firms_users') return new Result(array('bulstat'=>'PIN','name'=>'Firm','address'=>'Address','city'=>'Nairobi','mol'=>'Owner','is_vat_registered'=>0,'vat_number'=>'','default_currency'=>'KES','image'=>''));
   throw new Exception('Unexpected query '.$table);
 }
}
function full_document_number($n){return str_pad((string)(int)$n,10,'0',STR_PAD_LEFT);} function lang($s){return $s;} function log_message($a,$b){} function show_error($s){throw new Exception($s);}
require '/model.php';
$required=json_decode('${JSON.stringify(required)}',true);
$post=array('status'=>'issued','inv_type'=>'tax_inv','inv_number'=>'3','inv_currency'=>'KES','date_create'=>'28.11.2026','date_tax_event'=>'28.11.2026','maturity_date'=>'','remarks'=>'','payment_method'=>'M-Pesa','to_inv_number'=>'','to_inv_date'=>'','invoice_amount'=>'10','discount'=>'0','discount_type'=>'%','tax_base'=>'10','vat_percent'=>'0','vat_sum'=>'0','no_vat_reason'=>'','final_total'=>'10','userInfo'=>array('user'=>array('name'=>'Owner')),'invoice_translation'=>1,'invoice_firm_translation'=>1,'items_names'=>array('Item'),'items_quantities'=>array('1'),'items_quantity_types'=>array('pcs'),'items_prices'=>array('10'),'items_totals'=>array('10'),'item_from_list'=>array('0'),'client_from_list'=>0,'editId'=>0,'client_name'=>'Customer','client_bulstat'=>'','vat_number'=>'','client_ident_num'=>'','client_address'=>'Address','client_city'=>'','client_country'=>'','accountable_person'=>'','recipient_name'=>'');
foreach(array('issued','draft') as $status) {
 $db=new DB($required);$m=new NewInvoiceModel();$m->db=$db;$m->config=new Cfg();$post['status']=$status;$m->setInvoice($post);
 if (!$db->committed || $db->rolled) throw new Exception('strict schema save failed for '.$status);
 if ($db->records[0][1]['date_received']!==0 || $db->records[0][1]['return_reason']!=='' || $db->records[0][1]['status']!==$status) throw new Exception('invoice defaults/status');
 $last=end($db->records);if ($last[0]!=='invoices_logs' || $last[1]['action']!==$status) throw new Exception('history status');
}
foreach(array('invoices','invoices_items','items','invoices_clients','clients','invoices_firms','invoices_logs') as $bad) {
 $db=new DB($required);$db->fail=$bad;$m=new NewInvoiceModel();$m->db=$db;$m->config=new Cfg();
 try {$m->setInvoice($post);throw new Exception('did not stop');} catch (Exception $e) { if ($e->getMessage()!=='database_error') throw $e; }
 if (!$db->rolled || $db->committed) throw new Exception('failed to roll back '.$bad);
}
echo 'STRICT_SAVE_OK';
`);
try { await php.run('<?php require "/test.php";'); } catch (e) { out += String(e); }
if (!out.includes('STRICT_SAVE_OK') || /Warning|Notice|Fatal error/.test(out)) { console.error('FAIL model strict save: '+out); process.exit(1); }
console.log('real model strict schema, draft/issued and rollback checks pass (9 passed)'); process.exit(0);
