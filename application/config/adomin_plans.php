<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
  |--------------------------------------------------------------------------
  | Adomin Technologies — plans and local pricing
  |--------------------------------------------------------------------------
  |
  | Three tiers, one of them free. The prices below are canonical in Kenyan
  | Shillings, because that is where the product is sold from and where the
  | books are kept; everything else is derived. Storing a price per country
  | would mean editing this file every time a central bank moved, and two
  | people would eventually disagree about which copy was current.
  |
  | So: KES is the price, and every other currency is a presentation of it.
  | The conversion happens once per request (AdominPricing), the result is
  | what the buyer is quoted, and the KES figure is kept beside it on the
  | order row - if the shilling moves before they pay, the invoice and the
  | charge still refer to the same number.
  |
  | Overriding a single market: set ADOMIN_PRICE_<CUR> for the monthly and
  | ADOMIN_PRICE_<CUR>_YEARLY for the annual, e.g. ADOMIN_PRICE_USD=2 and
  | ADOMIN_PRICE_USD_YEARLY=17. A market with its own price is quoted in that
  | price and never converted.
  |
 */

if (!function_exists('adomin_plan_price_env')) {
    /**
     * A price pinned for one currency, in minor units.
     *
     * Minor units (cents) so 1750 KES is 175000 and $17.50 is 1750, and so
     * arithmetic never has to be done in floating point on money.
     *
     * @param string $currency
     * @param string $period   monthly | yearly
     * @return int|NULL
     */
    function adomin_plan_price_env($currency, $period = 'monthly')
    {
        $key = 'ADOMIN_PRICE_' . strtoupper($currency) . ($period === 'yearly' ? '_YEARLY' : '');
        $value = getenv($key);
        if ($value === FALSE || trim((string) $value) === '') {
            return NULL;
        }
        return (int) round(((float) $value) * 100);
    }
}

