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
        $allowed = array('creative', 'toner-save', 'banner');
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
            'opt_invTemplate'   => array('creative', 'toner-save', 'banner'),
            'opt_invEdge'       => array('none', 'wave', 'rule', 'corner', 'frame', 'chevron', 'arc'),
        );

        $saved = 0;

        /*
         * The block order: a comma list of the blocks that actually exist. It
         * is validated as a set rather than a single value, because a partial
         * or reordered list is legitimate while an unknown block is not.
         */
        if (isset($_POST['opt_invSections'])) {
            $known = array('header', 'items', 'totals', 'signature', 'remarks');
            $order = array();
            foreach (explode(',', (string) $_POST['opt_invSections']) as $name) {
                $name = trim($name);
                if (in_array($name, $known, TRUE) && !in_array($name, $order, TRUE)) {
                    $order[] = $name;
                }
            }
            if (!empty($order)) {
                $this->SettingsModel->setValueStore('opt_invSections', implode(',', $order));
                $saved++;
            }
        }

        /*
         * Component positions come from the canvas: "logo=x,y,w,h,size,face" for
         * each piece, fractions of the page. It is normalised rather than
         * rejected, because a half-finished value from a browser that closed
         * mid-drag should still save the components it did place - but nothing
         * outside the allowed keys or ranges is kept, so it can never put text
         * off the paper or smuggle markup into a style attribute.
         */
        if (isset($_POST['opt_invPlacement'])) {
            $placement = $this->cleanPlacement((string) $_POST['opt_invPlacement']);
            if ($placement !== '') {
                $this->SettingsModel->setValueStore('opt_invPlacement', $placement);
                $saved++;
            }
        }

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

    /**
     * Normalise the canvas's component positions.
     *
     * Input is "key=x,y,w,h,size,face;key=..." with x/y/w/h as fractions of the
     * page. Unknown components are dropped, numbers are clamped into the page,
     * and the font face is reduced to letters and spaces - it ends up inside a
     * style attribute on a printed document, so it cannot be allowed to carry
     * anything else.
     *
     * @param   string  $raw
     * @return  string
     */
    private function cleanPlacement($raw)
    {
        $known = array('logo', 'firm', 'address', 'docno');
        $out = array();

        foreach (explode(';', $raw) as $chunk) {
            $parts = explode('=', $chunk, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $key = trim($parts[0]);
            if (!in_array($key, $known, TRUE)) {
                continue;
            }

            $nums = explode(',', trim($parts[1]));
            if (count($nums) < 6) {
                continue;
            }

            $box = array();
            for ($i = 0; $i < 4; $i++) {
                $value = (float) $nums[$i];
                $box[] = round($value < 0 ? 0 : ($value > 1 ? 1 : $value), 3);
            }

            $size = (int) $nums[4];
            $size = $size < 6 ? 0 : ($size > 48 ? 48 : $size);

            $face = substr(preg_replace('/[^A-Za-z0-9 \-]/', '', trim($nums[5])), 0, 40);

            $out[] = $key . '=' . implode(',', $box) . ',' . $size . ',' . $face;
        }

        // one entry per component: the last placement of a key is the one meant
        $seen = array();
        $result = array();
        foreach (array_reverse($out) as $line) {
            $key = substr($line, 0, strpos($line, '='));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = TRUE;
            array_unshift($result, $line);
        }

        return implode(';', $result);
    }

    /*
     * The template canvas.
     *
     * One page holding every decision an invoice document needs: which design,
     * what the blocks are and in what order, which parts of the letterhead are
     * shown, the logo, the accent and the message. It saves in place and
     * previews from the same rules the printed document uses.
     */
    public function template()
    {
        $data = array();
        $head = array();
        $head['title'] = 'Invoice template';

        if (isset($_POST['saveCanvas'])) {
            $this->saveInvoiceLayout();
            return;
        }

        $data['layout'] = adomin_invoice_layout();
        $data['firm'] = $this->SettingsModel->getFirmForPreview();
        $data['templates'] = array(
            'creative'   => array('Creative', 'Accent bar, firm block on one side, roomy spacing.'),
            'toner-save' => array('Toner-save', 'No fills or heavy rules. Prints clean and cheap.'),
            'banner'     => array('Banner', 'Letterhead, accent title band, grouped items, contact strip.'),
        );

        $this->render('settings/invoice_template', $head, $data);
    }

    /*
     * Logo upload, called from the canvas.
     *
     * Replaces the company's logo for the selected firm. Validated by content
     * rather than by the name the browser sent, and stored where the invoice and
     * the PDF already look for it (attachments/companiesimages/<firm>/), so the
     * upload has one home instead of a second path that the documents ignore.
     */
    public function uploadLogo()
    {
        $this->output->set_content_type('application/json');

        if (!$this->input->is_ajax_request()) {
            $this->output->set_output(json_encode(array('status' => 'error', 'message' => 'No direct access')));
            return;
        }

        if (empty($_FILES['logo']['tmp_name']) || !is_uploaded_file($_FILES['logo']['tmp_name'])) {
            $this->output->set_output(json_encode(array('status' => 'error', 'message' => 'No file arrived. Choose an image and try again.')));
            return;
        }

        $info = @getimagesize($_FILES['logo']['tmp_name']);
        $allowed = array(IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp');
        if ($info === FALSE || !isset($allowed[$info[2]])) {
            $this->output->set_output(json_encode(array('status' => 'error', 'message' => 'That file is not a PNG, JPEG, GIF or WebP image.')));
            return;
        }

        // 2 MB: a logo larger than this is a screenshot that has not been cropped
        if (filesize($_FILES['logo']['tmp_name']) > 2 * 1024 * 1024) {
            $this->output->set_output(json_encode(array('status' => 'error', 'message' => 'That image is over 2 MB. Resize it first.')));
            return;
        }

        $firmId = (int) SELECTED_COMPANY_ID;
        $folder = FCPATH . 'attachments/' . COMPANIES_IMAGES_DIR . '/' . $firmId;
        if (!is_dir($folder) && !@mkdir($folder, 0777, TRUE)) {
            $this->output->set_output(json_encode(array('status' => 'error', 'message' => 'Could not create the folder for the image.')));
            return;
        }

        // a fixed name per firm: no user-supplied filename ever reaches the disk
        $name = 'logo.' . $allowed[$info[2]];
        $target = $folder . '/' . $name;
        if (!@move_uploaded_file($_FILES['logo']['tmp_name'], $target)) {
            $this->output->set_output(json_encode(array('status' => 'error', 'message' => 'Could not save the image.')));
            return;
        }
        @chmod($target, 0644);

        /*
         * Point the firm at it. firms_translations.image is what the invoice and
         * the PDF already read, so the upload appears in both without either
         * template learning anything new.
         */
        $this->db->where('for_firm', $firmId);
        $this->db->update('firms_translations', array('image' => $name));

        $this->saveHistory('Uploaded a company logo');

        $this->output->set_output(json_encode(array(
            'status' => 'success',
            'message' => 'Logo saved. It appears on every invoice from now on.',
            'url' => base_url('attachments/' . COMPANIES_IMAGES_DIR . '/' . $firmId . '/' . $name) . '?v=' . time(),
        )));
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
            'opt_invTemplate' => 'template', 'opt_invSections' => 'sections',
            'opt_invPlacement' => 'placement', 'opt_invEdge' => 'edge');

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
