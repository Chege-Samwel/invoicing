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

    public function __construct()
    {
        parent::__construct();
        $this->load->model(array('SettingsModel', 'NewInvoiceModel'));
    }

    public function index()
    {
        $data = array();
        $head = array();
        $this->postChecker();
        $head['title'] = lang('title_everytime') . lang('title_inv_sett');
        $data['myFirms'] = $this->SettingsModel->getMyFirmsDefaultCurrency();
        $data['currencies'] = $this->NewInvoiceModel->getCurrencies();
        $data['myCurrencies'] = $this->SettingsModel->getMyCurrencies();
        $data['myQuantityTypes'] = $this->SettingsModel->getMyQuantityTypes();
        $data['myPaymentMethods'] = $this->SettingsModel->getMyPaymentMethods();
        $data['myNoVatReasons'] = $this->SettingsModel->getMyNoVatReasons();
        $data['opt_invTemplate'] = $this->SettingsModel->getValueStores('opt_invTemplate');
        $this->render('settings/invoices', $head, $data);
        $this->saveHistory('Go to settings invoices page');
    }

    private function postChecker()
    {
        if (isset($_POST['currencyName'])) {
            $this->addCurrency();
        }
        if (isset($_POST['quantityTypeName'])) {
            $this->addQuantityType();
        }
        if (isset($_POST['paymentMethodName'])) {
            $this->addPaymentMethod();
        }
        if (isset($_POST['noVatReason'])) {
            $this->addNewNoVatReason();
        }
        if (isset($_POST['noVatReason'])) {
            $this->addNewNoVatReason();
        }
        if (isset($_POST['opt_invRoundTo'])) {
            $this->updateInvoicesRoundTo();
        }
        if (isset($_POST['updateInvCalculator'])) {
            $this->updateInvCaluculatorUsage();
        }
        if (isset($_POST['updateInvTemplate'])) {
            $this->updateInvTemplate();
        }
        if (isset($_POST['saveInvLayout'])) {
            $this->saveInvoiceLayout();
        }
    }

    private function updateInvCaluculatorUsage()
    {
        $this->SettingsModel->setValueStore('opt_invCalculator', isset($_POST['opt_invCalculator']) ? 0 : 1);
        $this->saveHistory('Set calculator usage to - ' . $_POST['opt_invCalculator'] == 0 ? 'off' : 'on');
        redirect(lang_url('user/settings/invoices'));
    }

    private function updateInvoicesRoundTo()
    {
        $this->SettingsModel->setValueStore('opt_invRoundTo', $_POST['opt_invRoundTo']);
        $this->saveHistory('Update round invoices total to - ' . $_POST['opt_invRoundTo']);
        redirect(lang_url('user/settings/invoices'));
    }

    private function addNewNoVatReason()
    {
        $this->NewInvoiceModel->setNewVatReason($_POST['noVatReason']);
        $this->saveHistory('Add new vat reason - ' . $_POST['noVatReason']);
        redirect(lang_url('user/settings/invoices'));
    }

    private function addPaymentMethod()
    {
        $this->NewInvoiceModel->setNewCustomPaymentMethod($_POST['paymentMethodName']);
        $this->saveHistory('Add new payment method - ' . $_POST['paymentMethodName']);
        redirect(lang_url('user/settings/invoices'));
    }

    private function addCurrency()
    {
        $this->SettingsModel->setNewCurrency($_POST);
        $this->saveHistory('Add new currency - ' . $_POST['currencyName'] . ' - ' . $_POST['currencyValue']);
        redirect(lang_url('user/settings/invoices'));
    }

    private function addQuantityType()
    {
        $this->NewInvoiceModel->setNewCustomQuantityType($_POST['quantityTypeName']);
        $this->saveHistory('Add new quantity type - ' . $_POST['quantityTypeName']);
        redirect(lang_url('user/settings/invoices'));
    }

    private function updateInvTemplate()
    {
        /*
         * The stored name becomes a file path when the document is rendered
         * (Home.php builds include $templateFile), so only the designs that
         * exist here may be stored. Anything else is ignored rather than saved.
         */
        $allowed = array('creative', 'toner-save');
        $template = isset($_POST['invTempl']) ? (string) $_POST['invTempl'] : '';
        if (!in_array($template, $allowed, TRUE)) {
            $this->session->set_flashdata('resultAction', 'Unknown template');
            redirect(lang_url('user/settings/invoices') . '#template');
        }

        $this->SettingsModel->setValueStore('opt_invTemplate', $template);
        $this->saveHistory('Update invoices template to - ' . $template);
        $this->session->set_flashdata('resultAction', 'Template saved');
        redirect(lang_url('user/settings/invoices') . '#template');
    }

    /*
     * Saving the layout can also carry the design, because the pane presents
     * them as one thing. The design still has its own endpoint for the plain
     * post, so both paths validate against the same list.
     */
    }

    /*
     * Called from ajax only
     */

    public function defaultcurrency()
    {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }
        if ((isset($_POST['forId']) && is_numeric($_POST['forId'])) && isset($_POST['newDefault'])) {
            $this->SettingsModel->setNewDefaultCurrency($_POST);
            echo '1';
            $this->saveHistory('Changed default currency to - ' . $_POST['newDefault'] . ' for firm id - ' . $_POST['forId']);
        } else {
            echo '0';
        }
    }

    public function deleteDefaultCurrency($num)
    {
        $this->SettingsModel->deleteDefaultCurrency($num);
        redirect(lang_url('user/settings/invoices'));
    }

    public function deleteCurrency($num)
    {
        $this->SettingsModel->deleteMyCurrency($num);
        redirect(lang_url('user/settings/invoices'));
    }

    public function deleteQuantityType($id)
    {
        $this->SettingsModel->deleteCustomQuantityType($id);
        redirect(lang_url('user/settings/invoices'));
    }

    public function deletePaymentMethod($id)
    {
        $this->SettingsModel->deleteCustomPaymentMethod($id);
        redirect(lang_url('user/settings/invoices'));
    }

    public function deleteNoVatReason($id)
    {
        $this->SettingsModel->deleteMyNoVatReason($id);
        redirect(lang_url('user/settings/invoices'));
    }


    /*
     * Invoice layout.
     *
     * The template (which design) and the layout (where things sit) are separate
     * on purpose: switching design should not lose the placement work, and
     * moving the logo should not change the design. Ten keys, all validated
     * against a fixed set, so a request cannot put an arbitrary value into the
     * value store and have it printed into a document.
     */

    private function saveInvoiceLayout()
    {
        $fields = array(
            'opt_invLogoPos'    => array('left', 'center', 'right', 'hide'),
            'opt_invAddressPos' => array('above', 'below', 'side'),
            'opt_invShowLogo'   => array('0', '1'),
            'opt_invShowAddress'=> array('0', '1'),
            'opt_invShowContacts' => array('0', '1'),
            'opt_invShowFirmVat'=> array('0', '1'),
            'opt_invShowRemarks'=> array('0', '1'),
            'opt_invFooterNote' => NULL,   // free text, clipped below
            'opt_invAccent'     => array('blue', 'slate', 'green', 'mono'),
            'opt_invCurrencyPos'=> array('totals', 'header'),
            'opt_invTemplate'   => array('creative', 'toner-save'),
        );

        $saved = 0;
        foreach ($fields as $key => $allowed) {
            if (!isset($_POST[$key])) {
                continue;
            }
            $value = (string) $_POST[$key];
            if ($allowed === NULL) {
                // a custom message: one paragraph, no markup
                $value = trim(strip_tags($value));
                if (function_exists('mb_substr')) {
                    $value = mb_substr($value, 0, 300, 'UTF-8');
                } else {
                    $value = substr($value, 0, 300);
                }
            } elseif (!in_array($value, $allowed, TRUE)) {
                continue;   // ignore anything not on the list rather than store it
            }
            $this->SettingsModel->setValueStore($key, $value);
            $saved++;
        }

        $this->saveHistory('Update invoice layout (' . $saved . ' settings)');
        $this->session->set_flashdata('resultAction', 'Layout saved');
        redirect(lang_url('user/settings/invoices') . '#template');
    }

    /*
     * Live preview: the same markup the document uses, rendered from the posted
     * values. Ajax only, and it never writes anything - the preview is what the
     * form currently says, not what is stored.
     */

    public function preview()
    {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }

        $layout = array();
        $keys = array('opt_invLogoPos' => 'logoPos', 'opt_invAddressPos' => 'addressPos',
            'opt_invShowLogo' => 'showLogo', 'opt_invShowAddress' => 'showAddress',
            'opt_invShowContacts' => 'showContacts', 'opt_invShowFirmVat' => 'showFirmVat',
            'opt_invShowRemarks' => 'showRemarks', 'opt_invFooterNote' => 'footerNote',
            'opt_invAccent' => 'accent', 'opt_invCurrencyPos' => 'currencyPos',
            'opt_invTemplate' => 'template');

        foreach ($keys as $post => $name) {
            $layout[$name] = isset($_POST[$post]) ? (string) $_POST[$post] : '';
        }

        /*
         * A toggle the form posts as "0" is off; a toggle it does not post at
         * all is also off. Normalising here means the preview and the stored
         * layout agree on what "absent" means instead of each guessing.
         */
        foreach (array('showLogo', 'showAddress', 'showContacts', 'showFirmVat', 'showRemarks') as $toggle) {
            $layout[$toggle] = $layout[$toggle] === '1' ? '1' : '0';
        }

        $firm = $this->SettingsModel->getFirmForPreview();
        $this->load->view('settings/invoice_preview', array('layout' => $layout, 'firm' => $firm));
    }

}