$config['pricing'] = array(

    /*
     * How the quote is turned into a local figure.
     *
     * 'peg' is the currency the KES price is converted through. USD is the
     * intermediate rather than converting KES->EUR directly, because one
     * well-known rate per currency is easier to keep honest than a full matrix
     * of every pair.
     *
     * The rates are the fallback. Production reads live rates daily and caches
     * them (AdominPricing::refreshRates); these numbers exist so a cold start,
     * an offline host or a failed fetch still quotes something sane instead of
     * refusing to sell.
     */
    'peg'        => 'USD',
    'rates'      => array(
        // units of currency per 1 USD
        'KES' => 129.00,
        'USD' => 1.00,
        'EUR' => 0.92,
        'GBP' => 0.79,
        'ZAR' => 18.20,
        'NGN' => 1550.00,
        'UGX' => 3750.00,
        'TZS' => 2650.00,
        'RWF' => 1320.00,
        'ETB' => 124.00,
        'GHS' => 15.10,
        'EGP' => 48.50,
        'AED' => 3.67,
        'SAR' => 3.75,
        'INR' => 84.00,
        'PKR' => 278.00,
        'CAD' => 1.39,
        'AUD' => 1.53,
        'BRL' => 5.80,
        'MXN' => 20.20,
        'JPY' => 152.00,
        'CHF' => 0.88,
        'SEK' => 10.90,
        'NOK' => 11.00,
        'DKK' => 6.90,
        'PLN' => 4.00,
        'TRY' => 34.50,
    ),

    /*
     * Free from a cache, but checked: a rate that has drifted more than this
     * against the previous one is rejected as a bad feed rather than used to
     * reprice the product. 15% in a day is not a move that happens to a pegged
     * quote; it is a parse error or a stale endpoint.
     */
    'max_rate_drift' => 0.15,

    /*
     * Rounding, per currency.
     *
     * A Kenyan price lands on a whole shilling; nobody quotes 1,899.37 to a
     * walk-in customer. USD and EUR land on the .99 everyone recognises as a
     * price. 'round' is the step in minor units: 100 = whole unit, 99 = the
     * .99 ending, 1 = exact.
     */
    'rounding' => array(
        'KES' => 'whole',
        'UGX' => 'whole',
        'TZS' => 'whole',
        'RWF' => 'whole',
        'NGN' => 'whole',
        'JPY' => 'whole',
        'CHF' => 'tenth',
        'SEK' => 'tenth',
        'NOK' => 'tenth',
        'DKK' => 'tenth',
        'PLN' => 'tenth',
        'TRY' => 'tenth',
        'BRL' => 'tenth',
        'MXN' => 'tenth',
        'USD' => 'charm',
        'EUR' => 'charm',
        'GBP' => 'charm',
        'CAD' => 'charm',
        'AUD' => 'charm',
        'default' => 'tenth',
    ),

    /*
     * The tiers.
     *
     * `key` is the column value on firms_plans.plan_type and never changes -
     * changing it would orphan every subscription row that carries it. The
     * name, the pitch and the feature list are presentation and can move.
     *
     * `limits`: -1 means unlimited. A null limit means "not applicable", which
     * reads differently in the UI from "zero allowed".
     */
    'plans' => array(

        'FREE' => array(
            'key'      => 'FREE',
            'name'     => 'Free',
            'tagline'  => 'Everything you need to send your first invoices.',
            'order'    => 1,
            'highlight' => FALSE,
            'price'    => array(
                'monthly' => 0,
                'yearly'  => 0,
            ),
            'trial_days' => 0,
            'limits'   => array(
                'invoices_per_month' => 5,
                'clients'            => 5,
                'firms'              => 1,
                'users'              => 1,
                'items'              => 20,
            ),
            /*
             * One line per comparison row, and the rows line up across tiers -
             * which is what lets the page render a matrix instead of three
             * paragraphs nobody compares. The keys are the row order.
             */
            'features' => array(
                'volume'     => '5 invoices a month, 5 clients',
                'branding'   => 'Basic designs, with our mark in the footer',
                'automation' => 'Manual only',
                'team'       => 'You only',
                'payments'   => 'Payment links, 2% platform fee',
                'tax'        => 'One tax rate per invoice',
                'reporting'  => 'Paid / unpaid list',
            ),
            // what the tier actually enables, for the code that has to enforce it
            'features_flags' => array(
                'watermark'          => TRUE,
                'custom_branding'    => FALSE,
                'reminders'          => FALSE,
                'recurring'          => FALSE,
                'platform_fee_pct'   => 2.0,
                'tax_multi'          => FALSE,
                'tax_summary'        => FALSE,
                'e_invoicing'        => FALSE,
                'read_receipts'      => FALSE,
                'reports_advanced'   => FALSE,
                'custom_domain'      => FALSE,
                'white_label'        => FALSE,
                'multi_currency'     => FALSE,
                'partial_payments'   => FALSE,
                'late_fees'          => FALSE,
                'saved_card_billing' => FALSE,
                'roles'              => FALSE,
            ),
        ),

                'PRO' => array(
            'key'      => 'PRO',
            'name'     => 'Pro',
            'tagline'  => 'Unlimited invoicing, your own branding, no platform fee.',
            'order'    => 2,
            'highlight' => TRUE,
            'price'    => array(
                // 200 KES a month, 1,750 a year. Twelve months would be 2,400,
                // so the year saves 650 - just over three months, not the
                // "two months free" that gets written on every pricing page.
                // Say the number, or say "three months", but not "two".
                'monthly' => 20000,
                'yearly'  => 175000,
            ),
            'trial_days' => 30,
            'limits'   => array(
                'invoices_per_month' => -1,
                'clients'            => -1,
                'firms'              => 1,
                'users'              => 2,
                'items'              => -1,
            ),
            'features' => array(
                'volume'     => 'Unlimited invoices and clients',
                'branding'   => 'Your logo, colours and custom fields, no mark',
                'automation' => 'Reminders and repeating invoices',
                'team'       => 'Two users, full access',
                'payments'   => 'Standard gateways, no platform fee',
                'tax'        => 'Several rates per invoice, tax summary',
                'reporting'  => 'Read receipts, income charts',
            ),
            'features_flags' => array(
                'watermark'          => FALSE,
                'custom_branding'    => TRUE,
                'reminders'          => TRUE,
                'recurring'          => TRUE,
                'platform_fee_pct'   => 0.0,
                'tax_multi'          => TRUE,
                'tax_summary'        => TRUE,
                'e_invoicing'        => FALSE,
                'read_receipts'      => TRUE,
                'reports_advanced'   => FALSE,
                'custom_domain'      => FALSE,
                'white_label'        => FALSE,
                'multi_currency'     => FALSE,
                'partial_payments'   => FALSE,
                'late_fees'          => FALSE,
                'saved_card_billing' => FALSE,
                'roles'              => FALSE,
            ),
        ),

                'BUSINESS' => array(
            'key'      => 'BUSINESS',
            'name'     => 'Business',
            'tagline'  => 'Several businesses, real roles, and the records an auditor asks for.',
            'order'    => 3,
            'highlight' => FALSE,
            // Not sold yet. Listed so the comparison is honest about what sits
            // above Pro, and quoted as "talk to us" rather than invented.
            'price'    => NULL,
            'trial_days' => 30,
            'limits'   => array(
                'invoices_per_month' => -1,
                'clients'            => -1,
                'firms'              => -1,
                'users'              => -1,
                'items'              => -1,
            ),
            'features' => array(
                'volume'     => 'Unlimited, with several business profiles',
                'branding'   => 'Own domain, bespoke CSS and HTML, white-label',
                'automation' => 'Late fees, saved-card billing, workflow rules',
                'team'       => 'Multi-seat, role-based permissions',
                'payments'   => 'Several gateways, deposits, part payments',
                'tax'        => 'Regional e-invoicing, audit trail, KRA eTIMS',
                'reporting'  => 'Time, expenses, project profit, reconciliation',
            ),
            'features_flags' => array(
                'watermark'          => FALSE,
                'custom_branding'    => TRUE,
                'reminders'          => TRUE,
                'recurring'          => TRUE,
                'platform_fee_pct'   => 0.0,
                'tax_multi'          => TRUE,
                'tax_summary'        => TRUE,
                'e_invoicing'        => TRUE,
                'read_receipts'      => TRUE,
                'reports_advanced'   => TRUE,
                'custom_domain'      => TRUE,
                'white_label'        => TRUE,
                'multi_currency'     => TRUE,
                'partial_payments'   => TRUE,
                'late_fees'          => TRUE,
                'saved_card_billing' => TRUE,
                'roles'              => TRUE,
            ),
        ),
    ),

    /*
     * The offer at signup. A trial is a BUSINESS-style grant of Pro features
     * with an end date, so nothing downstream needs to know it is a trial -
     * the plan is Pro, and firms_plans.to_date says when it stops.
     */
    'trial' => array(
        'enabled' => TRUE,
        'plan'    => 'PRO',
        'days'    => 30,
        // A second trial on the same account is a re-signup, not a customer
        'once_per_account' => TRUE,
    ),

    /*
     * What a plan change does to documents already issued. Nothing: an invoice
     * is a record, and downgrading an account must not retroactively put a
     * watermark back on last month's paper.
     */
    'grandfather_issued_documents' => TRUE,
);
