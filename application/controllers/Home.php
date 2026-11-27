<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Home extends MY_Controller
{

    private $blogLimit = 5;

    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $data = array();
        $head = array();
		$head['title'] = lang('title_home');
		$head['description'] = lang('description_home'); 
        $this->render('home/index', $head, $data);
    }

    public function logout()
    {
        unset($_SESSION['user_login']);
        unset($_SESSION['selected_company']);
        redirect(lang_url());
    }

    public function publicAcceptInvoice($uniqid)
    {
        $data = array();

        $arr = explode('u', $uniqid);
        if (!isset($arr[0]) || !is_numeric($arr[0])) {
            show_404();
        }

        $userId = $arr[0];
        $row = $this->PublicModel->getInvoiceForAccept($userId, $uniqid);
        if (empty($row)) {
            log_message('error', 'Try to accept not available invoice with uniqid - ' . $uniqid);
            show_404();
        }

        $invoice = modules::run('users/invoices/invoiceview/getInvoiceByNumber', $row['inv_type'], $row['inv_number']);
        if ($invoice == null) {
            show_404();
        }

        if (isset($_POST['action']) && ($_POST['action'] == 'accepted' || $_POST['action'] == 'refused')) {
            modules::run('users/invoices/invoices/changeInvoiceStatus', $invoice['id'], $_POST['action']);
            if ($_POST['action'] == 'accepted') {
                $action = 'accepted';
                $info = '';
            } else {
                $action = 'refused';
                $info = $_POST['refuse_reason'];
            }
            $this->PublicModel->setInvoiceLog($invoice['id'], $action, $info);
            redirect(base_url('accept/invoice/' . $invoice['uniqid']));
        }

        $template = $this->PublicModel->getOneValueStore('opt_invTemplate', $userId);

        $templates = $this->config->item('templates');
        if (!in_array($template, $templates)) {
            $template = $templates[0];
        }

        $templatesDir = 'application/modules/users/views/invoices/templates/';
        $templateFile = $templatesDir . $template . '.php';
        if (!is_file($templateFile)) {
            show_error(lang('no_template_file'));
        }
        $data['templateFile'] = $templateFile;
        $data['invoice'] = $invoice;
        $data['origin'] = 'original';
        $data['shareToken'] = $uniqid;
        $data['pdfUrl'] = base_url('share/invoice/' . rawurlencode($uniqid) . '/pdf');
        // the invoice templates read $firmInfo for the "show logo" switch; in a
        // public context there is no selected company, so rebuild it from the
        // invoice snapshot (without it the logo silently disappeared)
        $data['firmInfo'] = array(
            'id' => isset($invoice['firm']['for_company']) ? $invoice['firm']['for_company'] : (isset($invoice['for_company']) ? $invoice['for_company'] : 0),
            'name' => isset($invoice['firm']['name']) ? $invoice['firm']['name'] : '',
            'image' => isset($invoice['firm']['image']) ? $invoice['firm']['image'] : NULL,
            'show_logo' => 1,
        );
        $this->load->view('parts/invaccept/header');
        $this->load->view('invoices_parts/invoice_accept', $data);
        $this->load->view('parts/invaccept/footer');
    }

    /**
     * Legacy endpoint: wkhtmltopdf used to fetch its footer over HTTP, which
     * dead-locks a single-worker host (the "broken PDF" on Render free tier).
     * AdominPdf draws the footer in-process now; this only stays for bookmarks
     * and for deployments that opt back into PDF_ENGINE=wkhtmltopdf.
     */
    public function getInvoiceFooter()
    {
        if (!isset($_GET['num'])) {
            log_message('error', 'Call footer for wkhtmltopdf without GET[num] variable');
            exit;
        }
        $data = array();
        $data['num'] = $_GET['num'];
        $data['type'] = $_GET['type'];
        $data['pageTranslate'] = urldecode($_GET['pageTranslate']);
        $this->load->view('invoices_parts/footer.php', $data);
    }

    /**
     * "Send via link": a read-only copy of an invoice for a client, opened with
     * the token stored in invoices.uniqid. No login, no editing, no CSRF.
     *
     * @param string $uniqid
     */
    public function sharedInvoice($uniqid)
    {
        $invoice = $this->invoiceFromToken($uniqid);
        $this->load->library(array('AdominDocs', 'AdominPdf'));
        $data = array();
        $data['invoice'] = $invoice;
        $data['template'] = $this->adomindocs->invoiceTemplate(NULL, $invoice['for_user']);
        $data['templateFile'] = 'application/modules/users/views/invoices/templates/' . $data['template'] . '.php';
        $data['firmInfo'] = $this->adomindocs->firmInfoFromInvoice($invoice);
        $data['origin'] = 'original';
        $data['typeLabel'] = $this->adomindocs->typeLabel($invoice);
        $data['stamp'] = adomin_stamp();
        $data['shareToken'] = $uniqid;
        $data['pdfUrl'] = base_url('share/invoice/' . rawurlencode($uniqid) . '/pdf');
        $data['origin'] = 'original';
        $head = array();
        $head['title'] = (isset($invoice['firm']['name']) ? $invoice['firm']['name'] : adomin_conf('brand', 'short')) . ' · ' . adomin_conf('brand', 'app_name');
        $head['description'] = adomin_stamp();
        $this->logShareView($invoice, $uniqid);
        $this->load->view('parts/invaccept/header', $head);
        $this->load->view('invoices_parts/invoice_share', $data);
        $this->load->view('parts/invaccept/footer');
    }

    /**
     * The same document as a PDF for whoever holds the link.
     *
     * @param string $uniqid
     */
    public function sharedInvoicePdf($uniqid)
    {
        $invoice = $this->invoiceFromToken($uniqid);
        $this->load->library(array('AdominDocs', 'AdominPdf'));
        $template = $this->adomindocs->invoiceTemplate(NULL, $invoice['for_user']);
        $html = $this->adomindocs->invoiceHtml($invoice, $template, 'original', NULL);
        $options = array(
            'margins' => array('left' => 8, 'right' => 8, 'top' => 10, 'bottom' => 16),
            'footer' => array(
                'left' => $this->adomindocs->typeLabel($invoice) . ' № ' . $invoice['inv_number'],
                'right' => $this->adomindocs->pageLabel($invoice) . ' {PAGE_NUM} / {PAGE_COUNT}',
            ),
            'stamp' => (bool) adomin_conf('pdf', 'stamp'),
        );
        $this->adominpdf->streamAndExit($html, $this->adomindocs->filename('invoice', $invoice['inv_number']), $options);
    }

    /**
     * Optional audit trail for shared documents: who opened which link, when.
     * Silent when database/adomin-updates.sql has not been applied, so the
     * client-facing page can never fail because of logging.
     *
     * @param array  $invoice
     * @param string $token
     */
    private function logShareView($invoice, $token)
    {
        try {
            if (!$this->db->table_exists('invoice_share_views')) {
                return;
            }
            $this->db->insert('invoice_share_views', array(
                'for_invoice' => (int) $invoice['id'],
                'for_user' => (int) $invoice['for_user'],
                'token' => substr((string) $token, 0, 50),
                'ip' => substr((string) $this->input->ip_address(), 0, 45),
                'user_agent' => substr((string) $this->input->user_agent(), 0, 255),
                'time' => time(),
            ));
        } catch (Exception $e) {
            log_message('info', 'Adomin: share view not logged - ' . $e->getMessage());
        }
    }

    /**
     * The page a verification code opens.
     *
     * Everything about this page is written for someone holding a piece of
     * paper: it says which document the code belongs to, when it was issued and
     * for how much, and whether the figures in front of them still match the
     * record. It deliberately does not call itself an authenticity guarantee -
     * it is a signature check, and saying more than that would be a claim the
     * scheme cannot support.
     *
     * A miss is described the same way whether the number is unknown or the
     * signature is wrong. Distinguishing them would turn this into an oracle
     * for confirming that a guessed invoice number exists.
     *
     * @param string $invNumber
     * @param string $token
     */
    public function verify($invNumber = '', $token = '')
    {
        $this->load->library('AdominQr');
        $this->load->model('PublicModel');

        $invNumber = trim(rawurldecode((string) $invNumber));
        $token = trim((string) $token);

        $head = array();
        $head['title'] = lang('verify_title') . ' · ' . adomin_conf('brand', 'app_name');
        $head['description'] = lang('verify_intro');

        $data = array();
        $data['checked'] = TRUE;
        $data['verified'] = FALSE;
        $data['invoice'] = NULL;
        $data['invNumber'] = $invNumber;

        if ($invNumber === '' || !preg_match('/^[0-9a-f]{6,32}$/', $token)) {
            $data['checked'] = FALSE;
        } elseif (!$this->adominqr->available()) {
            /*
             * A host without the library (or without a key) cannot check
             * anything. Saying "not found" would be a lie about a document that
             * may well be genuine, so this is its own outcome.
             */
            $data['checked'] = FALSE;
            $data['unavailable'] = TRUE;
        } else {
            $matches = $this->PublicModel->getInvoicesByNumber($invNumber);
            foreach ($matches as $row) {
                if ($this->adominqr->matches($row, $token)) {
                    $data['verified'] = TRUE;
                    $data['invoice'] = $row;
                    break;
                }
            }
            if (!$data['verified'] && !empty($matches)) {
                log_message('info', 'Adomin: verification code did not match invoice ' . $invNumber);
            }
        }

        $this->load->view('parts/invaccept/header', $head);
        $this->load->view('invoices_parts/invoice_verify', $data);
        $this->load->view('parts/invaccept/footer');
    }

    /**
     * Share token -> invoice document (the same shape the preview uses).
     *
     * @param string $uniqid
     * @return array
     */
    private function invoiceFromToken($uniqid)
    {
        $uniqid = (string) $uniqid;
        if (!preg_match('/^\d+u[0-9a-z]{4,}$/i', $uniqid)) {
            show_404();
        }
        $parts = explode('u', $uniqid);
        $userId = (int) $parts[0];
        $invoice = $this->PublicModel->getInvoiceForShare($userId, $uniqid);
        if (empty($invoice)) {
            log_message('error', 'Adomin: invalid or revoked share token - ' . $uniqid);
            show_404();
        }
        return $invoice;
    }

}
