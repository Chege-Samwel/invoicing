<?php

/*
 * @Author:    Kiril Kirkov
 *  Github:    https://github.com/kirilkirkov
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class PlansUsers extends USER_Controller
{

    private $defaultPlans;

    public function __construct()
    {
        parent::__construct();
        $this->defaultPlans = $this->plansForDisplay();
        $this->load->model('PlansModel');
    }

    /**
     * The tiers, in the shape the old views expect.
     *
     * Those templates read $plans['PRO']['PRICE'], ['NUM_INVOICES'] and
     * ['NUM_FIRMS']. The price table they came from is gone (config.php no longer
     * carries a second copy of the pricing), so the array is built here from
     * adomin_plans.php. Rewriting every template was not worth it for two
     * screens; keeping a second price table so they would keep working was
     * worse.
     *
     * @return array
     */
    private function plansForDisplay()
    {
        $this->load->library('AdominPricing');
        $out = array();
        foreach ($this->adominpricing->plans() as $key => $plan) {
            $out[$key] = array(
                // the KES figure, unformatted: these screens compare it
                'PRICE' => is_array($plan['price'])
                    ? (int) round($plan['price']['monthly'] / 100)
                    : 0,
                'PRICE_MINOR' => is_array($plan['price']) ? (int) $plan['price']['monthly'] : NULL,
                'PRICE_FORMATTED' => $this->adominpricing->quote($key, 'monthly')['formatted'],
                'NUM_INVOICES' => $this->adominpricing->limitLabel($this->adominpricing->limit($key, 'invoices_per_month')),
                'NUM_FIRMS' => $this->adominpricing->limitLabel($this->adominpricing->limit($key, 'firms')),
                'NAME' => $plan['name'],
                // not sold online: the request flow has nothing to sell
                'SELLABLE' => is_array($plan['price']),
            );
        }
        return $out;
    }

    public function index()
    {
        $data = array();
        $head = array();
        $head['title'] = lang('title_everytime') . lang('title_plans');
        if ($this->plans->checkHaveIRequest() == true) {
            redirect(lang_url('user/myplan/request'));
        }
        if (isset($_POST['inv_per_month'])) {
            $this->sendPlanRequest();
        }
        $data['myCurrentPlans'] = $this->PlansModel->getMyActivePlans();
        $data['individualPlan'] = $this->PlansModel->getIndividualPlan();
        $data['requestForIndividualPlan'] = $this->PlansModel->getRequestForIndividualPlan();
        $data['plans'] = $this->defaultPlans;

        /*
         * The in-panel plan screen points at the public price list for the
         * comparison. It used to be the only place a price was shown, and it
         * showed a different one.
         */
        $this->load->library('AdominPricing');
        $data['priceList'] = $this->adominpricing->priceList('monthly');
        $data['currentPlanKey'] = $this->plans->myCurrentPlanKey();
        $data['pricingUrl'] = base_url('pricing');

        $this->render('plans/index', $head, $data);
        $this->saveHistory('Go to plans page');
    }

    private function sendPlanRequest()
    {
        $this->PlansModel->setPlanRequest($_POST);
        $this->session->set_flashdata('resultAction', lang('plan_req_sended'));
        redirect(lang_url('user/plans'));
    }

    public function choosePeriod($planType)
    {
        if ($this->plans->checkHaveIRequest() == true) {
            redirect(lang_url('user/myplan/request'));
        }
        $individualPlan = $this->PlansModel->getIndividualPlan();
        if (!array_key_exists(strtoupper($planType), $this->defaultPlans) && ($planType != 'custom' || $individualPlan == null)) {
            show_404();
        }
        if (isset($_POST['period'])) {
            $this->PlansModel->setNewPaymentRequiest($_POST);
            redirect(lang_url('user/myplan/request'));
        }
        $data = array();
        $head = array();
        $head['title'] = lang('title_everytime') . lang('title_choose_plan_period');
        $data['planType'] = $planType;
        $data['plans'] = $this->defaultPlans;
        if ($planType == 'custom') {
            $data['plans']['CUSTOM'] = array(
                'PRICE' => $individualPlan['price'],
                'NUM_INVOICES' => $individualPlan['num_invoices'],
                'NUM_FIRMS' => $individualPlan['num_firms']
            );
        }
        $this->render('plans/period', $head, $data);
        $this->saveHistory('Go to plans confirm page');
    }

    public function planRequest()
    {
        $data = array();
        $head = array();
        $head['title'] = lang('title_everytime') . lang('title_plan_req');
        $data['paymentReq'] = $this->PlansModel->getMyRequest();
        if ($data['paymentReq'] == null) {
            redirect(lang_url('user/plans'));
        }
        if (isset($_GET['payment']) && $_GET['payment'] == 'cancel') {
            $this->PlansModel->cancelPaymentRequest($data['paymentReq']['id']);
            redirect(lang_url('user/plans'));
        }
        $data['plans'] = $this->defaultPlans;
        if ($data['paymentReq']['plan_type'] == 'CUSTOM') {
            $individualPlan = $this->PlansModel->getIndividualPlan();
            $data['plans']['CUSTOM'] = array(
                'PRICE' => $individualPlan['price'],
                'NUM_INVOICES' => $individualPlan['num_invoices'],
                'NUM_FIRMS' => $individualPlan['num_firms']
            );
        }
        $priceOfSelectedPlan = $data['plans'][$data['paymentReq']['plan_type']]['PRICE'];
        $periodOfSelectedPlan = $data['paymentReq']['plan_period'];
        $data['mustPayAmount'] = $priceOfSelectedPlan * $periodOfSelectedPlan;
        $this->render('plans/request', $head, $data);
        $this->saveHistory('Go to plan request page');
    }

}
