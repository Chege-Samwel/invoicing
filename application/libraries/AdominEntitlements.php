<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * What this account is allowed to do, right now.
 *
 * This is the one place that answers that question. Before it existed the
 * answer was spread across four files: a hardcoded 5 in Plans::,
 * `$this->planUnits['num_invoices'] > 0` in the invoice controller, a firm
 * count in USER_Controller, and - for clients and users - nothing at all.
 *
 * Three things it fixes:
 *
 * 1. A paid plan used to grant a *countdown* of invoices: num_invoices was
 *    decremented as invoices were issued and never refilled, so "200 invoices"
 *    bought in January was exhausted whenever it ran out, not on 1 February.
 *    Pro is now unlimited, which the countdown cannot express, so the limit is
 *    read from config and the usage counted from the invoices table.
 *
 * 2. "-1 invoices left" was the only signal that an account was over its cap,
 *    and the UI treated it as a number. An account with no plan is Free, and
 *    Free has a cap of 5 a month - which is what the marketing says.
 *
 * 3. Nothing enforced a client cap or a user cap at all.
 *
 * The plan is looked up per account and cached for the request; the usage
 * counts are lazy, because most requests never ask.
 */
class AdominEntitlements
{

    private $CI;

    /** @var array|null the plan row resolved for this account */
    private $plan = NULL;

    /** @var array usage counters, filled on demand */
    private $usage = array();

    public function __construct()
    {
        $this->CI = & get_instance();
        if (!isset($this->CI->adominpricing) || !is_object($this->CI->adominpricing)) {
            $this->CI->load->library('AdominPricing');
        }
    }

    /* ------------------------------------------------------------------ */
    /* Which plan is this?                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * The plan this account is on.
     *
     * A row in firms_plans that covers today wins. No row, or a row that has
     * expired, means Free - an account that has never paid is not in an error
     * state, it is on the plan we advertise as free.
     *
     * @return array the plan from adomin_plans.php, with 'source' and, for a
     *               paid plan, the expires timestamp
     */
    public function plan()
    {
        if ($this->plan !== NULL) {
            return $this->plan;
        }

        $pricing = $this->CI->adominpricing;
        $free = $pricing->plan('FREE');

        $row = $this->activePlanRow();
        if ($row === NULL) {
            $free['source'] = 'free';
            $free['expires'] = NULL;
            $free['trial'] = FALSE;
            return $this->plan = $free;
        }

        $plan = $pricing->plan($row['plan_type']);
        if ($plan === NULL) {
            // A plan_type nobody recognises - a row written by an older build,
            // or a typo. Free is the safe reading: it cannot hand out features
            // the account has not paid for, and it does not break the page.
            log_message('error', 'AdominEntitlements: unknown plan_type "' . $row['plan_type'] . '" for user ' . $this->userId());
            $free['source'] = 'unknown';
            $free['expires'] = (int) $row['to_date'];
            $free['trial'] = FALSE;
            return $this->plan = $free;
        }

        $plan['source'] = 'paid';
        $plan['expires'] = (int) $row['to_date'];
        $plan['trial'] = $this->isTrialRow($row);
        return $this->plan = $plan;
    }

    /**
     * The row in firms_plans that is in force today, if any.
     *
     * @return array|NULL
     */
    protected function activePlanRow()
    {
        if ($this->userId() <= 0) {
            return NULL;
        }
        $now = time();
        $sql = 'SELECT plan_type, from_date, to_date, num_invoices, num_firms, sponsored
                FROM firms_plans
                WHERE for_user = ? AND from_date <= ? AND to_date >= ?
                ORDER BY id DESC LIMIT 1';
        $row = $this->CI->db->query($sql, array($this->userId(), $now, $now))->row_array();
        return empty($row) ? NULL : $row;
    }

