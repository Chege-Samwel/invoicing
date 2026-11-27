<?php

class SettingsModel extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
        $this->getFirmsIdsForUser();
    }

    public function getMyFirmsDefaultCurrency()
    {
        $this->db->select('firms_users.id, default_currency, name');
        $this->db->where('for_user', USER_ID);
        $this->db->where('is_deleted', 0);
        $this->db->where('firms_translations.is_default', 1);
        $this->db->join('firms_translations', 'firms_translations.for_firm = firms_users.id');
        $result = $this->db->get('firms_users');
        return $result->result_array();
    }

    /*
     * The firm details a layout preview draws with.
     *
     * The preview must not invent a company: showing "Your Company Ltd" would
     * hide the case where the real name is long enough to wrap the header, and
     * that is exactly what the preview exists to show. Falls back to neutral
     * sample text only when the account has no company yet.
     */

    public function getFirmForPreview()
    {
        $firm = array(
            'name' => 'Your company',
            'bulstat' => 'P000000000X',
            'vat_number' => '',
            'is_vat_registered' => 0,
            'address' => 'Your address',
            'city' => 'Your city',
            'accountable_person' => '',
            'image' => '',
            'default_currency' => 'KES',
        );

        /*
         * Where each value lives: the registration number and VAT flag are on
         * firms_users, while the name, address, city, contact person and logo
         * are on firms_translations - one row per language. Selecting the
         * address from firms_users (where it does not exist) made this query
         * fail, which is what broke the layout preview outright.
         */
        $this->db->select('firms_users.id, firms_users.bulstat, firms_users.vat_number,
                           firms_users.is_vat_registered, firms_users.default_currency,
                           firms_translations.name, firms_translations.address,
                           firms_translations.city, firms_translations.mol,
                           firms_translations.image');
        $this->db->from('firms_users');
        $this->db->join('firms_translations', 'firms_translations.for_firm = firms_users.id');
        $this->db->where('firms_users.for_user', USER_ID);
        $this->db->where('firms_users.is_deleted', 0);
        $this->db->where('firms_translations.is_default', 1);
        $this->db->limit(1);
        $row = $this->db->get()->row_array();

        if (is_array($row) && !empty($row)) {
            $map = array(
                'name' => 'name',
                'bulstat' => 'bulstat',
                'vat_number' => 'vat_number',
                'is_vat_registered' => 'is_vat_registered',
                'address' => 'address',
                'city' => 'city',
                'mol' => 'accountable_person',
                'image' => 'image',
                'default_currency' => 'default_currency',
            );
            foreach ($map as $column => $key) {
                if (isset($row[$column]) && $row[$column] !== NULL && $row[$column] !== '') {
                    $firm[$key] = $row[$column];
                }
            }
            $firm['id'] = $row['id'];
        }

        return $firm;
    }

    public function setNewDefaultCurrency($post)
    {
        $this->db->where('id', $post['forId']);
        $this->db->where('for_user', USER_ID);
        if (!$this->db->update('firms_users', array('default_currency' => $post['newDefault']))) {
            log_message('error', print_r($this->db->error(), true));
            show_error(lang('database_error'));
        }
    }

    public function deleteDefaultCurrency($id)
    {
        $this->db->where('id', $id);
        $this->db->where('for_user', USER_ID);
        if (!$this->db->update('firms_users', array('default_currency' => ''))) {
            log_message('error', print_r($this->db->error(), true));
            show_error(lang('database_error'));
        }
    }

    public function setNewCurrency($post)
    {
        if (!$this->db->insert('users_currencies', array(
                    'for_user' => USER_ID,
                    'name' => htmlspecialchars($post['currencyName']),
                    'value' => htmlspecialchars($post['currencyValue']
            )))) {
            log_message('error', print_r($this->db->error(), true));
            show_error(lang('database_error'));
        }
    }

    public function getMyCurrencies()
    {
        $this->db->where('for_user', USER_ID);
        $result = $this->db->get('users_currencies');
        return $result->result_array();
    }

    public function deleteMyCurrency($id)
    {
        $this->db->where('id', $id);
        $this->db->where('for_user', USER_ID);
        if (!$this->db->delete('users_currencies')) {
            log_message('error', print_r($this->db->error(), true));
            show_error(lang('database_error'));
        }
    }

    public function getMyQuantityTypes()
    {
        $this->db->where('for_user', USER_ID);
        $result = $this->db->get('users_quantity_types');
        return $result->result_array();
    }

    public function deleteCustomQuantityType($id)
    {
        $this->db->where('id', $id);
        $this->db->where('for_user', USER_ID);
        if (!$this->db->delete('users_quantity_types')) {
            log_message('error', print_r($this->db->error(), true));
            show_error(lang('database_error'));
        }
    }

    public function getMyPaymentMethods()
    {
        $this->db->where('for_user', USER_ID);
        $result = $this->db->get('users_payment_methods');
        return $result->result_array();
    }

    public function deleteCustomPaymentMethod($id)
    {
        $this->db->where('id', $id);
        $this->db->where('for_user', USER_ID);
        if (!$this->db->delete('users_payment_methods')) {
            log_message('error', print_r($this->db->error(), true));
            show_error(lang('database_error'));
        }
    }

    public function getMyNoVatReasons()
    {
        $this->db->where('for_user', USER_ID);
        $result = $this->db->get('user_no_vat_reasons');
        return $result->result_array();
    }

    public function deleteMyNoVatReason($id)
    {
        $this->db->where('id', $id);
        $this->db->where('for_user', USER_ID);
        if (!$this->db->delete('user_no_vat_reasons')) {
            log_message('error', print_r($this->db->error(), true));
            show_error(lang('database_error'));
        }
    }

    /**
     * Store one setting for the signed-in user.
     *
     * The existence check used to look at `_key` alone while the update was
     * scoped to the user, so once any account had a row for a key every other
     * account skipped the insert and updated zero rows - their setting silently
     * never saved. The check and the write have to ask the same question.
     *
     * @param   string  $key
     * @param   string  $value
     * @return  void
     */
    public function setValueStore($key, $value)
    {
        $this->setValueStoreForUser($key, $value, USER_ID);
    }

    /**
     * The same, for an explicit account - used by callers that write on behalf
     * of a firm's owner rather than whoever is browsing.
     *
     * @param   string  $key
     * @param   string  $value
     * @param   int     $userId
     * @return  void
     */
    public function setValueStoreForUser($key, $value, $userId)
    {
        $value = htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
        $userId = (int) $userId;

        $this->db->where('_key', $key);
        $this->db->where('for_user', $userId);
        $exists = $this->db->count_all_results('value_store') > 0;

        if ($exists) {
            $this->db->where('_key', $key);
            $this->db->where('for_user', $userId);
            $this->db->update('value_store', array('value' => $value));
            return;
        }

        $this->db->insert('value_store', array('for_user' => $userId, 'value' => $value, '_key' => $key));
    }

    public function getValueStores($key)
    {
        $this->db->where('_key', $key);
        $this->db->where('for_user', USER_ID);
        $query = $this->db->get('value_store');
        $result = $query->row_array();
        if (empty($result)) {
            return null;
        }
        return $result['value'];
    }

    /**
     * The currency codes this account can put on a document.
     *
     * users_currencies holds the account's own list (the settings pane adds and
     * removes rows there), and every firm has a default. A firm pointing at a
     * code that is no longer in the list is still worth offering, otherwise
     * removing a currency would silently strip the default from documents that
     * already used it.
     *
     * @return array  code => label
     */
    public function getCurrencyChoices()
    {
        $choices = array();

        foreach ($this->getMyCurrencies() as $row) {
            if (!empty($row['value'])) {
                $choices[$row['value']] = !empty($row['name']) ? $row['name'] : $row['value'];
            }
        }

        $firm = $this->getFirmForPreview();
        if (!empty($firm['default_currency']) && !isset($choices[$firm['default_currency']])) {
            $choices[$firm['default_currency']] = $firm['default_currency'];
        }

        if (empty($choices)) {
            $choices['KES'] = 'Kenyan Shilling';
        }

        ksort($choices);
        return $choices;
    }

    /**
     * The currency a new document starts with.
     *
     * @return string
     */
    public function getFirmDefaultCurrency()
    {
        $firm = $this->getFirmForPreview();
        return !empty($firm['default_currency']) ? $firm['default_currency'] : 'KES';
    }

    /**
     * Set the default currency for the firm the user is working in.
     *
     * Scoped by for_user as well as id: the id arrives from a form, and without
     * the second condition a request could set the default on another account's
     * firm.
     *
     * @param   string  $code
     * @return  bool
     */
    public function setFirmDefaultCurrency($code)
    {
        $this->db->where('id', (int) SELECTED_COMPANY_ID);
        $this->db->where('for_user', USER_ID);

        if (!$this->db->update('firms_users', array('default_currency' => strtoupper(trim((string) $code))))) {
            log_message('error', print_r($this->db->error(), true));
            return FALSE;
        }

        return TRUE;
    }

    /**
     * The languages this installation can render.
     *
     * Only rows whose files are actually present are offered: the `languages`
     * table can name an idiom that was never shipped, and choosing one would
     * leave the document falling back to English without saying so.
     *
     * @return array
     */
    public function getInstalledLanguages()
    {
        $rows = $this->db->order_by('name', 'asc')->get('languages')->result_array();
        $usable = array();

        foreach ($rows as $row) {
            if (empty($row['abbr']) || empty($row['name'])) {
                continue;
            }
            if (!is_file(APPPATH . 'language/' . $row['name'] . '/users_lang.php')) {
                continue;
            }
            $usable[] = array(
                'name' => $row['name'],
                'abbr' => strtolower($row['abbr']),
            );
        }

        return $usable;
    }

    /**
     * The document language this account has chosen, or the site default.
     *
     * @return string  a `languages`.name value
     */
    public function getDefaultDocLanguage()
    {
        $stored = $this->getValueStores('opt_docLanguage');
        if ($stored !== NULL && $stored !== '') {
            return $stored;
        }

        return (string) $this->config->item('language');
    }

    public function countEmployees()
    {
        $this->db->where('for_user', USER_ID);
        return $this->db->count_all_results('employees');
    }

    public function getEmployees($limit, $page)
    {
        $this->db->where('for_user', USER_ID);
        $result = $this->db->get('employees', $limit, $page);
        return $result->result_array();
    }

    public function setEmployee($post)
    {
        $insertArray = array(
            'for_user' => USER_ID,
            'name' => htmlspecialchars(trim($post['name'])),
            'email' => htmlspecialchars(trim($post['email'])),
            'phone' => htmlspecialchars(trim($post['phone'])),
            'schiffer' => htmlspecialchars(trim($post['schiffer'])),
            'password' => password_hash($post['password'], PASSWORD_DEFAULT),
            'time_added' => time()
        );
        if (isset($post['firms'])) {
            $insertArray['firms_access'] = serialize($post['firms']);
        }
        if ($post['editId'] > 0) {
            if (mb_strlen(trim($post['password'])) == 0) {
                unset($insertArray['password']);
            }
            unset($insertArray['time_added']);
            if (!$this->db->where('id', $post['editId'])->where('for_user', USER_ID)->update('employees', $insertArray)) {
                log_message('error', print_r($this->db->error(), true));
                show_error(lang('database_error'));
            }
        } else {
            // if employee, add new employee he cant add access firms 
            // and we add this firm only to new employee
            if (!isset($insertArray['firms_access']) && defined('EMPLOYEE_ID')) {
                $insertArray['firms_access'] = serialize(array(SELECTED_COMPANY_ID));
            }
            if (!$this->db->insert('employees', $insertArray)) {
                log_message('error', print_r($this->db->error(), true));
                show_error(lang('database_error'));
            }
            return $this->db->insert_id();
        }
    }

    public function getFirmsIdsForUser()
    {
        $this->db->select('id');
        $this->db->where('for_user', USER_ID);
        $result = $this->db->get('firms_users');
        $str = '';
        foreach ($result->result_array() as $fr) {
            $str .= ',' . $fr['id'];
        }
        return explode(',', ltrim($str, ','));
    }

    public function getEmployeeInfo($id)
    {
        $this->db->where('id', $id);
        $this->db->where('for_user', USER_ID);
        $result = $this->db->get('employees');
        return $result->row_array();
    }

    public function checkEmployeeFreeEmail($email, $editId)
    {
        if ($editId > 0) {
            $this->db->where('employees.id !=', $editId);
        }
        $this->db->where('for_user', USER_ID);
        $this->db->where('email', $email);
        $num1 = $this->db->count_all_results('employees');

        $this->db->where('id', USER_ID);
        $this->db->where('email', $email);
        $num2 = $this->db->count_all_results('users');
        $num = $num1 + $num2;
        if ($num > 0) {
            return false;
        }
        return true;
    }

    public function deleteEmployee($id)
    {
        $this->db->trans_begin();
        $this->db->where('id', $id);
        $this->db->where('for_user', USER_ID);
        if (!$this->db->delete('employees')) {
            log_message('error', print_r($this->db->error(), true));
        }
        $this->db->where('for_employee', $id);
        if (!$this->db->delete('employees_permissions')) {
            log_message('error', print_r($this->db->error(), true));
        }
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            show_error(lang('database_error'));
        } else {
            $this->db->trans_commit();
        }
    }

    public function setNewEmployeePermissions($employeeId, $defaultPermissions)
    {
        $insertArray = array();
        foreach ($defaultPermissions as $key => $value) {
            $insertArray[] = array(
                'for_employee' => $employeeId,
                'perm' => $key,
                'role' => $value
            );
        }
        if (!$this->db->insert_batch('employees_permissions', $insertArray)) {
            log_message('error', print_r($this->db->error(), true));
            show_error(lang('database_error'));
        }
    }

    public function getPermissions()
    {
        $this->db->where('for_employee', EMPLOYEE_ID);
        $result = $this->db->get('employees_permissions');
        return $result->result_array();
    }

    public function getEmployeePermissions($id)
    {
        $array = array();
        $this->db->select('perm, role');
        $this->db->where('for_employee', $id);
        $this->db->where('for_user', USER_ID);
        $this->db->join('employees', 'employees.id = employees_permissions.for_employee');
        $result = $this->db->get('employees_permissions');
        $arr = $result->result_array();
        foreach ($arr as $ar) {
            $array[$ar['perm']] = $ar['role'];
        }
        return $array;
    }

    public function updateEmployeePermissions($newPermissions, $employeeId)
    {
        $this->db->where('id', $employeeId);
        $this->db->where('for_user', USER_ID);
        if ($this->db->count_all_results('employees') === 0) {
            log_message('error', 'User ' . USER_ID . ' tried to update permissions for employee ' . $employeeId . ' they do not own');
            show_error(lang('database_error'));
            return;
        }
        foreach ($newPermissions as $key => $val) {
            $this->db->where('for_employee', $employeeId);
            $this->db->where('perm', $key);
            if (!$this->db->update('employees_permissions', array('role' => $val))) {
                log_message('error', print_r($this->db->error(), true));
                show_error(lang('database_error'));
            }
        }
    }

    public function getAdminInfo()
    {
        $this->db->where('id', USER_ID);
        $result = $this->db->get('users');
        return $result->row_array();
    }

    public function checkRegisteredUserFreeEmail($email)
    {
        $this->db->where('for_user', USER_ID);
        $this->db->where('email', $email);
        $num1 = $this->db->count_all_results('employees');

        $this->db->where('email', $email);
        $this->db->where('id !=', USER_ID);
        $num2 = $this->db->count_all_results('users');
        $num = $num1 + $num2;
        if ($num > 0) {
            return false;
        }
        return true;
    }

    public function updateUserAdminInfo($post)
    {
        $allowedFields = array('name', 'email', 'phone', 'schiffer');
        $updateData = array();
        foreach ($allowedFields as $field) {
            if (isset($post[$field])) {
                $updateData[$field] = htmlspecialchars(trim($post[$field]), ENT_QUOTES, 'UTF-8');
            }
        }
        if (mb_strlen(trim($post['password'] ?? '')) > 0) {
            $updateData['password'] = password_hash($post['password'], PASSWORD_DEFAULT);
        }
        $this->db->where('id', USER_ID);
        if (!$this->db->update('users', $updateData)) {
            log_message('error', print_r($this->db->error(), true));
            show_error(lang('database_error'));
        } else {
            $_SESSION['user_login']['email'] = $updateData['email'];
        }
    }

    public function setNewStore($storeName)
    {
        if (!$this->db->insert('stores', array(
                    'name' => $storeName,
                    'for_user' => USER_ID,
                    'for_company' => SELECTED_COMPANY_ID,
                    'created' => time()
                ))) {
            log_message('error', print_r($this->db->error(), true));
            show_error(lang('database_error'));
        }
    }

    public function checkStoreNameIsFree($storeName)
    {
        $this->db->where('id', USER_ID);
        $this->db->where('for_company', SELECTED_COMPANY_ID);
        $this->db->where('name', $storeName);
        $num = $this->db->count_all_results('stores');
        if ($num > 0) {
            return false;
        }
        return true;
    }

    public function deleteStore($id)
    {
        $this->db->where('id', $id);
        $this->db->where('id', USER_ID);
        $this->db->where('for_company', SELECTED_COMPANY_ID);
        if (!$this->db->update('stores', array('is_deleted' => 1))) {
            log_message('error', print_r($this->db->error(), true));
            show_error(lang('database_error'));
        }
    }

}
