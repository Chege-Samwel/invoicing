<?php

/*
 * @Author:    Kiril Kirkov
 *  Github:    https://github.com/kirilkirkov
 *
 * Adomin Technologies fork:
 *  - PDFs are rendered by AdominPdf (bundled dompdf) instead of a remote CSS
 *    fetch + wkhtmltopdf, so the download works on free tier hosting where the
 *    app cannot make HTTP requests to itself.
 *  - every invoice exposes a public "send via link" share URL.
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Invoiceview extends USER_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('NewInvoiceModel');
        $this->load->model('SettingsModel');
        $this->load->library(array('AdominDocs', 'AdominPdf'));
    }

    public function index($invType, $invNum)
    {
        $data = array();
        $head = array();
        $head['title'] = lang('title_everytime') . lang('title_inv_preview') . $invNum;
        $inv_readable_types_flip = array_flip($this->config->item('inv_readable_types'));
        $invoice = $this->getInvoiceByNumber($inv_readable_types_flip[$invType], $invNum);
        if ($invoice == null) {
            log_message('error', 'User with id - ' . USER_ID . ' get 404 when try to view invoice with type -' . $invType . ' and number - ' . $invNum);
            show_404();
        }
        $template = $this->adomindocs->invoiceTemplate(NULL, USER_ID);
        $templateFile = $this->adomindocs->invoiceTemplateFile($template);
        if (!$templateFile) {
            show_error(lang('no_template_file'));
        }
        $data['actionHistory'] = $this->NewInvoiceModel->getActionHistory($invoice['id']);
        $data['invoice'] = $invoice;
        // absolute path for the legacy include used by the preview view
        $data['templateFile'] = str_replace(FCPATH, '', $templateFile);
        $data['invType'] = $invType;
        $data['invNum'] = $invNum;
        $data['template'] = $template;
        /*
         * The same firm snapshot the PDF is given.
         *
         * viewInvoiceAsPdf() passes $this->firmInfo to invoiceHtml(); this page
         * passed nothing, and the templates read $firmInfo for the logo switch
         * and the logo path. So the preview could draw a letterhead with no logo
         * while the download of the same invoice drew one - the two paths
         * disagreeing about the same document. Built from the invoice's own
         * snapshot, which is what the PDF falls back to, so they cannot differ.
         */
        $data['firmInfo'] = $this->adomindocs->firmInfoFromInvoice($invoice);
        $data['shareEnabled'] = (bool) adomin_conf('share', 'enabled');
        $data['shareUrl'] = $this->shareUrlFor($invoice);
        $data['pdfUrl'] = base_url('user/' . $invType . '/print/' . $invNum);
        $this->render('invoices/view', $head, $data);
        $this->saveHistory('Go to preview invoice with number ' . $invNum . ' and firm id' . SELECTED_COMPANY_ID);
    }

    /**
     * Build the PDF and send it to the browser.
     *
     * @param string $invType invoice | pro-forma | debit-note | credit-note
     * @param mixed  $invNum
     */
    public function viewInvoiceAsPdf($invType, $invNum)
    {
        $inv_readable_types = array_flip($this->config->item('inv_readable_types'));
        $invoice = $this->getInvoiceByNumber($inv_readable_types[$invType], $invNum);

        if ($invoice == null) {
            log_message('error', 'User with id - ' . USER_ID . ' get 404 when try to render PDF invoice with type -' . $invType . ' and number - ' . $invNum);
            show_404();
        }

        $template = $this->adomindocs->invoiceTemplate(NULL, USER_ID);
        // no origin: the document is not one of a pair any more
        $html = $this->adomindocs->invoiceHtml($invoice, $template, '', $this->firmInfo);

        // margins: the old wkhtmltopdf call used 4mm sides / 10mm top-bottom,
        // dompdf needs a bigger bottom band so the drawn footer never overlaps
        $options = array(
            'margins' => array('left' => 8, 'right' => 8, 'top' => 10, 'bottom' => 16),
            'footer' => array(
                // No copy/original suffix: the document no longer carries that
                // distinction anywhere, so a footer that did would be the one
                // place still printing a word the rest of the sheet had dropped.
                'left' => $this->adomindocs->typeLabel($invoice) . ' № ' . $invoice['inv_number'],
                'right' => $this->adomindocs->pageLabel($invoice) . ' {PAGE_NUM} / {PAGE_COUNT}',
            ),
            'stamp' => (bool) adomin_conf('pdf', 'stamp'),
        );

        $filename = $this->adomindocs->filename($invType, $invoice['inv_number']);
        $this->adominpdf->streamAndExit($html, $filename, $options);
    }

    /**
     * AJAX: the public "send via link" URL of an invoice (created on demand).
     */
    public function shareLink($id)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        $invoice = $this->NewInvoiceModel->getInvoiceForShare($id);
        if (empty($invoice)) {
            show_404();
        }
        $token = $this->NewInvoiceModel->ensureShareToken($invoice['id'], $invoice['uniqid']);
        $url = adomin_share_url(array('uniqid' => $token));
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(array(
                'status' => 'success',
                'url' => $url,
                'acceptUrl' => base_url('accept/invoice/' . $token),
                'pdfUrl' => base_url('share/invoice/' . rawurlencode($token) . '/pdf'),
                'revokeUrl' => lang_url('user/invoice/revoke-share/' . $invoice['id']),
                'number' => $invoice['inv_number'],
                'total' => $invoice['final_total'] . ' ' . $invoice['inv_currency'],
                'client' => $invoice['client_name'],
                'subject' => $invoice['inv_type'] === 'prof' ? 'Pro forma invoice' : 'Invoice',
                'message' => $this->shareMessage($invoice, $url),
            )));
    }

    /**
     * Pre-filled WhatsApp / SMS / e-mail body for a share link.
     *
     * @param array  $invoice
     * @param string $url
     * @return string
     */
    private function shareMessage($invoice, $url)
    {
        $brand = adomin_conf('brand', 'short') ?: 'Adomin';
        $firm = isset($invoice['firm']['name']) ? $invoice['firm']['name'] : '';
        $amount = $invoice['final_total'] . ' ' . $invoice['inv_currency'];
        return sprintf(
            "%s %s%s\n\n%s: %s\n%s\n\nView & download: %s",
            adomin_conf('locale', 'greeting') ?: 'Hello',
            $invoice['client_name'],
            $firm ? ' (' . $firm . ')' : '',
            lang('invoices'),
            $invoice['inv_number'],
            $amount,
            $url
        );
    }

    /**
     * @param array $invoice row with 'uniqid'
     * @return string
     */
    private function shareUrlFor($invoice)
    {
        $prefix = adomin_conf('share', 'prefix') ?: 'invoice';
        $token = isset($invoice['uniqid']) ? $invoice['uniqid'] : '';
        if (!$token) {
            return '';
        }
        return base_url($prefix . '/' . rawurlencode($token));
    }

    public function getInvoiceByNumber($invType, $invNum)
    {
        return $this->NewInvoiceModel->getInvoiceByNumber($invType, $invNum);
    }

}