    /**
     * Was this row granted as a trial rather than bought?
     *
     * Nothing records that today, so it is inferred: a sponsored row, or a Pro
     * row whose length is the trial length and which carries no invoice count.
     * The column this really wants is a `source` on firms_plans; when that
     * exists, read it here and delete the guessing.
     *
     * @param array $row
     * @return bool
     */
    protected function isTrialRow(array $row)
    {
        if (!empty($row['sponsored'])) {
            return TRUE;
        }
        $trial = $this->CI->adominpricing->trial();
        if (empty($trial['enabled'])) {
            return FALSE;
        }
        $length = (int) $row['to_date'] - (int) $row['from_date'];
        // within a day of the trial length, and not sold with an invoice count
        return abs($length - ((int) $trial['days'] * 86400)) < 86400
            && (int) $row['num_invoices'] === -1;
    }

    /**
     * The plan key, for code that only needs the name.
     *
     * @return string FREE | PRO | BUSINESS
     */
    public function planKey()
    {
        $plan = $this->plan();
        return isset($plan['key']) ? $plan['key'] : 'FREE';
    }

    /**
     * A capability flag: watermark, recurring, custom_branding...
     *
     * @param string $flag
     * @return mixed NULL when the flag does not exist
     */
    public function flag($flag)
    {
        $flags = $this->CI->adominpricing->flags($this->planKey());
        return isset($flags[$flag]) ? $flags[$flag] : NULL;
    }

    /* ------------------------------------------------------------------ */
    /* Limits and usage                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * The limit for one counter: NULL = unlimited, 0 = not offered.
     *
     * @param string $what invoices_per_month | clients | firms | users
     * @return int|NULL
     */
    public function limit($what)
    {
        return $this->CI->adominpricing->limit($this->planKey(), $what);
    }

    /**
     * How many are in use now.
     *
     * Invoices are counted for the current calendar month, because the limit is
     * "per month". Clients and users are absolute counts.
     *
     * @param string $what
     * @return int
     */
    public function used($what)
    {
        if (isset($this->usage[$what])) {
            return $this->usage[$what];
        }

        $count = 0;
        switch ($what) {
            case 'invoices_per_month':
                $count = $this->countInvoicesThisMonth();
                break;
            case 'clients':
                $count = $this->countRows('clients', 'for_user');
                break;
            case 'users':
                // the owner is a user; employees are the others
                $count = 1 + $this->countRows('employees', 'for_user');
                break;
            case 'firms':
                $count = $this->countFirms();
                break;
        }

        return $this->usage[$what] = $count;
    }

    /**
     * @return int
     */
    protected function countInvoicesThisMonth()
    {
        if ($this->userId() <= 0) {
            return 0;
        }
        /*
         * Calendar month, not "the last 30 days". A cap described as "5 invoices
         * a month" that resets on the anniversary of signup is not the thing
         * anybody thinks they bought, and it makes the reset date invisible.
         *
         * The invoices table carries both date_create (the invoice date the
         * business chose) and created (when the row was written). The cap is on
         * creation, not on the date on the paper: an invoice backdated to last
         * month still cost us this month's allowance, and one dated forward must
         * not pre-spend next month's.
         */
        $start = strtotime('first day of this month 00:00:00');
        $sql = 'SELECT COUNT(id) AS num FROM invoices WHERE for_user = ? AND created >= ?';
        $row = $this->CI->db->query($sql, array($this->userId(), $start))->row_array();
        return isset($row['num']) ? (int) $row['num'] : 0;
    }

    /**
     * @param string $table
     * @param string $column
     * @return int
     */
    protected function countRows($table, $column)
    {
        if ($this->userId() <= 0) {
            return 0;
        }
        $sql = 'SELECT COUNT(id) AS num FROM ' . $table . ' WHERE ' . $column . ' = ?';
        $row = $this->CI->db->query($sql, array($this->userId()))->row_array();
        return isset($row['num']) ? (int) $row['num'] : 0;
    }

