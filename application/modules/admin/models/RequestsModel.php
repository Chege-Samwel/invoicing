<?php

class RequestsModel extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    public function getRequests($limit, $page)
    {
        $this->db->select('firms_payment_requests.*, individual_plans.price');
        $this->db->where('status', 0);
        $this->db->join('individual_plans', 'individual_plans.for_user = firms_payment_requests.for_user', 'left');
        $query = $this->db->get('firms_payment_requests', $limit, $page);
        return $query->result_array();
    }

    public function requestsCount()
    {
        $this->db->where('status', 0);
        return $this->db->count_all_results('firms_payment_requests');
    }

    public function activateRequestId($id)
    {
        $this->db->trans_begin();
        $this->db->where('id', $id);
        $this->db->update('firms_payment_requests', array(
            'status' => 2,
            'date_activated' => time()
        ));

        $this->db->where('id', $id);
        $result = $this->db->get('firms_payment_requests');
        $row = $result->row_array();

        /*
         * The grant no longer reads config.php's BASIC/ADVANCED/PRO table.
         *
         * That table priced plans at 5 / 15 / 50 and capped them at 20 / 200 /
         * 3000 invoices for ever, which is not what is sold any more. The tiers
         * live in application/config/adomin_plans.php, and the period sold is a
         * length, not a number of months.
         *
         * Two bugs go with it:
         *
         *   - `if ($row['plan_type'] == 'PRO') $toDate = strtotime("+1 month", $toDate)`
         *     gave every Pro buyer a free extra month. A year bought was thirteen
         *     months granted, and nothing in the UI explained the gift.
         *   - num_invoices was a countdown that nothing refilled. It is stored as
         *     -1 now: "not a countdown", since the limit is applied per calendar
         *     month by AdominEntitlements and a stale counter would shadow it.
         */
        if ($row['plan_type'] == 'CUSTOM') {
            $this->db->where('for_user', $row['for_user']);
            $result = $this->db->get('individual_plans');
            $rowPlan = $result->row_array();
            $months = (int) $row['plan_period'];
            $toDate = strtotime('+' . $months . ' month', time());
            $numInvoices = (int) $rowPlan['num_invoices'];
            $numFirms = (int) $rowPlan['num_firms'];
            $planType = 'CUSTOM';
        } else {
            $this->load->library('AdominPricing');
            $plan = $this->adominpricing->plan($row['plan_type']);
            if ($plan === NULL) {
                log_message('error', 'activateRequestId: unknown plan_type "' . $row['plan_type'] . '" on request ' . $id);
                $this->db->trans_rollback();
                show_error(lang('database_error'));
                return;
            }

            /*
             * plan_period is an option value from the period screen: 12, 24, 36
             * or 48. Those were treated as months, which gave a twelve-month
             * purchase a year - correct by accident. A two-year purchase got two
             * months and a four-year purchase four. Convert to the unit the
             * column actually means.
             */
            $period = (int) $row['plan_period'];
            $years = in_array($period, array(1, 2, 3, 4), TRUE)
                ? $period
                : max(1, (int) round($period / 12));
            $toDate = strtotime('+' . $years . ' year', time());

            $planType = $plan['key'];
            $numInvoices = -1;
            $numFirms = isset($plan['limits']['firms']) ? (int) $plan['limits']['firms'] : 1;
        }

        $this->db->insert('firms_plans', array(
            'for_user' => $row['for_user'],
            'from_date' => time(),
            'to_date' => $toDate,
            'plan_type' => $planType,
            'num_invoices' => $numInvoices,
            'num_firms' => $numFirms,
            'time' => time()
        ));
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            show_error(lang('database_error'));
        } else {
            $this->db->trans_commit();
        }
    }

    public function customPlanRequestsCount()
    {
        $this->db->where('status', 0);
        return $this->db->count_all_results('cusom_plan_requests');
    }

    public function getCustomPlanRequests($limit, $page)
    {
        $this->db->where('status', 0);
        $query = $this->db->get('cusom_plan_requests', $limit, $page);
        return $query->result_array();
    }

    public function setIndividualPlan($id, $price, $for_user)
    {
        $this->db->trans_begin();
        $this->db->where('id', $id);
        $this->db->where('status', 0);
        $this->db->update('cusom_plan_requests', array(
            'status' => 2
        ));

        $this->db->where('id', $id);
        $query = $this->db->get('cusom_plan_requests');
        $customReq = $query->row_array();

        $this->db->where('for_user', $for_user);
        $numIndividual = $this->db->count_all_results('individual_plans');
        if ($numIndividual > 0) {
            $this->db->where('for_user', $for_user);
            $this->db->update('individual_plans', array(
                'num_invoices' => $customReq['invoices'],
                'num_firms' => $customReq['companies'],
                'price' => $price
            ));
        } else {
            $this->db->insert('individual_plans', array(
                'for_user' => $for_user,
                'num_invoices' => $customReq['invoices'],
                'num_firms' => $customReq['companies'],
                'price' => $price
            ));
        }
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            show_error(lang('database_error'));
        } else {
            $this->db->trans_commit();
        }
    }

    public function rejectIndividualPlanRequest($id)
    {
        $this->db->where('id', $id);
        if (!$this->db->update('cusom_plan_requests', array(
                    'status' => 2
                ))) {
            show_error(lang('database_error'));
        }
    }

}
