<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * The public pricing page.
 *
 * It lives outside the user panel on purpose: the people who need to see the
 * price are the ones who have not signed up yet. The URL is /pricing, and the
 * language prefix (/en/pricing) is handled by the router like every other
 * public page.
 */
class Pricing extends MY_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->library('AdominPricing');
    }

    public function index()
    {
        $data = array();
        $head = array();

        /*
         * Currency can be chosen, and the choice is remembered. The visitor's
         * location only ever supplies the default - a Kenyan browsing from a
         * London hotel is still paying in shillings.
         */
        if (isset($_POST['currency'])) {
            $this->adominpricing->setPreferredCurrency($_POST['currency']);
            redirect(base_url('pricing'));
        }

        /*
         * There is one price and one period. ?period=yearly still arrives on
         * links people bookmarked while the product was a subscription, so the
         * parameter is normalised rather than rejected - but nothing reads it.
         */
        $period = $this->adominpricing->normalisePeriod(isset($_GET['period']) ? $_GET['period'] : NULL);

        $pricing = $this->adominpricing;
        $currency = $pricing->currencyForVisitor();
        $country = $pricing->countryForVisitor();

        $data['pricing'] = $pricing;
        $data['period'] = $period;
        $data['currency'] = $currency;
        $data['currencies'] = $pricing->supportedCurrencies();
        $data['geography'] = $pricing->countryNames();
        $data['countryName'] = $country !== NULL && isset($data['geography'][$country])
            ? $data['geography'][$country]
            : 'your region';

        /*
         * Whether the figure was guessed. If the visitor picked a currency or
         * the account already had one, it is not a guess and there is nothing to
         * explain or apologise for.
         */
        $data['currencyGuessed'] = !$pricing->hasExplicitCurrency();
        $data['ratesStale'] = $pricing->ratesAreStale();

        $data['currencyAction'] = base_url('pricing');
        $data['csrfName'] = $this->security->get_csrf_token_name();
        $data['csrfHash'] = $this->security->get_csrf_hash();
        $data['registerUrl'] = base_url('registration');
        $data['contactUrl'] = base_url('contacts');
        $data['buyUrlBase'] = base_url('user/plan/');

        // tell the layout this page brings its own stylesheet
        $head['pricingStylesheet'] = TRUE;
        $head['title'] = 'Pricing — ' . (adomin_conf('brand', 'name') ?: 'anvoice');
        $head['description'] = 'Free invoicing to start. Pro is 6,000 KES once - unlimited invoices, '
            . 'your own branding and no platform fee, with no subscription. Prices shown in your currency.';

        $this->render('home/pricing', $head, $data);
    }
}
