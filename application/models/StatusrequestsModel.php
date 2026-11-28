<?php

/*
 * Requests from clients: "I accept this invoice" / "I don't".
 *
 * A request is NOT a status. The share link is unauthenticated - anyone the
 * client forwards it to can open it - so an acceptance recorded there is a claim
 * by an unnamed visitor, not by the client. The business confirms or declines it
 * in the app, and only that confirmation moves invoices.status.
 *
 * The row is also the audit trail: who asked, from where, when, and on which
 * version of the token. Revoking a share link rotates the token, so a request
 * filed against an old token stays readable but can no longer be acted on
 * blindly - the confirmation screen shows the token it came from.
 */
class StatusrequestsModel extends CI_Model
{
    /**
     * @var string
     */
    protected $table = 'invoice_status_requests';

    /**
     * Whether the table exists.
     *
     * database/adomin-updates.sql creates it. The app is deployed against
     * databases that may not have run that file yet, and a missing table must
     * degrade - the share page keeps working, the queue simply reports nothing
     * rather than throwing a 500 at an anonymous visitor.
     *
     * @var bool|NULL
     */
    protected $available = NULL;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return bool
     */
    public function available()
    {
        if ($this->available === NULL) {
            $this->available = $this->db->table_exists($this->table);
        }
        return $this->available;
    }

    /**
     * File a client's request against a share link.
     *
     * One open request per document: a client who changes their mind, or who
     * clicks twice, replaces the pending request instead of stacking a queue of
     * contradictions for the business to adjudicate.
     *
     * @param int    $invoiceId
     * @param int    $forUser
     * @param string $status        accepted | refused
     * @param string $reason        the client's own words, for a refusal
     * @param string $token         the uniqid the request arrived on
     * @return bool                 FALSE when the table is missing
     */
    public function request($invoiceId, $forUser, $status, $reason = '', $token = '')
    {
        if (!$this->available()) {
            return FALSE;
        }

        $this->resolve($invoiceId, 'superseded');

        $this->db->insert($this->table, array(
            'for_invoice' => (int) $invoiceId,
            'for_user' => (int) $forUser,
            'status' => (string) $status,
            'reason' => mb_substr(trim((string) $reason), 0, 500),
            'token' => (string) $token,
            'state' => 'pending',
            'ip' => substr($this->input->ip_address(), 0, 45),
            'user_agent' => substr((string) $this->input->user_agent(), 0, 255),
            'time' => time(),
        ));

        if ($this->db->affected_rows() < 1) {
            log_message('error', 'Could not file invoice status request: ' . print_r($this->db->error(), TRUE));
            return FALSE;
        }

        return TRUE;
    }

    /**
     * The open request for one document, if there is one.
     *
     * @param int $invoiceId
     * @return array|NULL
     */
    public function pending($invoiceId)
    {
        if (!$this->available()) {
            return NULL;
        }

        $this->db->where('for_invoice', (int) $invoiceId);
        $this->db->where('state', 'pending');
        $this->db->order_by('id', 'desc');
        $this->db->limit(1);
        $row = $this->db->get($this->table)->row_array();

        return empty($row) ? NULL : $row;
    }

    /**
     * Every open request for an account, newest first.
     *
     * Joined to the invoice so the queue can name the document without a query
     * per row - a business with forty pending requests would otherwise open
     * forty connections to draw one table.
     *
     * @param int $forUser
     * @param int $limit
     * @return array
     */
    public function pendingForUser($forUser, $limit = 100)
    {
        if (!$this->available()) {
            return array();
        }

        $this->db->select('invoice_status_requests.*, invoices.inv_number, invoices.inv_type, invoices.status AS invoice_status, invoices.final_total, invoices.inv_currency');
        $this->db->join('invoices', 'invoices.id = invoice_status_requests.for_invoice', 'inner');
        $this->db->where('invoice_status_requests.for_user', (int) $forUser);
        $this->db->where('invoice_status_requests.state', 'pending');
        $this->db->where('invoices.is_deleted', 0);
        $this->db->order_by('invoice_status_requests.id', 'desc');
        $this->db->limit((int) $limit);

        return $this->db->get($this->table)->result_array();
    }

    /**
     * How many requests are waiting - for the badge in the menu.
     *
     * @param int $forUser
     * @return int
     */
    public function pendingCount($forUser)
    {
        if (!$this->available()) {
            return 0;
        }

        $this->db->where('for_user', (int) $forUser);
        $this->db->where('state', 'pending');
        return (int) $this->db->count_all_results($this->table);
    }

    /**
     * Close a request: the business confirmed it, declined it, or the client
     * replaced it.
     *
     * @param int    $id
     * @param string $state confirmed | declined | superseded
     * @param int    $byUser
     * @return bool
     */
    public function resolve($id, $state, $byUser = 0)
    {
        if (!$this->available()) {
            return FALSE;
        }

        $this->db->where('id', (int) $id);
        $this->db->where('state', 'pending');
        $this->db->update($this->table, array(
            'state' => (string) $state,
            'resolved_by' => (int) $byUser,
            'resolved_at' => time(),
        ));

        return $this->db->affected_rows() > 0;
    }

    /**
     * One request, scoped to the account that owns it.
     *
     * The account scope is the point: a request id is a small integer, and
     * without it any logged-in user could confirm another business's request by
     * walking the numbers.
     *
     * @param int $id
     * @param int $forUser
     * @return array|NULL
     */
    public function find($id, $forUser)
    {
        if (!$this->available()) {
            return NULL;
        }

        $this->db->where('id', (int) $id);
        $this->db->where('for_user', (int) $forUser);
        $this->db->limit(1);
        $row = $this->db->get($this->table)->row_array();

        return empty($row) ? NULL : $row;
    }
}
