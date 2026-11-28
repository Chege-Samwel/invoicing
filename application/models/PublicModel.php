<?php

class PublicModel extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    public function getOneLanguage($myLang)
    {
        $this->db->select('*');
        $this->db->where('abbr', $myLang);
        $result = $this->db->get('languages');
        return $result->row_array();
    }

    public function checkUserFreeEmail($email)
    {
        $this->db->where('enabled', 1);
        $this->db->where('email', $email);
        $num = $this->db->count_all_results('users');
        if ($num > 0) {
            return lang('registered_by_user');
        }
        return true;
    }

    public function registerUser($post)
    {
        $this->db->trans_begin();
        if (!$this->db->insert('users', array(
                    // name/phone are NOT NULL without a default on an
                    // unmodified schema, so they are always written explicitly;
                    // '' is the honest value when the form did not ask for them.
                    'name' => isset($post['name']) ? (string) $post['name'] : '',
                    'phone' => isset($post['phone']) ? (string) $post['phone'] : '',
                    // `schiffer` held the "replaces signature" label. It is no
                    // longer asked for and no longer printed, but the column is
                    // still NOT NULL without a default, so it is written empty
                    // rather than omitted - an INSERT that leaves it out fails
                    // outright under STRICT_TRANS_TABLES.
                    'schiffer' => '',
                    'email' => $post['email'],
                    'password' => password_hash($post['password'], PASSWORD_DEFAULT),
                    'ip_address' => isset($post['ip_address']) ? $post['ip_address'] : NULL,
                    'time_registered' => time()
                ))) {
            log_message('error', print_r($this->db->error(), true));
        }
        $user_id = $this->db->insert_id();
        $this->insertOptionsTables($user_id);
        $this->addFirstFreePlan($user_id);

        /*
         * What the business does. Kept so the account can be asked again later
         * and so the item list knows which trade to fill itself from - and
         * seeded here, once, into the company the account already has when one
         * exists. A brand-new registration has no company yet (the schema wants
         * a KRA PIN), so in that case the seeding is deferred to the first
         * company the account creates; see AdominIndustry::seedPending().
         */
        $this->load->library('AdominIndustry');
        $industry = isset($post['industry']) ? (string) $post['industry'] : '';
        if ($this->adominindustry->isValid($industry)) {
            $this->adominindustry->remember($user_id, $industry);
        }
        $company_id = $this->adominindustry->firstCompanyId($user_id);
        if ($company_id > 0) {
            $this->adominindustry->seed($user_id, $company_id, $this->adominindustry->forUser($user_id));
            $this->adominindustry->markSeeded($user_id);
        }

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return false;
        } else {
            $this->db->trans_commit();
            return true;
        }

        return false;
    }

    /*
     * Default system options for every user
     * they are named - value stores
     */

    private function insertOptionsTables($user_id)
    {
        $data = array(
            array(
                'for_user' => $user_id,
                '_key' => 'opt_invRoundTo',
                'value' => '2'
            ),
            array(
                'for_user' => $user_id,
                '_key' => 'opt_invCalculator',
                'value' => '1'
            ),
            array(
                'for_user' => $user_id,
                '_key' => 'opt_pagination',
                'value' => '20'
            ),
            array(
                'for_user' => $user_id,
                '_key' => 'opt_movementRoundTo',
                'value' => '2'
            ),
            array(
                'for_user' => $user_id,
                '_key' => 'opt_movementCalculator',
                'value' => '1'
            ),
            array(
                'for_user' => $user_id,
                '_key' => 'opt_negativeQuantities',
                'value' => '1'
            )
            ,
            array(
                'for_user' => $user_id,
                '_key' => 'opt_protocolCalculator',
                'value' => '1'
            )
            ,
            array(
                'for_user' => $user_id,
                '_key' => 'opt_protocolRoundTo',
                'value' => '1'
            ),
            array(
                'for_user' => $user_id,
                '_key' => 'opt_invTemplate',
                'value' => 'creative'
            )
        );
        if (!$this->db->insert_batch('value_store', $data)) {
            log_message('error', print_r($this->db->error(), true));
        }
    }

    /*
     * Add first free plan when register
     */

    /**
     * The trial granted at signup.
     *
     * What was here granted Pro for a month, which is the right offer, but it
     * hardcoded the limits that went with it: 3,000 invoices and 10 companies.
     * Ten companies is not what Pro includes (it includes one), and 3,000 was a
     * countdown that nothing refilled - Pro is unlimited now. Both numbers now
     * come from application/config/adomin_plans.php, so the trial cannot drift
     * away from the plan it is a trial of.
     *
     * "Once per account" is enforced by looking for an earlier trial row: the
     * registration path cannot run twice for the same address, but an account
     * that is deleted and re-registered, or a future re-grant path, would
     * otherwise get a second free month.
     *
     * @param int $user_id
     * @return void
     */
    public function addFirstFreePlan($user_id)
    {
        require_once APPPATH . 'config/adomin_plans.php';
        $trial = isset($config['pricing']['trial']) ? $config['pricing']['trial'] : array();
        if (empty($trial['enabled'])) {
            return;
        }

        if (!empty($trial['once_per_account']) && $this->hasHadTrial($user_id)) {
            log_message('error', 'addFirstFreePlan: user ' . $user_id . ' has already had a trial, not granting another');
            return;
        }

        $planKey = isset($trial['plan']) ? $trial['plan'] : 'PRO';
        $plan = isset($config['pricing']['plans'][$planKey]) ? $config['pricing']['plans'][$planKey] : NULL;
        if ($plan === NULL) {
            log_message('error', 'addFirstFreePlan: trial plan "' . $planKey . '" is not in the plan config');
            return;
        }

        $days = isset($trial['days']) ? (int) $trial['days'] : 30;

        if (!$this->db->insert('firms_plans', array(
                    'for_user' => $user_id,
                    'from_date' => time(),
                    // days, not "+1 month": a month is 28-31 days and the trial
                    // is advertised as exactly 30
                    'to_date' => time() + ($days * 86400),
                    'plan_type' => $planKey,
                    // -1 = not a countdown. AdominEntitlements applies the real
                    // limit per calendar month; a stale counter in this column
                    // would shadow the config.
                    'num_invoices' => -1,
                    'num_firms' => isset($plan['limits']['firms']) ? (int) $plan['limits']['firms'] : 1,
                    'time' => time(),
                    'sponsored' => 1
                ))) {
            log_message('error', print_r($this->db->error(), true));
        }
    }

    /**
     * Has this account been given a trial before?
     *
     * Every trial row is sponsored, so an existing one is the answer. A bought
     * plan is not sponsored and does not count.
     *
     * @param int $user_id
     * @return bool
     */
    private function hasHadTrial($user_id)
    {
        $this->db->where('for_user', $user_id);
        $this->db->where('sponsored', 1);
        return $this->db->count_all_results('firms_plans') > 0;
    }

    public function getUserInfoFromEmail($email, $type)
    {
        $array = array();
        /*
         * if is logged employee
         * return data for user and for employee
         * else return data only for user
         */
        if ($type == 2) {
            $this->db->where('enabled', 1);
            $this->db->where('email', $email);
            $result = $this->db->get('employees');
            $array['employee'] = $result->row_array();

            $this->db->where('enabled', 1);
            $this->db->where('id', $array['employee']['for_user']);
            $result = $this->db->get('users');
            $array['user'] = $result->row_array();
        } else {
            $this->db->where('enabled', 1);
            $this->db->where('email', $email);
            $result = $this->db->get('users');
            $array['user'] = $result->row_array();
        }
        return $array;
    }

    /*
     * RETURN TYPES EXPLAIN
     * 1 is user
     * 2 is employee
     * 3 is both 
     */

    public function loginCheck($post)
    {
        $this->db->select('password');
        $this->db->where('enabled', 1);
        $this->db->where('email', $post['email']);
        $resultUsers = $this->db->get('users');
        $rows = $resultUsers->result_array();
        $numUsers = 0;
        foreach ($rows as $row) {
            if (password_verify($post['password'], $row['password'])) {
                $numUsers++;
            }
        }

        $this->db->select('password');
        $this->db->where('enabled', 1);
        $this->db->where('email', $post['email']);
        $resultEmployees = $this->db->get('employees');
        $rows = $resultEmployees->result_array();
        $numEmployees = 0;
        foreach ($rows as $row) {
            if (password_verify($post['password'], $row['password'])) {
                $numEmployees++;
            }
        }

        if ($numUsers > 0 && $numEmployees > 0) {
            return 3;
        }
        if ($numEmployees > 0 && $numUsers == 0) {
            return 2;
        }
        if ($numUsers > 0 && $numEmployees == 0) {
            return 1;
        }
        return false;
    }

    public function getValueStores()
    {
        $query = $this->db->query("SELECT _key, value FROM value_store WHERE for_user=" . USER_ID . "");
        $result = $query->result_array();
        if (empty($result)) {
            return null;
        }
        $arr = array();
        foreach ($result as $res) {
            $arr[$res['_key']] = $res['value'];
        }
        return $arr;
    }

    public function getOneValueStore($key, $userId)
    {
        $query = $this->db->query("SELECT value FROM value_store WHERE _key = '$key' AND for_user = " . $userId . "");
        $result = $query->row_array();
        if (empty($result)) {
            return null;
        }
        return $result['value'];
    }

    public function getInvoiceForAccept($userId, $uniqid)
    {
        $this->db->select('inv_type, inv_number');
        $this->db->where('for_user', $userId);
        $this->db->where('uniqid', $uniqid);
        $this->db->limit(1);
        $result = $this->db->get('invoices');
        return $result->row_array();
    }

    /**
     * Full invoice document for a public "send via link" token.
     *
     * Same shape as NewInvoiceModel::getInvoiceByNumber() but without any
     * session dependency (USER_ID / SELECTED_COMPANY_ID are not defined for
     * anonymous visitors), so the shared link renders exactly like the
     * preview and the PDF.
     *
     * @param int    $userId
     * @param string $uniqid
     * @return array|NULL
     */
    public function getInvoiceForShare($userId, $uniqid)
    {
        $this->db->where('invoices.for_user', $userId);
        $this->db->where('invoices.uniqid', $uniqid);
        $this->db->where('invoices.is_deleted', 0);
        $this->db->limit(1);
        $arr = $this->db->get('invoices')->row_array();
        if (empty($arr)) {
            return NULL;
        }
        $arr['items'] = $this->db->where('for_invoice', $arr['id'])->order_by('position', 'asc')->get('invoices_items')->result_array();
        $arr['client'] = $this->db->where('for_invoice', $arr['id'])->get('invoices_clients')->row_array();
        $arr['firm'] = $this->db->where('for_invoice', $arr['id'])->get('invoices_firms')->row_array();
        $translation = $this->db->where('for_invoice', $arr['id'])->get('invoices_translations')->row_array();
        // same renames as the logged-in path: the share link must not print a
        // label the sender's own view has stopped printing
        $arr['translation'] = function_exists('adomin_normalise_labels')
            ? adomin_normalise_labels($translation)
            : $translation;
        return $arr;
    }

    /**
     * Locate a document from the number printed on it.
     *
     * A verification code carries the invoice number and a dozen characters of
     * signature - not the user, because a number is only unique within one
     * company's own sequence. The number is therefore not an identifier here,
     * and this returns every live invoice carrying it; the caller compares the
     * signature against each in turn. In practice that is one or two rows.
     *
     * @param string $invNumber
     * @return array
     */
    public function getInvoicesByNumber($invNumber)
    {
        $this->db->where('invoices.inv_number', $invNumber);
        $this->db->where('invoices.is_deleted', 0);
        $this->db->order_by('invoices.id', 'desc');
        $this->db->limit(20);
        $rows = $this->db->get('invoices')->result_array();

        if (empty($rows)) {
            return array();
        }

        $ids = array();
        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
        }

        /*
         * The signature covers the client's name, so it has to be loaded here.
         * Only the client and firm rows are needed - items and translations
         * would cost a query each for something the check never reads.
         */
        $clients = $this->db->where_in('for_invoice', $ids)->get('invoices_clients')->result_array();
        $byInvoice = array();
        foreach ($clients as $client) {
            $byInvoice[(int) $client['for_invoice']] = $client;
        }
        foreach ($rows as $i => $row) {
            $id = (int) $row['id'];
            $rows[$i]['client'] = isset($byInvoice[$id]) ? $byInvoice[$id] : array();
        }

        return $rows;
    }

    public function setInvoiceLog($invId, $action, $info = '')
    {
        $result = $this->db->query("INSERT DELAYED INTO invoices_logs (invoice_id, action, info, time) VALUES ($invId, '$action', '" . $this->db->escape_str($info) . "', " . time() . ")");
        if ($result != true) {
            log_message('error', print_r($this->db->error(), true));
        }
    }

}
