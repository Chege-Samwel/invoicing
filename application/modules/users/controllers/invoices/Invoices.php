<?php

/*
 * @Author:    Kiril Kirkov
 *  Github:    https://github.com/kirilkirkov
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Invoices extends USER_Controller
{

    private $num_rows = 20;

    public function __construct()
    {
        parent::__construct();
        $this->load->model(array('InvoicesModel', 'SettingsModel', 'NewInvoiceModel', 'StatusrequestsModel'));
        $this->load->library('AdominStatus');
        $paginationNumRows = $this->SettingsModel->getValueStores('opt_pagination');
        $this->num_rows = $paginationNumRows;
    }

    public function index($page = 0)
    {
        $data = array();
        $head = array();
        $head['title'] =  lang('title_everytime').lang('title_invoices');
        $this->postChecker();
        $rowscount = $this->InvoicesModel->countInvoices($_GET);
        $data['invoices'] = $this->InvoicesModel->getInvoices($this->num_rows, $page, $_GET);
        $data['inv_readable_types'] = $this->config->item('inv_readable_types');
        $data['linksPagination'] = pagination('user/invoices', $rowscount, $this->num_rows, MY_DEFAULT_LANGUAGE_ABBR != MY_LANGUAGE_ABBR ? 4 : 3);
        $data['paymentMethods'] = $this->NewInvoiceModel->getPaymentMethods();
        $data['countInvoices'] = $rowscount;
        // the account's own words for the five statuses
        $data['statusLabels'] = $this->adominstatus->labels($this->SettingsModel->getValueStores('opt_invStatusLabels'));
        /*
         * The statuses a person may set by hand, in pipeline order.
         *
         * Read from the library rather than written into the view, so the list a
         * dropdown offers and the list the endpoint accepts cannot drift apart -
         * and "sended" is not among them: it is a delivery flag the old app
         * wrote, not a state this one moves a document through.
         */
        $data['settableStatuses'] = $this->adominstatus->settable();
        $data['sumAmount'] = $this->InvoicesModel->sumOfAmounts($_GET);
        $this->render('invoices/index', $head, $data);
        $this->saveHistory('Go to invoices page');
    }

    private function postChecker()
    {
        if (isset($_POST['action'])) {
            if ($_POST['action'] == 'delete') {
                $this->deleteSelectedInvoices($_POST['ids']);
            }
            if ($_POST['action'] == 'stat_canceled') {
                $this->changeStatusCanceled($_POST['ids'], true);
            }
            if ($_POST['action'] == 'remove_canceled') {
                $this->changeStatusCanceled($_POST['ids'], false);
            }
        }
    }

    private function deleteSelectedInvoices($ids)
    {
        if (!$this->permissions->hasPerm('perm_delete_invoice')) {
            show_404();
        }
        $this->InvoicesModel->multipleDeleteInvoices($ids);
        redirect(lang_url('user/invoices'));
    }

    private function changeStatusCanceled($ids, $doCanceled)
    {
        if (!$this->permissions->hasPerm('perm_change_inv_status')) {
            show_404();
        }
        $this->InvoicesModel->multipleStatusCanceledInvoices($ids, $doCanceled);
        redirect(lang_url('user/invoices'));
    }

    public function deleteInvoice($id)
    {
        if (!$this->permissions->hasPerm('perm_delete_invoice')) {
            show_404();
        }
        $this->load->model('NewInvoiceModel');
        $this->NewInvoiceModel->logInvoiceAction($id, 'deleted', 'Removed from the invoice list');
        $this->InvoicesModel->deleteInvoice($id);
        $this->session->set_flashdata('resultAction', 'Invoice removed from the list. Its record is kept.');
        redirect(lang_url('user/invoices'));
    }

    /**
     * "Send via link": rotate the public token of an invoice, which kills every
     * link already in the wild, and go back to the invoice.
     *
     * @param int $id
     */
    public function revokeShare($id)
    {
        if (!$this->permissions->hasPerm('perm_change_inv_status')) {
            show_404();
        }
        $this->load->model('NewInvoiceModel');
        $this->NewInvoiceModel->revokeShareToken($id);
        $this->saveHistory('Revoked public share link of invoice id ' . $id);
        $back = $this->input->server('HTTP_REFERER');
        if ($back && strpos($back, base_url()) === 0) {
            redirect($back);
        }
        redirect(lang_url('user/invoices'));
    }

    public function changeInvoicePaymentStatus()
    {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }
        if (!$this->permissions->hasPerm('perm_change_inv_status')) {
            echo '0';
            return;
        }
        /*
         * The three payment states, not "whatever was posted". This value is a
         * column across which the app counts what is owed; an arbitrary string
         * would drop an invoice out of every total without an error.
         */
        $allowed = array('paid', 'unpaid', 'partly_paid');
        if ((isset($_POST['invId']) && is_numeric($_POST['invId']))
            && isset($_POST['newStatus']) && in_array($_POST['newStatus'], $allowed, TRUE)) {
            $this->load->model('NewInvoiceModel');
            $this->InvoicesModel->updateInvoicePaymentStatus($_POST['invId'], $_POST['newStatus']);
            /*
             * A status is how a business knows what is still owed, and it is
             * changed from a list with one click. Recording who changed it and
             * to what is the difference between a number and a fact.
             */
            $this->NewInvoiceModel->logInvoiceAction(
                $_POST['invId'],
                'payment_' . $_POST['newStatus'],
                'Payment marked ' . str_replace('_', ' ', $_POST['newStatus'])
            );
            echo '1';
            $this->saveHistory('Set new invoice status to - ' . $_POST['invId']);
        } else {
            echo '0';
        }
    }

    /**
     * AJAX: move a document's own status.
     *
     * Deliberately separate from changeInvoicePaymentStatus() below, which sets
     * whether the money arrived. The two are different axes - an invoice can be
     * accepted and unpaid - and the model has a separate scoped writer for each.
     */
    public function setInvoiceStatus()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        if (!$this->permissions->hasPerm('perm_change_inv_status')) {
            $this->output->set_status_header(403)->set_output('0');
            return;
        }

        $invoiceId = (int) $this->input->post('invId');
        $toStatus = (string) $this->input->post('newStatus');

        /*
         * The allowlist is the library's, not the form's. A dropdown is a hint;
         * this is the rule. An unknown value is logged and refused, so a request
         * typed by hand cannot put an invoice into a state the app cannot draw.
         */
        if (!in_array($toStatus, $this->adominstatus->settable(), TRUE)) {
            log_message('error', 'Rejected document status "' . $toStatus . '" for invoice ' . $invoiceId);
            $this->output->set_status_header(400)->set_output('0');
            return;
        }

        $moved = $this->InvoicesModel->updateInvoiceStatus($invoiceId, $toStatus);
        if ($moved) {
            $this->saveHistory('Set invoice ' . $invoiceId . ' to ' . $toStatus);
        }
        $this->output->set_content_type('text/plain')->set_output($moved ? '1' : '0');
    }

    public function changeInvoiceStatus($invoiceId, $toStatus)
    {
        /*
         * Name kept for the existing AJAX route and its callers, but this sets
         * the PAYMENT status (paid / unpaid / partly paid) - not the document's
         * status. The two are separate axes: an invoice can be accepted and
         * still unpaid. Only a logged-in user reaches this method, which is why
         * it is allowed to write at all.
         */
        $allowed = array('paid', 'unpaid', 'partly_paid');
        if (!in_array((string) $toStatus, $allowed, TRUE)) {
            log_message('error', 'Rejected payment status "' . $toStatus . '" for invoice ' . $invoiceId);
            return;
        }
        $this->InvoicesModel->updateInvoicePaymentStatus((int) $invoiceId, $toStatus);
    }

    /**
     * Requests from clients waiting for this business to confirm them.
     *
     * The queue exists because the share link is unauthenticated. A click
     * there files a request; this page is where a person with an account says
     * "yes, the client accepted it" - and that, not the click, is what moves
     * the document's status.
     */
    public function requests()
    {
        $data = array();
        $head = array();
        $head['title'] = lang('title_everytime') . lang('adomin_status_requests');

        if ($this->input->method() === 'post') {
            $this->handleRequestDecision();
        }

        $data['requests'] = $this->StatusrequestsModel->pendingForUser(USER_ID);
        $data['labels'] = $this->adominstatus->labels($this->SettingsModel->getValueStores('opt_invStatusLabels'));
        $data['available'] = $this->StatusrequestsModel->available();
        $data['inv_readable_types'] = $this->config->item('inv_readable_types');
        $this->render('invoices/requests', $head, $data);
        $this->saveHistory('Go to client status requests');
    }

    /**
     * Confirm or decline one request.
     *
     * The request id comes from the form and is re-read scoped to the account,
     * so an id belonging to another business resolves to nothing rather than
     * being confirmed. The document's status moves only on the confirmed path.
     */
    private function handleRequestDecision()
    {
        $id = (int) $this->input->post('request_id');
        $decision = (string) $this->input->post('decision');

        $request = $this->StatusrequestsModel->find($id, USER_ID);
        if (empty($request)) {
            show_404();
        }

        if ($decision !== 'confirm' && $decision !== 'decline') {
            redirect(lang_url('user/invoices/requests'));
        }

        $invoiceId = (int) $request['for_invoice'];
        $requested = (string) $request['status'];

        if ($decision === 'confirm' && $this->adominstatus->isKnown($requested)) {
            $this->InvoicesModel->updateInvoiceStatus($invoiceId, $requested);
            $this->StatusrequestsModel->resolve($id, 'confirmed', USER_ID);
            $this->NewInvoiceModel->logInvoiceAction($invoiceId, $requested, lang('adomin_status_confirmed_by_business'));
        } else {
            $this->StatusrequestsModel->resolve($id, 'declined', USER_ID);
            $this->NewInvoiceModel->logInvoiceAction($invoiceId, 'issued', lang('adomin_status_declined_by_business'));
        }

        redirect(lang_url('user/invoices/requests'));
    }

}
