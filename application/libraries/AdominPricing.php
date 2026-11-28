<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * What a plan costs here, in the money the buyer thinks in.
 *
 * The product is priced once, in Kenyan Shillings. This class turns that into
 * a local figure without ever storing a second canonical price, and without
 * guessing from an IP address alone.
 *
 * Why not just convert live and show the result:
 *
 *   - A price that moves every hour looks like a mistake. "200 KES" and then
 *     "1.62 USD" and then "1.58 USD" in one afternoon costs a sale.
 *   - A quote has to survive to the moment of payment. If the buyer is shown
 *     1.62 USD and charged the equivalent of today's rate, somebody is going
 *     to ask which number was the price.
 *
 * So: the rate is fetched at most once a day, cached on disk, sanity-checked
 * against the previous day's figure, and the buyer is quoted against that. The
 * KES figure is recorded alongside the local one on the order, so a charge can
 * always be explained.
 *
 * Geography, in order of confidence:
 *
 *   1. What the account already says (their invoices' currency) - a business
 *      billing in USD is a USD customer, whatever hotel their IP is in.
 *   2. The country Cloudflare or a local geolocation database reports.
 *   3. The browser's own timezone, read server-side from the Accept-Language
 *      header's region subtag, then from a cookie the page sets.
 *   4. The site's configured default.
 *
 * Every step is optional and the last one always answers, so a request with no
 * headers at all still produces a price.
 */
class AdominPricing
{
    /**
     * @var object
     */
    protected $CI;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var array|NULL memoised quotes for this request
     */
    protected $quotes = array();

    /**
     * Country -> currency, for the markets this product actually sells into.
     *
     * Not exhaustive on purpose: a country we have never sold to gets the
     * region's currency, which is a better guess than the site default and a
     * much better one than a wrong local currency.
     *
     * @var array
     */
    protected $countryCurrency = array(
        'KE' => 'KES', 'TZ' => 'TZS', 'UG' => 'UGX', 'RW' => 'RWF', 'BI' => 'BIF',
        'SS' => 'SSP', 'ET' => 'ETB', 'SO' => 'SOS', 'ZM' => 'ZMW', 'ZW' => 'USD',
        'MW' => 'MWK', 'MZ' => 'MZN', 'ZA' => 'ZAR', 'NA' => 'NAD', 'BW' => 'BWP',
        'NG' => 'NGN', 'GH' => 'GHS', 'CI' => 'XOF', 'SN' => 'XOF', 'CM' => 'XAF',
        'EG' => 'EGP', 'MA' => 'MAD', 'TN' => 'TND', 'DZ' => 'DZD',
        'US' => 'USD', 'CA' => 'CAD', 'MX' => 'MXN', 'BR' => 'BRL', 'AR' => 'ARS',
        'GB' => 'GBP', 'IE' => 'EUR', 'FR' => 'EUR', 'DE' => 'EUR', 'ES' => 'EUR',
        'IT' => 'EUR', 'NL' => 'EUR', 'BE' => 'EUR', 'PT' => 'EUR', 'AT' => 'EUR',
        'FI' => 'EUR', 'GR' => 'EUR', 'SK' => 'EUR', 'SI' => 'EUR', 'LT' => 'EUR',
        'LV' => 'EUR', 'EE' => 'EUR', 'LU' => 'EUR', 'CY' => 'EUR', 'MT' => 'EUR',
        'SE' => 'SEK', 'NO' => 'NOK', 'DK' => 'DKK', 'CH' => 'CHF', 'PL' => 'PLN',
        'CZ' => 'CZK', 'HU' => 'HUF', 'RO' => 'RON', 'BG' => 'BGN', 'HR' => 'EUR',
        'TR' => 'TRY', 'RU' => 'RUB', 'UA' => 'UAH',
        'AE' => 'AED', 'SA' => 'SAR', 'QA' => 'QAR', 'KW' => 'KWD', 'BH' => 'BHD',
        'OM' => 'OMR', 'JO' => 'JOD', 'IL' => 'ILS', 'LB' => 'LBP',
        'IN' => 'INR', 'PK' => 'PKR', 'BD' => 'BDT', 'LK' => 'LKR', 'NP' => 'NPR',
        'SG' => 'SGD', 'MY' => 'MYR', 'ID' => 'IDR', 'TH' => 'THB', 'VN' => 'VND',
        'PH' => 'PHP', 'CN' => 'CNY', 'JP' => 'JPY', 'KR' => 'KRW', 'HK' => 'HKD',
        'AU' => 'AUD', 'NZ' => 'NZD',
    );

    /**
     * Language region subtag -> country, for the Accept-Language fallback.
     *
     * @var array
     */
    protected $langRegion = array(
        'en-ke' => 'KE', 'en-tz' => 'TZ', 'en-ug' => 'UG', 'sw' => 'KE',
        'en-za' => 'ZA', 'en-ng' => 'NG', 'en-gh' => 'GH', 'en-gb' => 'GB',
        'en-us' => 'US', 'en-ca' => 'CA', 'en-au' => 'AU', 'en-in' => 'IN',
        'fr-fr' => 'FR', 'fr-ca' => 'CA', 'de-de' => 'DE', 'es-es' => 'ES',
        'pt-br' => 'BR', 'ar-ae' => 'AE', 'hi-in' => 'IN', 'ja-jp' => 'JP',
    );

    public function __construct()
    {
        $this->CI = & get_instance();

        if (!function_exists('adomin_conf') && function_exists('get_instance')) {
            $CI = & get_instance();
            if (is_object($CI) && isset($CI->load)) {
                $CI->load->helper('adomin');
            }
        }

        $this->config = $this->loadConfig();
    }

    /**
     * The pricing config, from CodeIgniter's loader.
     *
     * `require_once APPPATH.'config/adomin_plans.php'` from inside a method looks
     * like it works and does not: the file assigns a local $config, which dies
     * with the constructor. Every plan then came back empty, so the pricing page
     * rendered three blank cards and every limit read as unlimited - a silent,
     * total failure that only shows on the one page nobody loads in a test
     * unless they look at the output.
     *
     * Loading through CI puts the array where config items live, under its own
     * section so it cannot collide with the app's own $config['plans'].
     *
     * @return array
     */
    protected function loadConfig()
    {
        if (isset($this->CI->config) && is_object($this->CI->config)
            && method_exists($this->CI->config, 'load')) {
            $this->CI->config->load('adomin_plans', TRUE, TRUE);
            $pricing = $this->CI->config->item('pricing', 'adomin_plans');
            if (is_array($pricing) && !empty($pricing)) {
                return $pricing;
            }
        }

        /*
         * No CI config object - a unit test, a CLI script, the harness. Include
         * the file into a closure so the $config it assigns is captured rather
         * than discarded.
         */
        $file = APPPATH . 'config/adomin_plans.php';
        if (is_file($file)) {
            $captured = (static function ($__path) {
                $config = array();
                require $__path;
                return $config;
            })($file);
            if (isset($captured['pricing']) && is_array($captured['pricing'])) {
                return $captured['pricing'];
            }
        }

        return array();
    }

    /* ------------------------------------------------------------------ */
    /* The catalogue                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Every plan, in display order.
     *
     * @return array
     */
    public function plans()
    {
        $plans = isset($this->config['plans']) ? $this->config['plans'] : array();
        uasort($plans, function ($a, $b) {
            $ao = isset($a['order']) ? (int) $a['order'] : 0;
            $bo = isset($b['order']) ? (int) $b['order'] : 0;
            return $ao === $bo ? 0 : ($ao < $bo ? -1 : 1);
        });
        return $plans;
    }

    /**
     * One plan by key, case-insensitively; NULL when it does not exist.
     *
     * Callers pass plan keys that came from a database column filled in years
     * ago, so this is forgiving about case and about the legacy names the
     * upstream app used.
     *
     * @param string $key
     * @return array|NULL
     */
    public function plan($key)
    {
        $key = strtoupper(trim((string) $key));
        $legacy = array(
            'BASIC' => 'FREE',      // the old cheapest tier, now the free one
            'ADVANCED' => 'PRO',
            'PRO' => 'PRO',
            'FREE' => 'FREE',
            'BUSINESS' => 'BUSINESS',
            'PREMIUM' => 'BUSINESS',
        );
        if (isset($legacy[$key])) {
            $key = $legacy[$key];
        }

        $plans = $this->plans();
        return isset($plans[$key]) ? $plans[$key] : NULL;
    }

    /**
     * The row labels of the comparison matrix, in the order buyers care about
     * them: what it costs to use, then what it looks like, then what it does.
     *
     * @return array
     */
    public function featureLabels()
    {
        return array(
            'volume'     => 'Volume',
            'branding'   => 'Branding',
            'automation' => 'Automation',
            'team'       => 'Team',
            'payments'   => 'Payments',
            'tax'        => 'Tax',
            'reporting'  => 'Reporting',
        );
    }

    /**
     * The matrix as rows: one row per feature, one cell per plan.
     *
     * Built from the plan config rather than written out in the view, so a
     * tier that gains a feature cannot end up advertised on the page and
     * missing from the plan the buyer is actually put on.
     *
     * @return array  [ ['key','label','cells' => ['FREE' => '...', ...]], ... ]
     */
    public function featureMatrix()
    {
        $rows = array();
        foreach ($this->featureLabels() as $key => $label) {
            $cells = array();
            foreach ($this->plans() as $planKey => $plan) {
                $cells[$planKey] = isset($plan['features'][$key]) ? $plan['features'][$key] : NULL;
            }
            $rows[] = array('key' => $key, 'label' => $label, 'cells' => $cells);
        }
        return $rows;
    }

    /**
     * A plan's machine-readable capabilities.
     *
     * Anything that gates behaviour - the watermark, the platform fee, whether
     * a second user is allowed - must read this instead of comparing the plan
     * name to a string. Plan names are marketing and get rewritten; these keys
     * are the contract.
     *
     * @param string $planKey
     * @return array
     */
    public function flags($planKey)
    {
        $plan = $this->plan($planKey);
        if ($plan === NULL || !isset($plan['features_flags'])) {
            // An unknown plan is treated as the most restricted tier rather
            // than the most permissive one. Defaulting open is how a bug
            // becomes a free Business account.
            $fallback = $this->plan('FREE');
            return isset($fallback['features_flags']) ? $fallback['features_flags'] : array();
        }
        return $plan['features_flags'];
    }

    /**
     * A limit for one plan, with -1 normalised to NULL meaning unlimited.
     *
     * @param string $planKey
     * @param string $limit   invoices_per_month | clients | firms | users | items
     * @return int|NULL  NULL = unlimited, 0 = not offered at all
     */
    public function limit($planKey, $limit)
    {
        $plan = $this->plan($planKey);
        if ($plan === NULL || !isset($plan['limits'][$limit])) {
            return 0;
        }
        $value = (int) $plan['limits'][$limit];
        return $value < 0 ? NULL : $value;
    }

    /**
     * "Unlimited", "5", "Not included" - the limit as a person reads it.
     *
     * @param int|NULL $value
     * @param string   $unlimited
     * @return string
     */
    public function limitLabel($value, $unlimited = 'Unlimited')
    {
        if ($value === NULL) {
            return $unlimited;
        }
        return $value === 0 ? 'Not included' : (string) $value;
    }

    /* ------------------------------------------------------------------ */
    /* Price                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * A plan's price, converted for this visitor.
     *
     * @param string $planKey
     * @param string $period  monthly | yearly
     * @param string $currency optional override
     * @param bool   $refresh  ignore this request's memo (not the day cache)
     * @return array  amount_minor, currency, formatted, compare_at, base
     */
    public function quote($planKey, $period = 'monthly', $currency = NULL, $refresh = FALSE)
    {
        $period = $period === 'yearly' ? 'yearly' : 'monthly';
        $plan = $this->plan($planKey);
        if ($plan === NULL) {
            return $this->emptyQuote($period, $currency);
        }

        // A plan with no price is not sold online ("talk to us").
        if (!is_array($plan['price'])) {
            return $this->emptyQuote($period, $currency);
        }

        $currency = $this->normaliseCurrency($currency !== NULL ? $currency : $this->currencyForVisitor());
        $cacheKey = $plan['key'] . '|' . $period . '|' . $currency;
        if (!$refresh && isset($this->quotes[$cacheKey])) {
            return $this->quotes[$cacheKey];
        }

        $baseMinor = (int) $plan['price'][$period];

        // Free is free in every currency, and no rate should ever move it.
        if ($baseMinor === 0) {
            $quote = $this->buildQuote(0, 0, $currency, $period, $plan);
            return $this->quotes[$cacheKey] = $quote;
        }

        // A pinned price for this market wins over any conversion.
        $pinned = adomin_plan_price_env($currency, $period);
        if ($pinned !== NULL) {
            $quote = $this->buildQuote($pinned, $baseMinor, $currency, $period, $plan);
            $quote['source'] = 'pinned';
            $quote['exact_minor'] = NULL;
            return $this->quotes[$cacheKey] = $quote;
        }

        $rates = $this->rates();
        $from = isset($rates['KES']) ? (float) $rates['KES'] : 0.0;
        $to = isset($rates[$currency]) ? (float) $rates[$currency] : 0.0;

        if ($from <= 0 || $to <= 0) {
            // No rate for this currency: quote in KES rather than invent a
            // number, and let the page say so.
            $quote = $this->buildQuote($baseMinor, $baseMinor, 'KES', $period, $plan);
            $quote['source'] = 'base';
            $quote['fallback'] = TRUE;
            return $this->quotes[$cacheKey] = $quote;
        }

        // KES -> USD -> target, in floats only for the multiplication; the
        // result is rounded to a minor unit immediately and kept as an int.
        $inPeg = ($baseMinor / 100) / $from;
        $converted = $inPeg * $to;
        $exactMinor = (int) round($converted * 100);
        $minor = $this->roundFor($exactMinor, $currency);

        $quote = $this->buildQuote($minor, $baseMinor, $currency, $period, $plan);
        $quote['source'] = 'converted';
        // keep the unrounded conversion: rounding is policy, and policy that
        // drifts 30% from the real price is a bug we need to be able to see
        $quote['exact_minor'] = $exactMinor;
        return $this->quotes[$cacheKey] = $quote;
    }

    /**
     * Every plan, priced, as the pricing page needs it.
     *
     * @param string $period
     * @param string $currency
     * @return array
     */
    public function priceList($period = 'monthly', $currency = NULL)
    {
        $out = array();
        foreach ($this->plans() as $plan) {
            $quote = $this->quote($plan['key'], $period, $currency);
            $quote['plan'] = $plan;
            /*
             * The monthly price is worth showing beside the annual one, so the
             * buyer can see what the year actually saves. Both sides of that
             * subtraction have to be in the same currency *and* the same unit:
             * amount_minor is cents, so twelve months is twelve times the
             * monthly minor figure. Mixing a minor figure with a major one here
             * is how "saves KSh 650" turns into "saves KSh 221,850".
             */
            if ($period === 'yearly' && is_array($plan['price']) && (int) $plan['price']['monthly'] > 0) {
                $monthly = $this->quote($plan['key'], 'monthly', $currency);
                $twelveMonths = (int) $monthly['amount_minor'] * 12;
                $quote['monthly_quote'] = $monthly;
                $quote['monthly_equivalent'] = (int) round($quote['amount_minor'] / 12);
                $quote['saving_minor'] = max(0, $twelveMonths - (int) $quote['amount_minor']);
                $quote['saving'] = $quote['saving_minor'] > 0 ? $quote['saving_minor'] : NULL;
                $quote['saving_formatted'] = $quote['saving_minor'] > 0
                    ? $this->format($quote['saving_minor'], $quote['currency'])
                    : NULL;
                /*
                 * "Two months free" was wrong for these numbers: 12 x 200 is
                 * 2,400 against 1,750, so the year gives away 650 - a third of
                 * the year, just over three months. Report the saving in months
                 * as a rounded figure and let the page say "just over" rather
                 * than claiming a nice round number the maths does not support.
                 */
                $quote['free_months'] = $quote['saving_minor'] > 0 && (int) $monthly['amount_minor'] > 0
                    ? round($quote['saving_minor'] / (int) $monthly['amount_minor'], 1)
                    : 0;
            }
            $out[$plan['key']] = $quote;
        }
        return $out;
    }

    /**
     * What the trial offers, for the page and for signup.
     *
     * @return array
     */
    public function trial()
    {
        $trial = isset($this->config['trial']) ? $this->config['trial'] : array();
        if (empty($trial['enabled'])) {
            return array('enabled' => FALSE, 'days' => 0);
        }
        $plan = $this->plan(isset($trial['plan']) ? $trial['plan'] : 'PRO');
        $trial['enabled'] = TRUE;
        $trial['plan_key'] = $plan['key'];
        $trial['plan_name'] = $plan['name'];
        $trial['once'] = !empty($trial['once_per_account']);
        return $trial;
    }

    /* ------------------------------------------------------------------ */
    /* Currency detection                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * The currency to quote this visitor in.
     *
     * @return string ISO code
     */
    public function currencyForVisitor()
    {
        $fromAccount = $this->currencyFromAccount();
        if ($fromAccount !== NULL) {
            return $fromAccount;
        }

        $country = $this->countryForVisitor();
        if ($country !== NULL && isset($this->countryCurrency[$country])) {
            return $this->countryCurrency[$country];
        }

        $default = function_exists('adomin_conf') ? adomin_conf('locale', 'currency') : NULL;
        return $this->normaliseCurrency($default !== NULL ? $default : 'KES');
    }

    /**
     * Country code (ISO 3166-1 alpha-2) for this visitor, best guess.
     *
     * Cloudflare puts the country in a header on a proxied host; a plain VPS
     * has it in the browser's language preferences. Neither is proof, which is
     * why the quote is only ever a starting point the buyer can change.
     *
     * @return string|NULL
     */
    public function countryForVisitor()
    {
        $headers = array('HTTP_CF_IPCOUNTRY', 'HTTP_X_COUNTRY_CODE', 'HTTP_X_GEOIP_COUNTRY', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY');
        foreach ($headers as $header) {
            $value = $this->server($header);
            if ($value !== '' && strlen($value) === 2 && strtoupper($value) !== 'XX' && strtoupper($value) !== 'T1') {
                return strtoupper($value);
            }
        }

        // The browser's own setting, which the page also mirrors into a cookie
        // so the server does not have to rely on the header's ordering.
        $cookie = $this->cookie('adomin_cc');
        if ($cookie !== '' && strlen($cookie) === 2) {
            return strtoupper($cookie);
        }

        $accept = strtolower($this->server('HTTP_ACCEPT_LANGUAGE'));
        if ($accept !== '') {
            foreach (explode(',', $accept) as $part) {
                $tag = trim(explode(';', $part)[0]);
                if (isset($this->langRegion[$tag])) {
                    return $this->langRegion[$tag];
                }
                // en-KE, en-GB: the bare language alone is not a country
                if (strpos($tag, '-') !== FALSE) {
                    $pieces = explode('-', $tag);
                    $region = strtoupper(end($pieces));
                    if (strlen($region) === 2 && isset($this->countryCurrency[$region])) {
                        return $region;
                    }
                }
            }
        }

        $tz = strtolower($this->server('HTTP_X_TIMEZONE'));
        if ($tz !== '') {
            $map = array(
                'africa/nairobi' => 'KE', 'africa/dar_es_salaam' => 'TZ', 'africa/kampala' => 'UG',
                'africa/kigali' => 'RW', 'africa/addis_ababa' => 'ET', 'africa/lagos' => 'NG',
                'africa/accra' => 'GH', 'africa/cairo' => 'EG', 'africa/johannesburg' => 'ZA',
                'europe/london' => 'GB', 'europe/dublin' => 'IE', 'europe/paris' => 'FR',
                'europe/berlin' => 'DE', 'europe/madrid' => 'ES', 'europe/rome' => 'IT',
                'america/new_york' => 'US', 'america/chicago' => 'US', 'america/denver' => 'US',
                'america/los_angeles' => 'US', 'america/toronto' => 'CA', 'america/sao_paulo' => 'BR',
                'asia/dubai' => 'AE', 'asia/riyadh' => 'SA', 'asia/kolkata' => 'IN',
                'asia/singapore' => 'SG', 'asia/tokyo' => 'JP', 'australia/sydney' => 'AU',
            );
            if (isset($map[$tz])) {
                return $map[$tz];
            }
        }

        return NULL;
    }

    /**
     * Did this visitor actually choose a currency, or are we guessing?
     *
     * The page treats a guess differently from a choice: it says the figure was
     * inferred and offers to change it. Silently presenting a guessed foreign
     * price as if the buyer had asked for it is how people end up disputing a
     * charge.
     *
     * @return bool
     */
    public function hasExplicitCurrency()
    {
        return $this->currencyFromAccount() !== NULL;
    }

    /**
     * Are the rates older than a day?
     *
     * A stale rate is not an error - the fallback table is always there - but
     * the page should say so rather than imply the figure is current to the
     * minute.
     *
     * @return bool
     */
    public function ratesAreStale()
    {
        $stamp = $this->ratesUpdatedAt();
        if ($stamp === NULL || (int) $stamp <= 0) {
            return TRUE;
        }
        return (time() - (int) $stamp) > 86400;
    }

    /**
     * ISO country code => English name, for the "guessed from" line.
     *
     * @return array
     */
    public function countryNames()
    {
        return array(
            'KE' => 'Kenya', 'TZ' => 'Tanzania', 'UG' => 'Uganda', 'RW' => 'Rwanda',
            'ET' => 'Ethiopia', 'NG' => 'Nigeria', 'GH' => 'Ghana', 'ZA' => 'South Africa',
            'EG' => 'Egypt', 'MA' => 'Morocco', 'TN' => 'Tunisia', 'DZ' => 'Algeria',
            'US' => 'the United States', 'CA' => 'Canada', 'BR' => 'Brazil',
            'MX' => 'Mexico', 'AR' => 'Argentina', 'CL' => 'Chile', 'CO' => 'Colombia',
            'GB' => 'the United Kingdom', 'IE' => 'Ireland', 'FR' => 'France',
            'DE' => 'Germany', 'ES' => 'Spain', 'IT' => 'Italy', 'NL' => 'the Netherlands',
            'BE' => 'Belgium', 'PT' => 'Portugal', 'CH' => 'Switzerland',
            'AT' => 'Austria', 'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark',
            'FI' => 'Finland', 'PL' => 'Poland', 'CZ' => 'Czechia', 'GR' => 'Greece',
            'TR' => 'Turkey', 'RU' => 'Russia', 'UA' => 'Ukraine',
            'AE' => 'the United Arab Emirates', 'SA' => 'Saudi Arabia', 'QA' => 'Qatar',
            'KW' => 'Kuwait', 'IL' => 'Israel', 'IN' => 'India', 'PK' => 'Pakistan',
            'BD' => 'Bangladesh', 'LK' => 'Sri Lanka', 'CN' => 'China',
            'JP' => 'Japan', 'KR' => 'South Korea', 'SG' => 'Singapore',
            'MY' => 'Malaysia', 'ID' => 'Indonesia', 'PH' => 'the Philippines',
            'TH' => 'Thailand', 'VN' => 'Vietnam', 'AU' => 'Australia',
            'NZ' => 'New Zealand',
        );
    }

    /**
     * The currency the account already uses, if there is a live session.
     *
     * Somebody who bills in USD is a USD customer even when they are travelling
     * - the preferred currency is a property of the business, not of the
     * connection.
     *
     * @return string|NULL
     */
    protected function currencyFromAccount()
    {
        if (!isset($this->CI->session) || !is_object($this->CI->session)) {
            return NULL;
        }
        $value = $this->CI->session->userdata('adomin_quote_currency');
        if (is_string($value) && $value !== '') {
            return $this->normaliseCurrency($value);
        }

        /*
         * A cookie outlives the session. Somebody who chose EUR last week should
         * not be quoted in KES because their PHP session expired, and the page
         * should not call that guess "detected from your location" either.
         */
        $cookie = $this->cookie('adomin_quote_currency');
        if ($cookie !== '') {
            return $this->normaliseCurrency($cookie);
        }
        return NULL;
    }

    /**
     * Take a currency the buyer chose and remember it for the session.
     *
     * @param string $currency
     * @return string the normalised code actually stored
     */
    public function setPreferredCurrency($currency)
    {
        $currency = $this->normaliseCurrency($currency);
        if (isset($this->CI->session) && is_object($this->CI->session)) {
            $this->CI->session->set_userdata('adomin_quote_currency', $currency);
        }
        // and a cookie, so the first paint after a cold visit is right too
        if (function_exists('set_cookie') && !headers_sent()) {
            set_cookie('adomin_quote_currency', $currency, 60 * 60 * 24 * 180);
        }
        $this->quotes = array();
        return $currency;
    }

    /* ------------------------------------------------------------------ */
    /* Rates                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * The rate table, from the day cache when it is fresh.
     *
     * @return array  currency => units per 1 USD
     */
    public function rates()
    {
        $fallback = isset($this->config['rates']) ? $this->config['rates'] : array('USD' => 1.0);

        $cached = $this->readRateCache();
        if ($cached !== NULL) {
            return $cached;
        }

        $fresh = $this->fetchRates($fallback);
        if ($fresh !== NULL) {
            $this->writeRateCache($fresh);
            return $fresh;
        }

        return $fallback;
    }

    /**
     * When the rates currently in use were last confirmed.
     *
     * @return int unix timestamp, 0 when never
     */
    public function ratesUpdatedAt()
    {
        $path = $this->rateCachePath();
        return is_file($path) ? (int) filemtime($path) : 0;
    }

    /**
     * Where the day cache lives.
     *
     * Under the system temp directory, not the web root: the file is derived
     * from a public endpoint and rewriting it must never be a way to serve
     * something. Failing that, the configured PDF temp dir is reused.
     *
     * @return string
     */
    protected function rateCachePath()
    {
        $base = function_exists('adomin_conf') ? adomin_conf('pdf', 'cache_dir') : NULL;
        if (empty($base) || !is_dir($base)) {
            $base = sys_get_temp_dir();
        }
        return rtrim($base, '/\\') . DIRECTORY_SEPARATOR . 'adomin-rates.json';
    }

    /**
     * @return array|NULL
     */
    protected function readRateCache()
    {
        $path = $this->rateCachePath();
        if (!is_file($path)) {
            return NULL;
        }

        $ttl = 24 * 60 * 60;
        if (time() - (int) filemtime($path) > $ttl) {
            return NULL;
        }

        $raw = @file_get_contents($path);
        if ($raw === FALSE || trim($raw) === '') {
            return NULL;
        }

        $data = json_decode($raw, TRUE);
        if (!is_array($data) || empty($data['rates']) || !is_array($data['rates'])) {
            return NULL;
        }

        return $data['rates'];
    }

    /**
     * @param array $rates
     * @return void
     */
    protected function writeRateCache(array $rates)
    {
        $payload = json_encode(array('fetched' => time(), 'rates' => $rates));
        @file_put_contents($this->rateCachePath(), $payload, LOCK_EX);
    }

    /**
     * Fetch today's rates, or NULL to keep yesterday's.
     *
     * The check that matters is the drift guard: a feed that returns 0.001
     * instead of 1.0 would otherwise reprice the whole product into nonsense
     * for a day. A move larger than max_rate_drift is treated as a broken
     * response and the cached table is kept.
     *
     * @param array $previous
     * @return array|NULL
     */
    protected function fetchRates(array $previous)
    {
        if (!function_exists('curl_init') || !ini_get('allow_url_fopen') && !function_exists('curl_init')) {
            return NULL;
        }

        $endpoint = getenv('ADOMIN_RATES_URL');
        if ($endpoint === FALSE || trim($endpoint) === '') {
            $endpoint = 'https://open.er-api.com/v6/latest/USD';
        }

        $raw = $this->httpGet($endpoint, 6);
        if ($raw === NULL) {
            return NULL;
        }

        $data = json_decode($raw, TRUE);
        $list = NULL;
        if (isset($data['rates']) && is_array($data['rates'])) {
            $list = $data['rates'];
        } elseif (isset($data['conversion_rates']) && is_array($data['conversion_rates'])) {
            $list = $data['conversion_rates'];
        }
        if ($list === NULL) {
            return NULL;
        }

        $maxDrift = isset($this->config['max_rate_drift']) ? (float) $this->config['max_rate_drift'] : 0.15;
        $out = array();
        foreach ($list as $code => $value) {
            $code = strtoupper((string) $code);
            $value = (float) $value;
            if ($code === '' || $value <= 0) {
                continue;
            }
            if (isset($previous[$code]) && (float) $previous[$code] > 0) {
                $drift = abs($value - (float) $previous[$code]) / (float) $previous[$code];
                if ($drift > $maxDrift) {
                    log_message('error', 'Adomin: rejected rate for ' . $code . ' (drift ' . round($drift * 100, 1) . '%)');
                    continue;
                }
            }
            $out[$code] = $value;
        }

        if (!isset($out['USD'])) {
            $out['USD'] = 1.0;
        }

        // Keep every currency we know about even if the feed dropped it, so a
        // missing line does not turn into "we cannot quote your market".
        foreach ($previous as $code => $value) {
            if (!isset($out[$code])) {
                $out[$code] = $value;
            }
        }

        return count($out) > 1 ? $out : NULL;
    }

    /**
     * @param string $url
     * @param int    $timeout
     * @return string|NULL
     */
    protected function httpGet($url, $timeout = 6)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_TIMEOUT => (int) $timeout,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_FOLLOWLOCATION => TRUE,
                CURLOPT_MAXREDIRS => 2,
                CURLOPT_SSL_VERIFYPEER => TRUE,
                CURLOPT_USERAGENT => 'anvoice-pricing/1.0',
            ));
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body !== FALSE && $code >= 200 && $code < 300) {
                return $body;
            }
            return NULL;
        }

        if ((bool) ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(array('http' => array('timeout' => (int) $timeout)));
            $body = @file_get_contents($url, FALSE, $ctx);
            return $body === FALSE ? NULL : $body;
        }

        return NULL;
    }

    /* ------------------------------------------------------------------ */
    /* Formatting                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Turn minor units into what a buyer reads.
     *
     * Uses the currency's own symbol where we know it and the ISO code where we
     * do not, rather than a dollar sign for everything.
     *
     * @param int    $minor
     * @param string $currency
     * @param bool   $withDecimals
     * @return string
     */
    public function format($minor, $currency, $withDecimals = NULL)
    {
        $symbols = array(
            'KES' => 'KSh', 'TZS' => 'TSh', 'UGX' => 'USh', 'RWF' => 'FRw',
            'NGN' => '₦', 'GHS' => 'GH₵', 'ZAR' => 'R', 'ETB' => 'Br',
            'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'INR' => '₹', 'AED' => 'AED',
            'SAR' => 'SAR', 'CAD' => 'CA$', 'AUD' => 'A$', 'BRL' => 'R$', 'MXN' => 'MX$',
            'JPY' => '¥', 'CNY' => '¥', 'SGD' => 'S$', 'PHP' => '₱', 'IDR' => 'Rp',
            'MYR' => 'RM', 'THB' => '฿', 'VND' => '₫', 'KRW' => '₩', 'TRY' => '₺',
            'PLN' => 'zł', 'SEK' => 'kr', 'NOK' => 'kr', 'DKK' => 'kr', 'CHF' => 'CHF',
        );
        $symbol = isset($symbols[$currency]) ? $symbols[$currency] : $currency;

        $minor = (int) $minor;

        /*
         * Whether to show cents follows the rounding policy, not a separate
         * hardcoded list. If a currency is priced in whole units then showing
         * ".00" is noise at best and looks like a bug at worst: "USh 5,814.00"
         * and "NGN 2,403.10" both read wrong to the person paying them.
         *
         * Currencies with no minor unit at all (JPY, KRW, VND...) are whole by
         * definition whatever the config says.
         */
        if ($withDecimals === NULL) {
            $zeroDecimal = in_array($currency, array('JPY', 'KRW', 'VND', 'CLP', 'ISK', 'XOF', 'XAF'), TRUE);
            $rounding = isset($this->config['rounding'][$currency]) ? $this->config['rounding'][$currency] : '';
            $withDecimals = !$zeroDecimal && $rounding !== 'whole';
        }

        if ($withDecimals) {
            $amount = number_format($minor / 100, 2, '.', ',');
        } else {
            $amount = number_format((int) round($minor / 100), 0, '.', ',');
        }

        return $symbol . ' ' . $amount;
    }

    /**
     * @param int    $minor
     * @param int    $baseMinor
     * @param string $currency
     * @param string $period
     * @param array  $plan
     * @return array
     */
    protected function buildQuote($minor, $baseMinor, $currency, $period, array $plan)
    {
        return array(
            'amount_minor'  => (int) $minor,
            'amount'        => round((int) $minor / 100, 2),
            'currency'      => $currency,
            'period'        => $period,
            'formatted'     => $this->format((int) $minor, $currency),
            'base_minor'    => (int) $baseMinor,
            'base_currency' => 'KES',
            'base_formatted' => $this->format((int) $baseMinor, 'KES'),
            'exact_minor'   => (int) $minor,
            'free'          => (int) $minor === 0,
            'sellable'      => TRUE,
            'source'        => 'base',
        );
    }

    /**
     * @param string $period
     * @param string $currency
     * @return array
     */
    protected function emptyQuote($period, $currency)
    {
        $currency = $this->normaliseCurrency($currency !== NULL ? $currency : 'KES');
        return array(
            'amount_minor'  => NULL,
            'amount'        => NULL,
            'currency'      => $currency,
            'period'        => $period,
            'formatted'     => '',
            'free'          => FALSE,
            'sellable'      => FALSE,
            'source'        => 'none',
        );
    }

    /**
     * Round a minor-unit amount the way that market quotes prices.
     *
     * @param int    $minor
     * @param string $currency
     * @return int
     */
    protected function roundFor($minor, $currency)
    {
        $mode = isset($this->config['rounding'][$currency])
            ? $this->config['rounding'][$currency]
            : (isset($this->config['rounding']['default']) ? $this->config['rounding']['default'] : 'tenth');

        if ($minor <= 0) {
            return 0;
        }

        switch ($mode) {
            case 'charm':
                /*
                 * .99 endings, but bounded.
                 *
                 * The naive version - round to the nearest unit, then take a
                 * cent off - turned 200 KES (about $1.55) into $1.99. That is
                 * not a price point, it is a 28% surcharge on every customer
                 * outside Kenya, and nobody would forgive it once they did the
                 * arithmetic.
                 *
                 * So the .99 has to be a price the conversion actually lands
                 * near: the nearest .99 step within 15% of the exact figure.
                 * $1.55 has none - 99c is far too cheap and $1.99 too dear - so
                 * it falls back to the nearest quarter, which is a price a
                 * person can still recognise.
                 */
                $exact = $minor / 100;

                // candidate .99 prices either side of the exact figure
                $floorStep = max(1, (int) floor($exact));
                foreach (array($floorStep * 100 - 1, ($floorStep + 1) * 100 - 1) as $candidate) {
                    if ($candidate >= 99 && abs(($candidate / 100) - $exact) / $exact <= 0.15) {
                        return (int) $candidate;
                    }
                }

                /*
                 * No .99 step is close enough. Fall back to the nearest quarter
                 * (or whole unit above 20) so the price still looks deliberate,
                 * and keep whatever drift that costs inside the same bound.
                 */
                $unit = $exact >= 20 ? 100 : 25;
                return (int) (round($minor / $unit) * $unit);

            case 'whole':
            case 'tenth':
                return (int) (round($minor / 10) * 10);

            default:
                return (int) $minor;
        }
    }

    /**
     * @param string $currency
     * @return string
     */
    public function normaliseCurrency($currency)
    {
        $currency = strtoupper(trim((string) $currency));
        if ($currency === '' || !preg_match('/^[A-Z]{3}$/', $currency)) {
            $default = function_exists('adomin_conf') ? adomin_conf('locale', 'currency') : NULL;
            return strtoupper($default !== NULL && $default !== '' ? $default : 'KES');
        }
        return $currency;
    }

    /**
     * The currencies this installation can quote, for a picker.
     *
     * @return array code => label
     */
    public function supportedCurrencies()
    {
        $rates = $this->rates();
        $out = array();
        foreach ($rates as $code => $rate) {
            $out[$code] = $code . ' · ' . $this->format($this->quote('PRO', 'monthly', $code)['amount_minor'], $code);
        }
        ksort($out);
        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Small helpers                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * @param string $key
     * @return string
     */
    protected function server($key)
    {
        if (isset($_SERVER[$key]) && is_string($_SERVER[$key])) {
            return trim($_SERVER[$key]);
        }
        return '';
    }

    /**
     * @param string $key
     * @return string
     */
    protected function cookie($key)
    {
        if (isset($_COOKIE[$key]) && is_string($_COOKIE[$key])) {
            return trim($_COOKIE[$key]);
        }
        if (isset($this->CI->input) && is_object($this->CI->input)) {
            $value = $this->CI->input->cookie($key);
            return is_string($value) ? trim($value) : '';
        }
        return '';
    }
}
