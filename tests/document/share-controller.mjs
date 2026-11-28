// Exercise the real public GET controller with CI stand-ins. Property names on
// the controller are deliberately case-sensitive, just as in PHP/CI.
import { PhpNode } from 'php-wasm/PhpNode.mjs';
import fs from 'node:fs';
const root = new URL('../../', import.meta.url).pathname;
const php = new PhpNode();
let output = '';
php.addEventListener('output', e => { output += e.detail[0]; });
await php.writeFile('/Home.php', fs.readFileSync(root + 'application/controllers/Home.php', 'utf8'));
await php.writeFile('/test.php', `<?php
error_reporting(E_ALL & ~E_DEPRECATED);
define('BASEPATH', '/system/');
class MY_Controller { public function __construct() {} }
class FakeLoader {
    public $views = array();
    function helper($v) {}
    function library($v) {}
    function model($v) {}
    function view($name, $data = array()) { $this->views[$name] = $data; }
}
class FakeInput { function method() { return 'get'; } function get($key) { return ''; } }
class FakeDb { function table_exists($table) { return false; } }
class FakePublic {
    function getInvoiceForShare($user, $token) {
        return array('id' => 7, 'for_user' => 3, 'status' => 'issued', 'firm' => array('name' => 'Firm'));
    }
}
class FakeDocs {
    function invoiceTemplate($unused, $id) { return 'creative'; }
    function firmInfoFromInvoice($invoice) { return array(); }
    function typeLabel($invoice) { return 'Invoice'; }
}
class FakeRequests {
    public $enabled = false;
    function pending($id) { return null; }
    function available() { return $this->enabled; }
}
function adomin_share_token_is_wellformed($v) { return true; }
function adomin_share_token_user($v) { return 3; }
function adomin_stamp() { return ''; }
function adomin_conf($section, $key) { return 'Invoices'; }
function base_url($path = '') { return '/' . $path; }
require '/Home.php';
$home = new Home();
$home->load = new FakeLoader();
$home->input = new FakeInput();
$home->db = new FakeDb();
$home->PublicModel = new FakePublic();
$home->adomindocs = new FakeDocs();
$home->StatusrequestsModel = new FakeRequests();
foreach (array(false, true) as $available) {
    $home->StatusrequestsModel->enabled = $available;
    $home->sharedInvoice('3uabcde');
    $data = $home->load->views['invoices_parts/invoice_share'];
    if ($data['canRecord'] !== $available || $data['shareToken'] !== '3uabcde' || $data['uniqid'] !== '3uabcde') {
        throw new Exception('unexpected share data');
    }
}
echo 'SHARE_GET_OK';
`);
try { await php.run('<?php require "/test.php";'); } catch (e) { output += String(e); }
if (!output.includes('SHARE_GET_OK')) {
  console.error('FAIL public controller GET: ' + output);
  process.exit(1);
}
console.log('public controller GET renders with and without request table (1 passed)');