    /**
     * Firms are counted per user, not per session selection.
     *
     * The business profiles live in firms_users (one row per profile, joined to
     * firms_translations for its name), not in a table called `firms`. Counting
     * the wrong place returned 0 for everybody, which would have silently
     * removed the multi-firm limit rather than enforcing it.
     *
     * @return int
     */
    protected function countFirms()
    {
        if ($this->userId() <= 0) {
            return 0;
        }
        $sql = 'SELECT COUNT(id) AS num FROM firms_users WHERE for_user = ? AND is_deleted = 0';
        $row = $this->CI->db->query($sql, array($this->userId()))->row_array();
        return isset($row['num']) ? (int) $row['num'] : 0;
    }

    /* ------------------------------------------------------------------ */
    /* The questions the controllers actually ask                          */
    /* ------------------------------------------------------------------ */

    /**
     * May this account create another invoice?
     *
     * @return bool
     */
    public function canCreateInvoice()
    {
        $limit = $this->limit('invoices_per_month');
        if ($limit === NULL) {
            return TRUE;
        }
        return $this->used('invoices_per_month') < $limit;
    }

    /**
     * @return bool
     */
    public function canCreateClient()
    {
        $limit = $this->limit('clients');
        if ($limit === NULL) {
            return TRUE;
        }
        return $this->used('clients') < $limit;
    }

    /**
     * @return bool
     */
    public function canCreateUser()
    {
        $limit = $this->limit('users');
        if ($limit === NULL) {
            return TRUE;
        }
        return $this->used('users') < $limit;
    }

    /**
     * @return bool
     */
    public function canCreateFirm()
    {
        $limit = $this->limit('firms');
        if ($limit === NULL) {
            return TRUE;
        }
        return $this->used('firms') < $limit;
    }

    /**
     * How many more of something may be created. NULL when unlimited, which
     * reads better in a UI than a large number.
     *
     * @param string $what
     * @return int|NULL
     */
    public function remaining($what)
    {
        $limit = $this->limit($what);
        if ($limit === NULL) {
            return NULL;
        }
        return max(0, $limit - $this->used($what));
    }

    /**
     * A sentence for the UI, for whichever limit was hit.
     *
     * Written here rather than in three controllers so the wording, and the
     * upgrade path, are identical wherever the wall is met.
     *
     * @param string $what
     * @return string
     */
    public function limitMessage($what)
    {
        $limit = $this->limit($what);
        $names = array(
            'invoices_per_month' => 'invoices this month',
            'clients' => 'clients',
            'users' => 'users',
            'firms' => 'business profiles',
        );
        $name = isset($names[$what]) ? $names[$what] : $what;

        if ($limit === 0) {
            return 'Your plan does not include ' . $name . '. Upgrade to add them.';
        }
        return 'You have used all ' . $limit . ' ' . $name . ' on the '
            . $this->plan()['name'] . ' plan. Upgrade to Pro for unlimited.';
    }

    /* ------------------------------------------------------------------ */
    /* Compatibility with the old planUnits array                          */
    /* ------------------------------------------------------------------ */

    /**
     * The shape USER_Controller has always handed to views.
     *
     * Kept so existing screens keep working, but no longer a countdown: a
     * positive number means "room left", and for an unlimited plan it is a
     * large number rather than -1, because templates printed it directly.
     *
     * @return array num_invoices, num_firms
     */
    public function planUnits()
    {
        $invoices = $this->remaining('invoices_per_month');
        $firms = $this->remaining('firms');
        return array(
            'num_invoices' => $invoices === NULL ? 999999 : $invoices,
            'num_firms' => $firms === NULL ? 999999 : $firms,
            'unlimited_invoices' => $invoices === NULL,
        );
    }

    /**
     * Forget the cached plan - call after a plan change so the next question
     * gets the new answer inside the same request.
     *
     * @return void
     */
    public function reset()
    {
        $this->plan = NULL;
        $this->usage = array();
    }

    /**
     * @return int
     */
    protected function userId()
    {
        return defined('USER_ID') ? (int) USER_ID : 0;
    }
}
