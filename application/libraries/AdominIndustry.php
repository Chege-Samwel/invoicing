<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Industry, and the starter products that go with it.
 *
 * Registering asks a business what it does. This turns that answer into rows in
 * the company's own item list once, at the moment the account is created, and
 * keeps the answer on the account afterwards so the answer can be changed and
 * the list extended later.
 *
 * Why a library rather than more model methods: the seeding has to run from two
 * very different places - a brand-new registration with no session at all, and
 * a logged-in company settings page where USER_ID and SELECTED_COMPANY_ID are
 * defined - and CI helpers reach neither cleanly. The library takes the user
 * and company ids as arguments instead of reading constants, which is also what
 * makes it testable outside a request.
 *
 * The one rule worth stating twice: seeding is a copy, not a subscription. Once
 * the rows exist they are ordinary items and deleting one sticks. `restore()`
 * is the only thing that puts them back, and it is only ever called because
 * somebody pressed a button.
 */
class AdominIndustry
{
    /** @var object */
    private $CI;

    /** Units the schema actually offers; anything else becomes 'number'. */
    private static $units = array('kilogram', 'number', 'days', 'months');

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->config->load('industries', TRUE);
    }

    /**
     * Every industry, keyed, as `key => ['label','blurb','products']`.
     *
     * @return array
     */
    public function all()
    {
        $list = $this->CI->config->item('industries', 'industries');

        return is_array($list) ? $list : array();
    }

    /**
     * `key => label`, for a `<select>`. Ordered as the config file orders it.
     *
     * @return array
     */
    public function options()
    {
        $out = array();
        foreach ($this->all() as $key => $industry) {
            $out[$key] = isset($industry['label']) ? $industry['label'] : $key;
        }

        return $out;
    }

    /**
     * The industry the registration form starts on.
     *
     * @return string
     */
    public function fallback()
    {
        $default = $this->CI->config->item('industries_default', 'industries');

        return $this->isValid($default) ? $default : 'general';
    }

    /**
     * @param  string $key
     * @return bool
     */
    public function isValid($key)
    {
        $all = $this->all();

        return is_string($key) && $key !== '' && isset($all[$key]);
    }

    /**
     * The key stored for a user, or the fallback when nothing is stored.
     *
     * @param  int $userId
     * @return string
     */
    public function forUser($userId)
    {
        $userId = (int) $userId;
        if ($userId < 1) {
            return $this->fallback();
        }

        $stored = $this->CI->db
            ->select('value')
            ->where('for_user', $userId)
            ->where('_key', 'opt_industry')
            ->limit(1)
            ->get('value_store')
            ->row_array();

        $key = isset($stored['value']) ? (string) $stored['value'] : '';

        return $this->isValid($key) ? $key : $this->fallback();
    }

    /**
     * Remember the answer on the account. Written as a value_store row so it
     * appears wherever the other per-user options do, and survives a login on
     * another device without a schema change.
     *
     * @param  int    $userId
     * @param  string $key
     * @return bool
     */
    public function remember($userId, $key)
    {
        $userId = (int) $userId;
        if ($userId < 1 || ! $this->isValid($key)) {
            return FALSE;
        }

        $exists = $this->CI->db
            ->where('for_user', $userId)
            ->where('_key', 'opt_industry')
            ->count_all_results('value_store') > 0;

        if ($exists) {
            return (bool) $this->CI->db
                ->where('for_user', $userId)
                ->where('_key', 'opt_industry')
                ->update('value_store', array('value' => $key));
        }

        return (bool) $this->CI->db->insert('value_store', array(
            'for_user' => $userId,
            '_key'     => 'opt_industry',
            'value'    => $key,
        ));
    }

    /**
     * The starter products for an industry, ready to insert.
     *
     * @param  string $key
     * @return array  list of ['name','quantity_type']
     */
    public function products($key)
    {
        if ( ! $this->isValid($key)) {
            $key = $this->fallback();
        }

        $all = $this->all();
        $rows = isset($all[$key]['products']) && is_array($all[$key]['products'])
            ? $all[$key]['products']
            : array();

        $out = array();
        $seen = array();

        foreach ($rows as $row) {
            $name = isset($row[0]) ? trim((string) $row[0]) : '';
            $unit = isset($row[1]) ? (string) $row[1] : 'number';

            if ($name === '') {
                continue;
            }

            // the same name twice in one trade would double the suggestion
            $fingerprint = strtolower($name);
            if (isset($seen[$fingerprint])) {
                continue;
            }
            $seen[$fingerprint] = TRUE;

            if ( ! in_array($unit, self::$units, TRUE)) {
                $unit = 'number';
            }

            $out[] = array('name' => $name, 'quantity_type' => $unit);
        }

        return $out;
    }

    /**
     * Copy an industry's products into one company's item list.
     *
     * Names already present for that company are skipped, so registering,
     * restoring and switching industries can all call this without producing
     * duplicates.
     *
     * @param  int    $userId
     * @param  int    $companyId
     * @param  string $industry
     * @return int    how many rows were added
     */
    public function seed($userId, $companyId, $industry)
    {
        $userId = (int) $userId;
        $companyId = (int) $companyId;

        if ($userId < 1 || $companyId < 1) {
            return 0;
        }

        $products = $this->products($industry);
        if ( ! $products) {
            return 0;
        }

        $existing = $this->CI->db
            ->select('name')
            ->where('for_user', $userId)
            ->where('for_company', $companyId)
            ->get('items')
            ->result_array();

        $have = array();
        foreach ($existing as $row) {
            /*
             * setItem() stores the name through htmlspecialchars(), so a product
             * named "Sockets & switches" is already "Sockets &amp; switches" in
             * the table. Comparing raw against stored would treat every name
             * with punctuation as new and insert it again on every run.
             */
            $have[strtolower(trim(html_entity_decode((string) $row['name'], ENT_QUOTES, 'UTF-8')))] = TRUE;
        }

        $added = 0;
        foreach ($products as $product) {
            if (isset($have[strtolower($product['name'])])) {
                continue;
            }

            $ok = $this->CI->db->insert('items', array(
                'for_user'      => $userId,
                'for_company'   => $companyId,
                'name'          => htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'),
                'quantity_type' => $product['quantity_type'],
                // a starter product carries no price: a printed wrong price is
                // worse than an empty field that asks for one
                'single_price'  => '',
                'currency'      => '',
            ));

            if ($ok) {
                $have[strtolower($product['name'])] = TRUE;
                $added++;
            } else {
                log_message('error', 'AdominIndustry: could not seed item: ' . print_r($this->CI->db->error(), TRUE));
            }
        }

        return $added;
    }

    /**
     * The company a user's first registration should seed into.
     *
     * Registration does not create a company - the schema asks for a KRA PIN
     * and a name, which an onboarding form right after signup collects. The
     * item list therefore has to wait for that company to exist. This returns
     * the oldest company on the account, which on a fresh registration is the
     * one that gets created first.
     *
     * @param  int $userId
     * @return int company id, or 0 when the account has none yet
     */
    public function firstCompanyId($userId)
    {
        $userId = (int) $userId;
        if ($userId < 1) {
            return 0;
        }

        $row = $this->CI->db
            ->select('id')
            ->where('for_user', $userId)
            ->order_by('id', 'ASC')
            ->limit(1)
            ->get('firms_users')
            ->row_array();

        return isset($row['id']) ? (int) $row['id'] : 0;
    }

    /**
     * Seed whichever company on the account has no items yet.
     *
     * This is what "finish onboarding" calls: it is idempotent, so it is safe
     * to run on every login until the first company exists, and once the list
     * has rows it does nothing at all. That is what makes it safe to call from
     * a dashboard hit - the account that deleted all five starter rows still
     * gets none back, because the list was seeded when the company appeared.
     *
     * @param  int $userId
     * @return int rows added
     */
    public function seedPending($userId)
    {
        $userId = (int) $userId;
        $companyId = $this->firstCompanyId($userId);

        if ($companyId < 1) {
            return 0;
        }

        // a marker means "already offered", even if every row was later deleted
        $marked = $this->CI->db
            ->select('value')
            ->where('for_user', $userId)
            ->where('_key', 'opt_industrySeeded')
            ->limit(1)
            ->get('value_store')
            ->row_array();

        if ( ! empty($marked['value'])) {
            return 0;
        }

        // rows were added by hand before the industry existed: do not pile on
        $count = $this->CI->db
            ->where('for_user', $userId)
            ->where('for_company', $companyId)
            ->count_all_results('items');

        $added = $count > 0 ? 0 : $this->seed($userId, $companyId, $this->forUser($userId));

        $this->markSeeded($userId);

        return $added;
    }

    /**
     * Record that the starter set has been offered for this account.
     *
     * @param  int $userId
     * @return void
     */
    public function markSeeded($userId)
    {
        $userId = (int) $userId;
        if ($userId < 1) {
            return;
        }

        $exists = $this->CI->db
            ->where('for_user', $userId)
            ->where('_key', 'opt_industrySeeded')
            ->count_all_results('value_store') > 0;

        if ($exists) {
            $this->CI->db
                ->where('for_user', $userId)
                ->where('_key', 'opt_industrySeeded')
                ->update('value_store', array('value' => '1'));

            return;
        }

        $this->CI->db->insert('value_store', array(
            'for_user' => $userId,
            '_key'     => 'opt_industrySeeded',
            'value'    => '1',
        ));
    }

    /**
     * Add an industry's products to a company that already has a list.
     *
     * This is the "add these products too" button on company settings - the
     * one an account uses when it takes on a second kind of work. It does not
     * remove anything, which is why it is safe: what the account deleted last
     * time stays deleted until it is seeded again on purpose.
     *
     * @param  int    $userId
     * @param  int    $companyId
     * @param  string $industry
     * @return int
     */
    public function add($userId, $companyId, $industry)
    {
        return $this->seed($userId, $companyId, $industry);
    }
}
