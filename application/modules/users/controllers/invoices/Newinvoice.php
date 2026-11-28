<?php

/*
 * @Author:    Kiril Kirkov
 *  Github:    https://github.com/kirilkirkov
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Newinvoice extends USER_Controller
{

    private $editId = 0;
    private $invNum = 0;

    public function __construct()
    {
        parent::__construct();
        $this->load->model(array('NewInvoiceModel', 'SettingsModel', 'ManagefirmsModel'));
    }

    public function index($invType = null, $invNum = 0)
    {
        $data = array();
        $head = array();
        $head['title'] = lang('title_everytime') . lang('title_new_inv');
        $data['currencies'] = $this->NewInvoiceModel->getCurrencies();
        $data['myDefaultFirmCurrency'] = $this->NewInvoiceModel->getFirmDefaultCurrency();
        $data['quantityTypes'] = $this->NewInvoiceModel->getAllQuantityTypes();
        $data['paymentMethods'] = $this->NewInvoiceModel->getPaymentMethods();
        $data['invoiceLanguages'] = $this->NewInvoiceModel->getMyInvoiceLanguages();
        $data['myNoVatReasons'] = $this->SettingsModel->getMyNoVatReasons();
        $data['nextInvNumber'] = $this->NewInvoiceModel->getNextFreeInvoiceNumber();
        //if is not edit and want get $_POST from other document
        if (isset($_GET['create-from']) && isset($_GET['number']) && $invNum == 0) {
            $this->getPostFromDucument();
        }

        if ($invNum > 0) {
            $this->invNum = $invNum;
            $inv_readable_types = array_flip($this->config->item('inv_readable_types'));
            $result = $this->NewInvoiceModel->getInvoiceByNumber($inv_readable_types[$invType], $invNum);
            if (empty($result)) {
                log_message('error', 'User with id - ' . USER_ID . ' get 404 when try to edit invoice with type -' . $invType . ' and number - ' . $invNum);
                show_404();
            }
            $this->editId = $result['id'];
            $this->postChecker();
            $currentItems = array();
            foreach ($result['items'] as $item) {
                $currentItems[] = $item['id'];
            }
            $_POST = $result;
        } else {
            $this->postChecker();
        }

        $data['editId'] = $this->editId;
        if (isset($_POST['inv_currency'])) {
            $theCurrency = $_POST['inv_currency'];
        } elseif ($data['myDefaultFirmCurrency'] != null) {
            $theCurrency = $data['myDefaultFirmCurrency'];
        } else {
            $theCurrency = 'EUR';
        }
        $data['currentItems'] = isset($currentItems) ? $currentItems : null;
        $data['theCurrency'] = $theCurrency;
        $data['allForFirm'] = $this->ManagefirmsModel->getCompanyInfo(SELECTED_COMPANY_ID);
        $this->render('invoices/newinvoice', $head, $data);
        $this->saveHistory('Go to new invoice page');
    }

    /*
     * loads posts variable for client and items from 
     * other document
     */

    private function getPostFromDucument()
    {
        if ($_GET['create-from'] == 'store-order') {
            $this->load->model('StoreModel');
            $result = $this->StoreModel->getMovementByNumber($_GET['number']);
            if ($result != null) {
                $_POST['client'] = $result['client'];
                $_POST['items'] = $result['items'];
            }
        }
    }

    private function postChecker()
    {
        if (isset($_POST['addNewInvoiceLanguage'])) {
            $this->addNewInvoiceLanguage();
        }
        /*
         * Add new invoice
         */
        if (isset($_POST['inv_type'])) {
            $_POST['editId'] = $this->editId; // Check is update or new invoice
            $this->createInvoice();
        }
    }

    private function createInvoice()
    {
        $isValid = $this->validateInvoice();
        $inv_readable_types = $this->config->item('inv_readable_types');
        if ($isValid === true) {
            if ($this->editId > 0) {
                if (!$this->permissions->hasPerm('perm_edit_invoice')) {
                    show_404();
                }
                $this->NewInvoiceModel->updateInvoice($_POST);
            } else {
                if (!$this->permissions->hasPerm('perm_add_invoice')) {
                    show_404();
                }
                $_POST['userInfo'] = $this->userInfo; // get info for logged user
                /*
                 * Prevent a POST that dodges the "no room left" screen.
                 *
                 * The check used to read $this->planUnits['num_invoices'] > 0,
                 * where the value was a countdown stored on firms_plans. Pro is
                 * unlimited now, which that column cannot express, and the free
                 * allowance has to reset monthly. AdominEntitlements answers the
                 * question against the invoices actually created this month.
                 *
                 * The buyer must be told why, not silently redirected to an
                 * invoice that was never written - which is what used to happen.
                 */
                if ($this->adomentitlements->canCreateInvoice()) {
                    $this->NewInvoiceModel->setInvoice($_POST);
                    $this->setDocumentPointer($_POST['inv_number']); //optional
                } else {
                    log_message('error', 'Invoice cap reached: user ' . (defined('USER_ID') ? USER_ID : 0)
                        . ' tried to create an invoice by POST on the '
                        . $this->adomentitlements->planKey() . ' plan');
                    $this->session->set_flashdata('limitReached', $this->adomentitlements->limitMessage('invoices_per_month'));
                    redirect(lang_url('user/new/invoice'));
                    return;
                }
            }
            redirect(lang_url('user/' . $inv_readable_types[$_POST['inv_type']] . '/view/' . $_POST['inv_number']));
        } else {
            $this->session->set_flashdata('resultAction', $isValid);
            if ($this->editId > 0) {
                redirect(lang_url('user/' . $inv_readable_types[$_POST['inv_type']] . '/edit/' . $this->invNum));
            } else {
                redirect(lang_url('user/new/invoice'));
            }
        }
    }

    private function validateInvoice()
    {
        $errors = array();
        if ($_POST['inv_type'] == 'debit' || $_POST['inv_type'] == 'credit') {
            if (mb_strlen(trim($_POST['to_inv_number'])) == 0) {
                $errors[] = lang('err_create_to_inv_num');
            }
            if (mb_strlen(trim($_POST['to_inv_date'])) == 0) {
                $errors[] = lang('err_create_to_inv_date');
            }
        }
        if (mb_strlen(trim($_POST['client_name'])) == 0) {
            $errors[] = lang('err_create_client_name');
        }
        if (mb_strlen(trim($_POST['client_address'])) == 0) {
            $errors[] = lang('err_create_client_addr');
        }
        if (mb_strlen(trim($_POST['inv_number'])) == 0) {
            $errors[] = lang('err_create_inv_num');
        } else {
            $isFreeInvNum = $this->NewInvoiceModel->checkIsFreeInvoiceNumber($_POST['inv_number'], $_POST['inv_type'], $this->editId);
            if ($isFreeInvNum === false) {
                $errors[] = lang('err_create_inv_num_is_taken');
            }
        }
        if (mb_strlen(trim($_POST['date_create'])) == 0) {
            $errors[] = lang('err_create_date_create');
        }
        if (mb_strlen(trim($_POST['date_tax_event'])) == 0) {
            $errors[] = lang('err_create_tax_event');
        }
        foreach ($_POST['items_names'] as $item_name) {
            if (mb_strlen(trim($item_name)) == 0) {
                $errors[] = lang('err_create_no_item_name');
            }
        }
        foreach ($_POST['items_quantities'] as $item_quantity) {
            if (mb_strlen(trim($item_quantity)) == 0) {
                $errors[] = lang('err_create_no_item_qua');
            }
        }
        if (empty($errors)) {
            return true;
        }
        return $errors;
    }

    /*
     * When submit form for create new invoice
     * Check if information for this invoice comes from other document
     * And set "to invoice" in him
     */

    private function setDocumentPointer($inv_number)
    {
        if (isset($_GET['create-from']) == 'store-order') {
            $this->load->model('StoreModel');
            $this->StoreModel->updateMovementPointToInvNumber($inv_number, $_GET['number']);
        }
    }

    private function addNewInvoiceLanguage()
    {
        $this->NewInvoiceModel->setNewInvoiceLanguage($_POST);
        $this->saveHistory('Add invoice translation - ' . $_POST['language_name']);
        redirect(lang_url('user/new/invoice'));
    }

    public function addnewquantitytype()
    {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }
        if (isset($_POST['newVal'])) {
            $this->NewInvoiceModel->setNewCustomQuantityType($_POST['newVal']);
        }
    }

    public function addnewpaymentmethod()
    {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }
        if (isset($_POST['newVal'])) {
            $this->NewInvoiceModel->setNewCustomPaymentMethod($_POST['newVal']);
        }
    }

    /**
     * Suggestions for the item-name field, as the name is typed.
     *
     * Answers JSON: a list of {name, quantity_type, single_price, currency}.
     * The client only ever inserts `name` and copies the unit, so a stale price
     * cannot be billed silently - see the comment in general.js.
     */
    public function itemsuggest()
    {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }

        $term = isset($_POST['term']) ? (string) $_POST['term'] : '';
        $rows = $this->NewInvoiceModel->suggestItems($term, 8);

        $out = array();
        foreach ($rows as $row) {
            $out[] = array(
                'name'          => html_entity_decode((string) $row['name'], ENT_QUOTES, 'UTF-8'),
                'quantity_type' => (string) $row['quantity_type'],
                'single_price'  => (string) $row['single_price'],
                'currency'      => (string) $row['currency'],
            );
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($out));
    }

    public function modalselector()
    {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }
        if (!isset($_POST['selectType'])) {
            return;
        }

        $selectType = (string) $_POST['selectType'];
        $result = $this->NewInvoiceModel->getListForSelector($selectType);

        if (!empty($result)) {
            include 'application/modules/users/views/invoices/listSelectorHtml.php';
        } else {
            /*
             * The ternary used to be concatenated without parentheses, so it
             * bound to the whole left-hand string: the "no clients" message came
             * out wrapped in the <div> and the "no items" message lost its
             * wrapper entirely, printing raw into the dialog body.
             */
            $message = $selectType === 'clients' ? lang('no_clients_selector') : lang('no_items_selector');
            echo '<div class="no-data">' . html_escape($message) . '</div>';
        }
    }

}
