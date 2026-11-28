<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Invoice statuses: the list, the wording, and who is allowed to set them.
 *
 * Three things live here rather than in a controller, because all three are
 * asked from more than one place and they have to agree:
 *
 *   1. The status list is editable per account. A business that wants
 *      "Delivered" or "Awaiting payment" instead of our words can rename the
 *      labels - the stored key never changes, so nothing downstream cares.
 *
 *   2. A client accepting or refusing through a share link does NOT change the
 *      status. It files a request, and the business confirms it. "Accepted" is
 *      a statement the issuing business makes about its own document; letting
 *      an anonymous visitor holding a forwarded URL make it would be a claim
 *      with no one behind it.
 *
 *   3. The words themselves are translated in one place, so the app, the print
 *      and the confirmation mail cannot drift apart.
 *
 * Stored keys, deliberately short and stable - they are column values, not
 * labels:
 *
 *   draft     the document exists but is not issued
 *   issued    sent to the client (the default for a new invoice)
 *   accepted  the business confirmed the client took it
 *   refused   the business confirmed the client rejected it
 *   canceled  withdrawn by the business
 *
 * "Payment status" is a separate axis (paid / unpaid / partly paid) and is not
 * part of this list: an invoice can be accepted and still unpaid.
 */
class AdominStatus
{
    /**
     * The keys, in the order they progress through the pipeline.
     *
     * @var array
     */
    protected $keys = array('draft', 'issued', 'accepted', 'refused', 'canceled');

    /**
     * Statuses a client is allowed to request from a share link.
     *
     * Only these two: a client cannot declare its own invoice "issued", and
     * certainly cannot cancel one.
     *
     * @var array
     */
    protected $requestable = array('accepted', 'refused');

    /**
     * The value-store key holding the account's label overrides.
     *
     * @var string
     */
    const LABEL_KEY = 'opt_invStatusLabels';

    /**
     * @return array the keys, in pipeline order
     */
    public function keys()
    {
        return $this->keys;
    }

    /**
     * @return array statuses a share link may ask for
     */
    public function requestable()
    {
        return $this->requestable;
    }

    /**
     * Is this a status the app knows about?
     *
     * Every write path goes through here. A status is a column value that
     * decides which section of the app a document appears in, so accepting an
     * arbitrary string from a request would file an invoice somewhere no view
     * looks for it - it would simply vanish from every list.
     *
     * @param string $status
     * @return bool
     */
    public function isKnown($status)
    {
        return in_array((string) $status, $this->keys, TRUE);
    }

    /**
     * Can a client ask for this one through a share link?
     *
     * @param string $status
     * @return bool
     */
    public function isRequestable($status)
    {
        return in_array((string) $status, $this->requestable, TRUE);
    }

    /**
     * The default wording for every key, translated.
     *
     * Falls back to the key itself when the language file has no entry, which
     * is what the list already did - but through lang('status_' . $key) at
     * every call site, so an unknown key printed "status_whatever" in the
     * middle of a table.
     *
     * @return array key => label
     */
    public function defaults()
    {
        $labels = array();
        foreach ($this->keys as $key) {
            $label = lang('status_' . $key);
            $labels[$key] = ($label === 'status_' . $key || $label === '') ? ucfirst($key) : $label;
        }
        return $labels;
    }

    /**
     * The account's labels: defaults with the saved overrides applied.
     *
     * Stored as a JSON object in one value-store row rather than five, so a
     * half-saved form cannot leave the account with three renamed statuses and
     * two default ones.
     *
     * @param mixed $stored the raw value-store string, if the caller has it
     * @return array key => label
     */
    public function labels($stored = NULL)
    {
        $labels = $this->defaults();

        if ($stored === NULL || trim((string) $stored) === '') {
            return $labels;
        }

        $saved = json_decode((string) $stored, TRUE);
        if (!is_array($saved)) {
            // a corrupt row must not blank the column: keep the defaults
            return $labels;
        }

        foreach ($labels as $key => $default) {
            if (isset($saved[$key]) && trim((string) $saved[$key]) !== '') {
                // the value store runs everything through htmlspecialchars(),
                // so a label stored as "Client's copy" comes back as
                // "Client&#039;s copy" and would print literally
                $labels[$key] = html_entity_decode(trim((string) $saved[$key]), ENT_QUOTES, 'UTF-8');
            }
        }

        return $labels;
    }

    /**
     * The label for one key, from a label set the caller already loaded.
     *
     * @param array  $labels
     * @param string $key
     * @return string
     */
    public function label(array $labels, $key)
    {
        return isset($labels[$key]) ? $labels[$key] : ucfirst((string) $key);
    }

    /**
     * Turn submitted labels into the value that gets stored.
     *
     * Returns NULL when nothing differs from the defaults, so the row can be
     * deleted instead of storing a copy of the defaults - which would then
     * freeze the wording at whatever the language file said on the day the
     * account first saved the form.
     *
     * @param array $submitted key => label
     * @return string|NULL json, or NULL for "no overrides"
     */
    public function encodeLabels(array $submitted)
    {
        $defaults = $this->defaults();
        $overrides = array();

        foreach ($defaults as $key => $default) {
            if (!isset($submitted[$key])) {
                continue;
            }
            $value = trim((string) $submitted[$key]);
            if ($value !== '' && $value !== $default) {
                $overrides[$key] = $value;
            }
        }

        return $overrides ? json_encode($overrides) : NULL;
    }

    /**
     * The status a new invoice starts in.
     *
     * @return string
     */
    public function initial()
    {
        return 'issued';
    }

    /**
     * Every key the app may write to invoices.status from a settings screen.
     *
     * Same as keys() today; kept as its own method because the two questions
     * are different - one is "what can a document be", the other "what may a
     * human set by hand" - and they are only equal by coincidence.
     *
     * @return array
     */
    public function settable()
    {
        return $this->keys;
    }
}
