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
        $this->load->model(array('InvoicesModel', 'SettingsModel', 'NewInvoiceModel'));
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
        if ((isset($_POST['invId']) && is_numeric($_POST['invId'])) && isset($_POST['newStatus'])) {
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

    public function changeInvoiceStatus($invoiceId, $toStatus)
    {
        $this->InvoicesModel->updateInvoicePaymentStatus($invoiceId, $toStatus);
    }

}
