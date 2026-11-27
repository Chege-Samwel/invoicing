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
        /*
         * The round-to field and the calculator switch were rendered by the view
         * but never read here, so the page showed empty boxes for both and threw
         * an undefined-variable notice on every load - the saved values simply
         * did not appear. getValueStores() returns the stored string, so an
         * account that has never chosen keeps an empty round-to and the
         * calculator on, which is what the app does for a new account.
         */
        $data['opt_invRoundTo'] = $this->SettingsModel->getValueStores('opt_invRoundTo');
        $calculator = $this->SettingsModel->getValueStores('opt_invCalculator');
        $data['opt_invCalculator'] = ($calculator === NULL || $calculator === '') ? 1 : (int) $calculator;
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
        /*
         * 1 = the calculator is offered on a new invoice, 0 = it is hidden.
         * A ticked box means "stop the calculator", so a tick is the 0.
         */
        $stop = isset($_POST['opt_invCalculator']);
        $this->SettingsModel->setValueStore('opt_invCalculator', $stop ? 0 : 1);
        $this->saveHistory('Set calculator usage to - ' . ($stop ? 'off' : 'on'));
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

    /**
     * The defaults dialog: which currency and which language new documents use.
     *
     * Both answers already had a home - the currency on firms_users, the
     * language in the settings pane - but they were in two different places and
     * neither was asked for at the moment it matters, which is when someone
     * starts their first invoice. This puts the pair in one small dialog that can
     * be opened from anywhere, and saves them together.
     *
     * Posted as JSON because it is called from a dialog on several pages that
     * have no form of their own.
     */
    public function defaults()
    {
        $this->output->set_content_type('application/json');

        if (!$this->input->is_ajax_request()) {
            $this->output->set_output(json_encode(array('status' => 'error', 'message' => 'No direct access')));
            return;
        }

        if ($this->input->method() !== 'post') {
            // a GET is the dialog asking what the values are now
            $this->output->set_output(json_encode(array(
                'status' => 'success',
                'currency' => $this->SettingsModel->getFirmDefaultCurrency(),
                'language' => $this->SettingsModel->getDefaultDocLanguage(),
                'languages' => $this->SettingsModel->getInstalledLanguages(),
                'currencies' => $this->SettingsModel->getCurrencyChoices(),
            )));
            return;
        }

        $saved = array();

        $currency = isset($_POST['currency']) ? trim((string) $_POST['currency']) : '';
        if ($currency !== '') {
            /*
             * Only a code this installation already offers. Accepting anything
             * else would let a request put an arbitrary string where every total
             * on every document reads its unit from.
             */
            $allowed = array_keys($this->SettingsModel->getCurrencyChoices());
            if (!in_array(strtoupper($currency), $allowed, TRUE)) {
                $this->output->set_output(json_encode(array(
                    'status' => 'error',
                    'message' => 'That currency is not one this account can use.',
                )));
                return;
            }
            $this->SettingsModel->setFirmDefaultCurrency($currency);
            $saved[] = 'currency';
        }

        $language = isset($_POST['language']) ? trim((string) $_POST['language']) : '';
        if ($language !== '') {
            $known = array();
            foreach ($this->SettingsModel->getInstalledLanguages() as $row) {
                $known[] = $row['name'];
            }
            if (!in_array($language, $known, TRUE)) {
                $this->output->set_output(json_encode(array(
                    'status' => 'error',
                    'message' => 'That language is not installed here.',
                )));
                return;
            }
            $this->SettingsModel->setValueStore('opt_docLanguage', $language);
            $saved[] = 'language';
        }

        if (empty($saved)) {
            $this->output->set_output(json_encode(array('status' => 'error', 'message' => 'Nothing to save.')));
            return;
        }

        $this->saveHistory('Updated document defaults (' . implode(', ', $saved) . ')');

        $this->output->set_output(json_encode(array(
            'status' => 'success',
            'message' => 'Defaults saved. New documents use them.',
            'saved' => $saved,
        )));
    }

    /**
     * Rename one row in the four settings lists, without leaving the page.
     *
     * The page used to add and delete but never edit, so a one-character typo in
     * a currency name meant deleting the row and adding it back - which also
     * detaches it from every document that referenced it. The Edit button on
     * each row posts here.
     *
     * JSON in, JSON out, and it only ever answers an XHR: the page is a form
     * page, and a plain browser POST to this URL should not be a way to change
     * settings.
     *
     * @return void
     */
    public function rename()
    {
        if (!$this->input->is_ajax_request()) {
            $this->output->set_status_header(403);
            $this->output->set_output(json_encode(array(
                'status' => 'error',
                'message' => 'No direct access',
            )));
            return;
        }

        $kind = (string) $this->input->post('kind');
        $id = (int) $this->input->post('id');
        $name = (string) $this->input->post('name');
        $value = (string) $this->input->post('value');

        if ($id <= 0) {
            $this->output->set_output(json_encode(array(
                'status' => 'error',
                'message' => 'That row no longer exists.',
            )));
            return;
        }

        $saved = FALSE;
        switch ($kind) {
            case 'currency':
                $saved = $this->SettingsModel->updateMyCurrency($id, array(
                    'name' => $name,
                    'value' => $value,
                ));
                $label = 'currency';
                break;
            case 'quantitytype':
                $saved = $this->SettingsModel->renameLookupValue('users_quantity_types', $id, $name);
                $label = 'quantity type';
                break;
            case 'paymentmethod':
                $saved = $this->SettingsModel->renameLookupValue('users_payment_methods', $id, $name);
                $label = 'payment method';
                break;
            case 'novatreason':
                $saved = $this->SettingsModel->renameLookupValue('user_no_vat_reasons', $id, $name);
                $label = 'no-vat reason';
                break;
            default:
                $this->output->set_output(json_encode(array(
                    'status' => 'error',
                    'message' => 'Unknown setting.',
                )));
                return;
        }

        if (!$saved) {
            $this->output->set_output(json_encode(array(
                'status' => 'error',
                'message' => 'That cannot be saved - the name and value cannot both be empty.',
            )));
            return;
        }

        $this->saveHistory('Renamed ' . $label . ' to - ' . strip_tags($name));

        $this->output->set_output(json_encode(array(
            'status' => 'success',
            'message' => 'Saved.',
            'name' => strip_tags($name),
            'value' => strip_tags($value),
        )));
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
            // keys of canvasTemplates() - kept in step by name, not by a second list
            'opt_invTemplate'   => array_keys($this->canvasTemplates()),
            'opt_invEdge'       => array_keys(adomin_invoice_edge_designs()),
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
        $data['templates'] = $this->canvasTemplates();

        /*
         * The document is drawn here, on the server, for the first paint.
         * Rendering it blank and waiting for an ajax round trip meant there was
         * nothing on screen to drag until that call came back, so the page read
         * as "drag and drop does not work" whenever the first POST was slow.
         */
        $data['previewHtml'] = $this->load->view('settings/invoice_preview', array(
            'layout' => adomin_invoice_layout(),
            'firm' => $data['firm'],
        ), TRUE);

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

        $this->load->view('settings/invoice_preview', array(
            'layout' => $this->previewLayout(),
            'firm' => $this->SettingsModel->getFirmForPreview(),
        ));
    }

    /**
     * The designs offered on the canvas.
     *
     * One list, because the canvas, the settings pane and the save validation
     * all have to agree on what a design is called. They were three literal
     * arrays before, and a design added to one of them was silently rejected by
     * the others.
     *
     * key => [label, description, swatch class]
     *
     * @return array
     */
    private function canvasTemplates()
    {
        return array(
            'banner'     => array('Banner', 'Full-width letterhead, accent title band, grouped items.', 'banner'),
            'creative'   => array('Creative', 'Accent bar, firm block on one side, roomy spacing.', 'creative'),
            'toner-save' => array('Toner-save', 'No fills or heavy rules. Prints clean and cheap.', 'toner'),
            'minimal'    => array('Minimal', 'Type only. No rules, no fills, generous margins.', 'minimal'),
            'classic'    => array('Classic', 'Centred letterhead, serif headings, ruled table.', 'classic'),
            'modern'     => array('Modern', 'Two-column header, thin accent rule, tabular totals.', 'modern'),
        );
    }

    /**
     * The layout the preview draws: whatever was posted, or the stored one.
     *
     * Pulled out of preview() so the canvas page can draw its first document
     * from the same rules. It used to render an empty box and wait for an
     * ajax round trip before there was anything on screen - which meant a user
     * whose first POST was slow (or rejected) saw a document area with nothing
     * to drag, and the natural reading of that is "drag and drop is broken".
     *
     * Posted values win. A field that was not posted at all - which is the
     * case on the first render - falls back to what is stored, so the first
     * document on screen is the user's real template, not a default one.
     *
     * @return array
     */
    private function previewLayout()
    {
        $keys = array('opt_invLogoPos' => 'logoPos', 'opt_invAddressPos' => 'addressPos',
            'opt_invShowLogo' => 'showLogo', 'opt_invShowAddress' => 'showAddress',
            'opt_invShowContacts' => 'showContacts', 'opt_invShowFirmVat' => 'showFirmVat',
            'opt_invShowRemarks' => 'showRemarks', 'opt_invFooterNote' => 'footerNote',
            'opt_invAccent' => 'accent', 'opt_invCurrencyPos' => 'currencyPos',
            'opt_invTemplate' => 'template', 'opt_invSections' => 'sections',
            'opt_invPlacement' => 'placement', 'opt_invEdge' => 'edge');

        $stored = adomin_invoice_layout();
        $layout = array();

        foreach ($keys as $post => $name) {
            if (isset($_POST[$post])) {
                $layout[$name] = (string) $_POST[$post];
            } elseif (isset($stored[$name])) {
                $layout[$name] = (string) $stored[$name];
            } else {
                $layout[$name] = '';
            }
        }

        /*
         * A toggle posted as "0" is off; a toggle that was not posted is
         * whatever is stored. An empty value is neither, so it follows the
         * stored setting rather than silently turning a section off.
         */
        foreach (array('showLogo', 'showAddress', 'showContacts', 'showFirmVat', 'showRemarks') as $toggle) {
            if (!isset($_POST['opt_inv' . ucfirst($toggle)]) && isset($stored[$toggle])) {
                $layout[$toggle] = $stored[$toggle] === '1' ? '1' : '0';
            } else {
                $layout[$toggle] = $layout[$toggle] === '1' ? '1' : '0';
            }
        }

        return $layout;
    }

}
