// Execute the real create controller with CI/database stand-ins, for both statuses.
import { PhpNode } from 'php-wasm/PhpNode.mjs';
import fs from 'node:fs';
const root = new URL('../../', import.meta.url);
const php = new PhpNode(); let output = '';
php.addEventListener('output', e => { output += e.detail[0]; });
await php.writeFile('/Newinvoice.php', fs.readFileSync(new URL('application/modules/users/controllers/invoices/Newinvoice.php', root)));
await php.writeFile('/run.php', `<?php
error_reporting(E_ALL & ~E_DEPRECATED);
define('BASEPATH', '/app/'); define('USER_ID', 3);
class USER_Controller {}
class StubConfig { function item($key) { return array('tax_inv'=>'invoice','prof'=>'pro-forma','debit'=>'debit-note','credit'=>'credit-note'); } }
class StubPermissions { public $allow = true; function hasPerm($p) { return $this->allow; } }
class StubSession { public $data=array(); function set_flashdata($k,$v) { $this->data[$k]=$v; } }
class StubEntitlements { public $allow=true; function canCreateInvoice(){return $this->allow;} function planKey(){return 'FREE';} function limitMessage($x){return 'Limit reached';} }
class StubModel { public $saved=array(); public $available=true; function checkIsFreeInvoiceNumber($n,$t,$e){return $this->available;} function setInvoice($post){$this->saved[]=$post;} }
if (!function_exists('mb_strlen')) { function mb_strlen($x){return strlen($x);} }
function full_document_number($n){return str_pad((string)(int)$n,10,'0',STR_PAD_LEFT);}
function lang($x){return $x;} function lang_url($x){return '/'.$x;} function log_message($a,$b){} function redirect($url){throw new Exception('redirect:'.$url);} function show_404(){throw new Exception('404');}
require '/Newinvoice.php';
$ref = new ReflectionClass('Newinvoice'); $c = $ref->newInstanceWithoutConstructor();
$c->config=new StubConfig(); $c->permissions=new StubPermissions(); $c->session=new StubSession(); $c->adominentitlements=new StubEntitlements(); $c->NewInvoiceModel=new StubModel(); $c->userInfo=array('user'=>array('name'=>'Owner'));
$method=$ref->getMethod('createInvoice'); $method->setAccessible(true);
$base=array('inv_type'=>'tax_inv','status'=>'issued','inv_number'=>'0001','client_name'=>'Customer','client_address'=>'Address','date_create'=>'28.11.2026','date_tax_event'=>'28.11.2026','items_names'=>array('Item'),'items_quantities'=>array('2'),'items_prices'=>array('4.5'),'items_quantity_types'=>array('pcs'),'items_totals'=>array('9'),'item_from_list'=>array('0'));
function run($controller,$method,$post) { $_POST=$post; $_GET=array(); try {$method->invoke($controller);} catch(Exception $e) { return $e->getMessage(); } return 'no redirect'; }
foreach(array('issued','draft') as $status) {
 $post=$base; $post['status']=$status; $result=run($c,$method,$post); $saved=end($c->NewInvoiceModel->saved);
 if ($saved['status']!==$status || $result!=='redirect:/user/invoice/view/0000000001' || $saved['inv_number']!=='0000000001') throw new Exception('bad '.$status.' '.$result);
}
$count=count($c->NewInvoiceModel->saved);
foreach (array('missing_rows','zero_quantity','bad_price','duplicate_number','invalid_number') as $case) {
 $post=$base; $c->NewInvoiceModel->available=true;
 if ($case==='missing_rows') $post['items_names']=array();
 if ($case==='zero_quantity') $post['items_quantities']=array('0');
 if ($case==='bad_price') $post['items_prices']=array('1,2');
 if ($case==='duplicate_number') $c->NewInvoiceModel->available=false;
 if ($case==='invalid_number') $post['inv_number']='not-a-number';
 $result=run($c,$method,$post);
 if ($result!=='redirect:/user/new/invoice' || count($c->NewInvoiceModel->saved)!==$count || empty($c->session->data['resultAction'])) throw new Exception('bad validation '.$case);
}
$c->NewInvoiceModel->available=true; $c->adominentitlements->allow=false;
if (run($c,$method,$base)!=='redirect:/user/new/invoice' || count($c->NewInvoiceModel->saved)!==$count || $c->session->data['resultAction'][0]!=='Limit reached') throw new Exception('cap not shown');
$c->adominentitlements->allow=true; $c->permissions->allow=false;
if (run($c,$method,$base)!=='404' || count($c->NewInvoiceModel->saved)!==$count) throw new Exception('permission');
echo 'CONTROLLER_OK';
`);
try { await php.run('<?php require "/run.php";'); } catch (e) { output += String(e); }
const model = fs.readFileSync(new URL('application/modules/users/models/NewInvoiceModel.php',root),'utf8');
if (!output.includes('CONTROLLER_OK') || /Warning|Notice|Fatal error/.test(output) || !/logInvoiceAction\(\$insertId, \$insertArray\['status'\]/.test(model)) {
  console.error('FAIL invoice creation: ' + output); process.exit(1);
}
console.log('issued and draft controller, validation, cap, permission and history pass (8 passed)'); process.exit(0);
