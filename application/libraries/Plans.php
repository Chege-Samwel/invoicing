<?php

defined('BASEPATH') OR exit('No direct script access allowed');
/*
 * This class loads users plans
 */

class Plans
{

    private $CI;

    public function __construct()
    {
        $this->CI = & get_instance();
        $this->CI->load->model('PlansModel');
    }

    /*
     * The system wait for constant number firms
     * number of invoces are decrease when create
     */

    /**
     * How much of the plan is left, in the shape the views expect.
     *
     * This used to be a countdown - firms_plans.num_invoices was decremented per
     * invoice and never refilled - with a hardcoded 5 for the free plan. Two
     * problems with that: a paid allowance never came back, so "200 invoices"
     * meant 200 for ever rather than 200 this month; and the 5 lived here while
     * every other limit lived nowhere at all.
     *
     * The limits now come from application/config/adomin_plans.php via
     * AdominEntitlements, which also counts what has been used. The array shape
     * is unchanged so templates that print num_invoices keep working.
     *
     * @return array num_invoices, num_firms
     */
    public function getMyCurrentPlanUnits()
    {
        $this->CI->load->library('AdominEntitlements');
        return $this->CI->adominentitlements->planUnits();
    }

    /**
     * The plan key this account is on: FREE, PRO or BUSINESS.
     *
     * @return string
     */
    public function myCurrentPlanKey()
    {
        $this->CI->load->library('AdominEntitlements');
        return $this->CI->adominentitlements->planKey();
    }

    /**
     * A capability flag for the current plan.
     *
     * @param string $flag
     * @return mixed
     */
    public function flag($flag)
    {
        $this->CI->load->library('AdominEntitlements');
        return $this->CI->adominentitlements->flag($flag);
    }

    public function myCurrentPlanType()
    {
        $myPlanType = $this->CI->PlansModel->getMyPlanType();
        // An account with no plan row used to trigger "Trying to access array
        // offset on null" here. No row means Free, which is a plan.
        if (empty($myPlanType['plan_type'])) {
            return 'FREE';
        }
        return $myPlanType['plan_type'];
    }

    public function checkHaveIRequest()
    {
        $myRequest = $this->CI->PlansModel->getMyRequest();
        if ($myRequest == null) {
            return false;
        }
        return true;
    }

}
