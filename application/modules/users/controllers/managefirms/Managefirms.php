<?php

/*
 * @Author:    Kiril Kirkov
 *  Github:    https://github.com/kirilkirkov
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Managefirms extends USER_Controller
{

    private $myFirms;

    public function __construct()
    {
        parent::__construct();
        $this->load->model(array('ManagefirmsModel', 'HomeModel'));
    }

    public function index()
    {
        $data = array();
        $head = array();
        $head['title'] = lang('title_everytime') . lang('title_manage_firms');
        $data['firms'] = $this->myFirms = $this->HomeModel->getFirms();
        if (isset($_POST['firm_name'])) {
            if (count($this->myFirms) < $this->planUnits['num_firms']) { // if num my firms is lower than my plan num allowed
                $result = $this->validateCompanyDetails();
                if ($result === true) {
                    $companyId = $this->setFirm();
                    $this->addCompanyFolders($companyId);
                    $this->offerStarterProducts($companyId);
                    $this->saveHistory('Add company - ' . print_r($_POST, true));
                    $this->session->set_flashdata('resultAction', lang('company_added'));
                } else {
                    $this->session->set_flashdata('addFirm', '1');
                    $this->session->set_flashdata('resultAction', $result);
                }
                redirect(lang_url('user/managefirms'));
            }
        }
        $this->render('managefirms/index', $head, $data);
        $this->saveHistory('Go to manage firms page');
    }

    public function deleteCompany($companyId)
    {
        if (!in_array($companyId, $this->canUseFirms)) {
            log_message('error', 'User with id - ' . USER_ID . ' get 404 when try to delete company with id -' . $companyId);
            show_404();
        }
        $this->ManagefirmsModel->deleteCompany($companyId);
        if ($companyId == SELECTED_COMPANY_ID) {
            $lastAddedCompanyId = $this->ManagefirmsModel->getLastAddedCompanyId();
            if ($lastAddedCompanyId != null) {
                redirect(lang_url('user/usecompany/' . $lastAddedCompanyId));
            }
        }
        $this->session->set_flashdata('resultAction', lang('company_deleted'));
        $this->saveHistory('Delete company id - ' . $companyId);
        redirect(lang_url('user/managefirms'));
    }

    public function editCompany($companyId, $translateId = 0)
    {
        $data = array();
        $head = array();
        $head['title'] = lang('title_everytime') . lang('title_edit_firm');
        if (!in_array($companyId, $this->canUseFirms)) {
            log_message('error', 'User with id - ' . USER_ID . ' get 404 when try to edit company with id -' . $companyId . ' No permissions!');
            show_404();
        }
        $result = $this->getCompanyInfo($companyId);
        if (empty($result['company']) || !is_numeric($companyId)) {
            log_message('error', 'User with id - ' . USER_ID . ' get 404 when try to edit company with id -' . $companyId . ' Not found this id or is not numeric searched for id');
            show_404();
        }
        if ($translateId > 0) {
            $companyTranslate = $this->ManagefirmsModel->getTranslationInfo($translateId, $companyId);
            if (empty($companyTranslate)) {
                show_404();
            }
            $data['companyTranslate'] = $companyTranslate;
        } else {
            $data['companyTranslate'] = $this->ManagefirmsModel->getMyCompanyDefaultTranslation($companyId);
        }
        /*
         * Industry, and the starter products that come with it.
         *
         * Two separate actions, deliberately: changing the trade does not touch
         * the item list (an account that has been trading for a year must not
         * have its list rewritten because the answer was corrected), while
         * "add these products" copies the trade's list in and never removes
         * anything. Both go through AdominIndustry, which skips names that are
         * already there, so pressing the button twice is harmless.
         */
        $this->load->library('AdominIndustry');
        if (isset($_POST['save_industry'])) {
            $industry = isset($_POST['industry']) ? trim((string) $_POST['industry']) : '';
            if ($this->adominindustry->isValid($industry)) {
                $this->adominindustry->remember(USER_ID, $industry);
                $this->addProductsToCompany($companyId);
                $this->saveHistory('Set industry to ' . $industry . ' for company ' . $companyId);
                $this->session->set_flashdata('resultAction', lang('industry_saved'));
            } else {
                $this->session->set_flashdata('resultAction', lang('industry_invalid'));
            }
            redirect(lang_url('user/managefirms/edit/' . $companyId));
        }
        if (isset($_POST['add_industry_products'])) {
            $industry = isset($_POST['industry']) ? trim((string) $_POST['industry']) : '';
            if ($this->adominindustry->isValid($industry)) {
                $this->addProductsToCompany($companyId, $industry);
                $this->saveHistory('Added ' . $industry . ' products to company ' . $companyId);
                $this->session->set_flashdata('resultAction', lang('industry_products_added'));
            }
            redirect(lang_url('user/managefirms/edit/' . $companyId));
        }

        $data['industries'] = $this->adominindustry->options();
        $data['industryCurrent'] = $this->adominindustry->forUser(USER_ID);
        $data['industryProducts'] = $this->adominindustry->products($data['industryCurrent']);
        $data['companyItemCount'] = $this->ManagefirmsModel->countCompanyItems($companyId);

        /*
         * Save Bulstat Data
         */
        if (isset($_POST['firm_bulstat'])) {
            $result = $this->updateCompanyStaticInfo($companyId);
            if ($result === false) {
                $this->session->set_flashdata('resultAction', lang('bulstat_is_taken'));
            } else {
                $this->saveHistory('Update firm details for Id - ' . $companyId);
                $this->session->set_flashdata('resultAction', lang('firm_details_changed'));
            }
            redirect(lang_url('user/managefirms/edit/' . $companyId));
        }
        /*
         * Save Translation texts
         */
        if (isset($_POST['saveTranslate'])) {
            $this->updateTranslation($companyId);
            $this->saveHistory('Update translation for company Id - ' . $companyId);
            $this->session->set_flashdata('resultAction', lang('translation_updated'));
            redirect(lang_url('user/managefirms/edit/' . $companyId . '/' . $translateId));
        }
        /*
         * Add new translation
         */
        if (isset($_POST['add_new_translation'])) {
            $result = $this->addNewTranslation($companyId);
            if ($result === true) {
                $this->saveHistory('Add new translation for company Id - ' . $companyId);
                $this->session->set_flashdata('resultAction', lang('new_translation_added'));
            } else {
                $this->session->set_flashdata('addNewTranslationErr', '1');
                $this->session->set_flashdata('resultAction', $result);
            }
            redirect(lang_url('user/managefirms/edit/' . $companyId));
        }
        $data['companyInfo'] = $result;
        $this->render('managefirms/edit', $head, $data);
        $this->saveHistory('Go to edit firms id - ' . $companyId);
    }

    public function deleteTranslation($companyId, $translationid)
    {
        $this->ManagefirmsModel->deleteTranslation($companyId, $translationid);
        $this->session->set_flashdata('resultAction', lang('translation_deleted'));
        $this->saveHistory('Delete tanslation id - ' . $translationid);
        redirect(lang_url('user/managefirms/edit/' . $companyId));
    }

    public function makeDefaultTranslation($companyId, $translationId)
    {
        $this->ManagefirmsModel->makeDefaultTranslationWithId($companyId, $translationId);
        $this->saveHistory('Make default tanslation id - ' . $translationid);
        redirect(lang_url('user/managefirms/edit/' . $companyId));
    }

    public function makeDefaultFirm($firmId)
    {
        $this->ManagefirmsModel->makeDefaultFirmWithId($firmId);
        $this->saveHistory('Make default company id - ' . $firmId);
        redirect(lang_url('user/managefirms'));
    }

    private function addNewTranslation($companyId)
    {
        $errors = $this->validateCompanyDetails(false);
        $img = uploader('./attachments/' . COMPANIES_IMAGES_DIR . '/' . $companyId);
        if (isset($img['result']) && $img['result'] === true) {
            $_POST['image'] = $img['value'];
        } elseif (!isset($img['result']) && $img === false) {
            $_POST['image'] = '';
        } elseif (isset($img['result']) && $img['result'] === false) {
            $errors[] = lang('upload_img_error') . $img['value'];
        }
        if ($errors === true) {
            $this->ManagefirmsModel->setNewTranslation($_POST, $companyId);
            return true;
        } else {
            return $errors;
        }
    }

    private function updateTranslation($companyId)
    {
        $errors = $this->validateCompanyDetails(false);
        $img = uploader('./attachments/' . COMPANIES_IMAGES_DIR . '/' . $companyId);
        if (isset($img['result']) && $img['result'] === true) {
            $_POST['image'] = $img['value'];
        } elseif (!isset($img['result']) && $img === false) {
            $_POST['image'] = $_POST['old_image'];
        } elseif (isset($img['result']) && $img['result'] === false) {
            $errors[] = lang('upload_img_error') . $img['value'];
        }
        if ($errors === true) {
            $result = $this->ManagefirmsModel->updateTranslation($_POST, $companyId);
            return $result;
        } else {
            return $errors;
        }
    }

    private function updateCompanyStaticInfo($companyId)
    {
        $result = $this->HomeModel->checkBulstatIsFree($_POST['firm_bulstat'], $companyId);
        if ($result === true) {
            $this->ManagefirmsModel->updateCompanyStaticInfo($_POST, $companyId);
        }
        return $result;
    }

    private function getCompanyInfo($companyId)
    {
        $result = $this->ManagefirmsModel->getCompanyInfo($companyId);
        return $result;
    }

    private function setFirm()
    {
        $_POST['is_default'] = 0;
        $companyId = $this->HomeModel->setFirm($_POST);
        return $companyId;
    }

    /**
     * Fill the new company's item list from the trade given at registration.
     *
     * Registration has no company to seed into yet - the schema wants a KRA pin
     * and a name - so the list is filled the moment the first one is created.
     * AdominIndustry::seedPending() is idempotent and marks the account, which
     * is what keeps a later company from being seeded again: an account that
     * trimmed the starter list must not find it refilled just because it opened
     * a second entity.
     *
     * @param  int $companyId
     * @return void
     */
    /**
     * Copy one trade's products into this company's item list.
     *
     * @param  int         $companyId
     * @param  string|null $industry  defaults to the account's saved trade
     * @return int         rows added
     */
    private function addProductsToCompany($companyId, $industry = NULL)
    {
        if ($industry === NULL) {
            $industry = $this->adominindustry->forUser(USER_ID);
        }

        $added = $this->adominindustry->add(USER_ID, (int) $companyId, $industry);
        $this->session->set_flashdata('starterProductsAdded', $added);

        return $added;
    }

    private function offerStarterProducts($companyId)
    {
        $this->load->library('AdominIndustry');
        $added = $this->adominindustry->seedPending(USER_ID);
        if ($added > 0) {
            $this->session->set_flashdata('starterProductsAdded', $added);
        }
    }

}
