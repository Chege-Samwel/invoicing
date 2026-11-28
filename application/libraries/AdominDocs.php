<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/*
  |--------------------------------------------------------------------------
  | AdominDocs — shared document (HTML) builder
  |--------------------------------------------------------------------------
  |
  | One place that knows how an invoice becomes a printable document, so the
  | logged-in preview, the PDF download, the public "send via link" page and
  | the public PDF all render identically.
  |
  | Kept free of session/permission checks on purpose: the callers decide who
  | may see what, this class only builds markup.
 */
class AdominDocs
{

    /** @var CI_Controller */
    private $CI;

    /** @var string document-root relative css files inlined into every PDF */
    private $printCss = array(
        'assets/users/css/invoices-templates.css',
        'assets/users/css/invoice-banner.css',
        'assets/users/css/invoice-adoc.css',
        'assets/users/css/pdf-base.css',
    );

    public function __construct()
    {
        $this->CI = & get_instance();
        if (!function_exists('adomin_conf')) {
            $this->CI->load->helper('adomin');
        }
    }

    /**
     * Resolve the invoice template the user picked in Settings > Invoices.
     *
     * @param string|null $explicit
     * @param int|null    $userId used when there is no session (public links)
     * @return string template file name without extension
     */
    /**
     * Apply the label renames to a translation row.
     *
     * The table of renames lives in the document helper, because the HTML views
     * and the public share pages need it too and they never load this library.
     * This is the thin wrapper the PDF path uses.
     *
     * @param array|null $translation
     * @return array
     */
    public function normaliseLabels($translation)
    {
        return function_exists('adomin_normalise_labels')
            ? adomin_normalise_labels($translation)
            : (is_array($translation) ? $translation : array());
    }

    public function invoiceTemplate($explicit = NULL, $userId = NULL)
    {
        $templates = $this->CI->config->item('templates');
        if (!is_array($templates) || !$templates) {
            $templates = array('creative');
        }
        $template = $explicit;
        if (!$template && isset($this->CI->SettingsModel)) {
            $template = $this->CI->SettingsModel->getValueStores('opt_invTemplate');
        }
        if (!$template && $userId && isset($this->CI->PublicModel)) {
            $template = $this->CI->PublicModel->getOneValueStore('opt_invTemplate', $userId);
        }
        if (!$template || !in_array($template, $templates, TRUE)) {
            $template = $templates[0];
        }
        return $template;
    }

    /**
     * Absolute path of a template file, or NULL.
     *
     * @param string $template
     * @return string|null
     */
    public function invoiceTemplateFile($template)
    {
        $dir = FCPATH . 'application/modules/users/views/invoices/templates/';
        $file = $dir . $template . '.php';
        if (is_file($file)) {
            return $file;
        }
        // $config['templates'] and the folder can disagree (a template added on
        // one host, a file renamed by a deploy). A client with a share link must
        // still get a document, so fall back to the first template that exists.
        $templates = $this->CI->config->item('templates');
        if (is_array($templates)) {
            foreach ($templates as $candidate) {
                if (is_file($dir . $candidate . '.php')) {
                    log_message('error', 'AdominDocs: template "' . $template . '" is missing, rendered "'
                        . $candidate . '" instead');
                    return $dir . $candidate . '.php';
                }
            }
        }
        return NULL;
    }

    /**
     * Full HTML document for an invoice, ready for AdominPdf or for an iframe.
     *
     * @param array       $invoice  row from NewInvoiceModel::getInvoiceByNumber()
     * @param string      $template template file name
     * @param string      $origin   'original' | 'copy'
     * @param array|null  $firmInfo selected company row (show_logo lives here)
     * @param array       $extra    extra variables to expose to the template
     * @return string
     */
    public function invoiceHtml(array $invoice, $template, $origin = '', $firmInfo = NULL, array $extra = array())
    {
        $templateFile = $this->invoiceTemplateFile($template);
        if (!$templateFile) {
            show_error('Invoice template is missing: ' . html_escape($template), 500);
        }

        // The templates read $firmInfo for the logo switch. When the document is
        // rendered outside the logged-in app (public link) rebuild it from the
        // invoice snapshot, which is exactly what upstream did wrong.
        if (!is_array($firmInfo)) {
            $firmInfo = $this->firmInfoFromInvoice($invoice);
        } elseif (!array_key_exists('logo_url', $firmInfo)) {
            // the session copy of the company predates the logo_url column
            $extra = $this->firmInfoFromInvoice($invoice);
            $firmInfo['logo_url'] = $extra['logo_url'];
            if (empty($firmInfo['image']) && !empty($extra['image'])) {
                $firmInfo['image'] = $extra['image'];
            }
        }
        if (!isset($firmInfo['show_logo'])) {
            $firmInfo['show_logo'] = 1;
        }

        $css = '';
        foreach ($this->printCss as $file) {
            $abs = FCPATH . $file;
            if (is_file($abs)) {
                $css .= "\n" . file_get_contents($abs);
            }
        }
        $css .= "\n" . $this->printOverridesCss();

        if (isset($invoice['translation'])) {
            $invoice['translation'] = $this->normaliseLabels($invoice['translation']);
        }

        $data = array_merge(array(
            'invoice' => $invoice,
            'firmInfo' => $firmInfo,
            // kept for templates that still take it; nothing prints it now
            'origin' => $origin === 'copy' ? 'copy' : 'original',
        ), $extra);

        ob_start();
        extract($data);
        include $templateFile;
        $body = ob_get_clean();

        // The "creative" template already renders its own .invoice-box, while
        // "toner-save" expects the caller to provide it. Both must end up under
        // .invoice-box because every rule in invoices-templates.css is scoped
        // there - that is why upstream PDFs looked unstyled for one template.
        if (strpos($body, 'invoice-box') === FALSE) {
            $body = '<div class="invoice-box">' . $body . '</div>';
        }

        $title = str_replace(array("\n", "\r", '<', '>'), '', (isset($invoice['translation']['invoice']) ? $invoice['translation']['invoice'] : 'Invoice') . ' ' . (isset($invoice['inv_number']) ? $invoice['inv_number'] : ''));

        return '<!DOCTYPE html>' . "\n"
            . '<html lang="' . (defined('MY_LANGUAGE_ABBR') ? MY_LANGUAGE_ABBR : 'en') . '">' . "\n"
            . '<head><meta charset="utf-8">'
            . '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>'
            . '<style>' . $css . '</style>'
            . '</head>' . "\n"
            . '<body class="adomin-print">'
            . $this->watermark($invoice)
            . $body
            . $this->stampBlock()
            . '</body></html>';
    }

    /**
     * The Free-plan watermark, drawn behind the invoice.
     *
     * This is not the same thing as the credit in the footer, and the two must
     * not be confused. The footer credit - "Generated by anvoice, powered by
     * Adomin Technologies" - is how a reader identifies where the document came
     * from, and it stays on every plan including Business. The watermark is the
     * plan-level mark: Free carries it, Pro and above remove it.
     *
     * A plan deciding whether to print our name at all is the thing that gets
     * argued about at renewal, so the rule lives in the plan config's
     * `watermark` flag, and this reads the flag rather than comparing the plan
     * name to a string.
     *
     * Rendered as a repeating diagonal in the page background rather than a
     * single word: one faint "anvoice" in the middle of an invoice is easy to
     * crop out of a scan, and hiding our mark is the whole reason it is there.
     *
     * @param array $invoice
     * @return string
     */
    public function watermark(array $invoice = array())
    {
        if (!$this->planWantsWatermark($invoice)) {
            return '';
        }
        $text = trim((string) adomin_conf('brand', 'name'));
        if ($text === '') {
            $text = 'anvoice';
        }
        // never let a config value break out of the attribute
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // an inline SVG pattern: no external file, renders in dompdf and wkhtmltopdf
        return '<div class="adomin-watermark" aria-hidden="true">'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="360" height="260" viewBox="0 0 360 260">'
            . '<defs><pattern id="adomin-wm" width="180" height="130" patternUnits="userSpaceOnUse" patternTransform="rotate(-32)">'
            . '<text x="10" y="80" font-family="Helvetica, Arial, sans-serif" font-size="26" font-weight="700" '
            . 'fill="#000000" fill-opacity="0.055">' . $text . '</text>'
            . '</pattern></defs>'
            . '<rect width="360" height="260" fill="url(#adomin-wm)"/>'
            . '</svg></div>';
    }

    /**
     * Does this document's plan carry the watermark?
     *
     * The plan is the account's current plan. A document issued on Free keeps
     * its mark when the account later upgrades (grandfather_issued_documents),
     * which is the honest reading: the paper said what it said when it was
     * issued. Re-rendering an old invoice from the share link is the one place
     * this cannot be perfect, because the plan is read at render time.
     *
     * @param array $invoice
     * @return bool
     */
    protected function planWantsWatermark(array $invoice = array())
    {
        if (!function_exists('get_instance')) {
            return FALSE;
        }
        $CI = & get_instance();
        if (!is_object($CI) || !isset($CI->db)) {
            return FALSE;
        }
        /*
         * Read defensively through a local: a controller that has not touched an
         * entitlement yet has no such property, and reading it directly gives
         * the undefined-property warning this guard exists to avoid.
         */
        $entitlements = isset($CI->adomentitlements) && is_object($CI->adomentitlements)
            ? $CI->adomentitlements
            : NULL;

        if ($entitlements === NULL && isset($CI->load) && is_object($CI->load)) {
            $CI->load->library('AdominEntitlements');
            $entitlements = isset($CI->adomentitlements) && is_object($CI->adomentitlements)
                ? $CI->adomentitlements
                : NULL;
        }

        if ($entitlements === NULL) {
            // No entitlement layer available (an unusual embedding). Printing the
            // mark is the safer failure: it costs the reader nothing, while
            // removing it is the thing that was paid for.
            return TRUE;
        }
        return $entitlements->flag('watermark') === TRUE;
    }

    /**
     * CSS that keeps the printed document on brand and letter safe.
     */
    public function printOverridesCss()
    {
        $accent = adomin_conf('brand', 'accent') ?: '#0b6bcb';
        return '
        .adomin-print .invoice-box { width: auto !important; min-height: 0 !important; padding: 0 !important; box-shadow: none !important; margin: 0 !important; }
        /*
         * The Free-plan watermark sits behind the document.
         *
         * position:fixed with a negative z-index is the one arrangement that
         * works in both PDF engines we render with: dompdf honours it as a page
         * background, wkhtmltopdf repeats it on every page. It must not paint
         * over the content, and the content must not need a background colour to
         * stay legible - an invoice table with an opaque fill would hide the mark
         * on that row, which is acceptable; a watermark over the figures is not.
         */
        .adomin-print .adomin-watermark {
            position: fixed; inset: 0; top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: -1; pointer-events: none;
        }
        .adomin-print .adomin-watermark svg { width: 100%; height: 100%; }
        .adomin-print .main-inv-container { padding: 0 !important; margin: 0 !important; box-shadow: none !important; }
        .adomin-print .firm-logo img { max-height: 74px; width: auto; }
        .adomin-print .invoice-creative table thead th,
        .adomin-print table.items-table th { border-bottom: 1.5px solid ' . $accent . '; }
        .adomin-stamp {
            margin-top: 14px; padding-top: 6px;
            border-top: 1px solid #e3e6ea;
            font-size: 8.5px; color: #6e7681; text-align: right;
        }
        .adomin-stamp b { color: ' . $accent . '; }
        ';
    }

    /**
     * "Powered by Adomin Technologies · 26 Nov 2026, 14:03 EAT" block.
     *
     * @param bool $withTz
     * @return string
     */
    public function stampBlock($withTz = TRUE)
    {
        $brandName = adomin_conf('brand', 'name') ?: 'Adomin Technologies';
        $brand = htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8');
        $site = adomin_conf('brand', 'website');
        $label = adomin_stamp($withTz);
        $time = $withTz ? adomin_stamp_time() : '';
        // adomin_stamp() is "Powered by <Brand> · <time>"; split it so the brand
        // can be linked and the timestamp kept as its own span, and so a custom
        // ADOMIN_POWERED_BY phrase still reads correctly.
        $phrase = trim(preg_replace('/\s*·\s*.*$/s', '', $label));
        $prefix = trim(str_replace($brandName, '', $phrase));
        if ($prefix === '') {
            $prefix = 'Powered by';
        }
        $brandHtml = $site
            ? '<a href="' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . '">' . $brand . '</a>'
            : $brand;
        $out = '<div class="adomin-stamp"><span class="label">' . html_escape($prefix) . ' ' . $brandHtml . '</span>';
        if ($time !== '') {
            $out .= ' <span class="stamp-time">· ' . html_escape($time) . '</span>';
        }
        /*
         * The same credit the PDF footer carries, so a printed page and a
         * downloaded file identify their origin identically. The product line
         * comes first because that is what the reader can act on.
         */
        if (function_exists('adomin_pdf_credit')) {
            $out .= '<br><span class="stamp-credit">' . adomin_pdf_credit(TRUE) . '</span>';
        }
        return $out . '</div>';
    }

    /**
     * Rebuild the "selected company" row an invoice template expects, from the
     * invoice snapshot. Needed whenever a document is rendered outside the
     * logged-in app (public share link, cron, queue worker): without it the
     * firm logo silently disappeared from the PDF.
     *
     * @param array $invoice
     * @return array
     */
    public function firmInfoFromInvoice(array $invoice)
    {
        $firm = isset($invoice['firm']) && is_array($invoice['firm']) ? $invoice['firm'] : array();
        $id = isset($firm['for_company']) ? $firm['for_company'] : (isset($invoice['for_company']) ? $invoice['for_company'] : 0);
        $image = NULL;
        if (!empty($firm['image'])) {
            $image = $firm['image'];
        }
        $info = array(
            'id' => $id,
            'name' => isset($firm['name']) ? $firm['name'] : '',
            'image' => $image,
            'show_logo' => 1,
            'logo_url' => NULL,
        );
        // show_logo and the external logo URL are per company settings, not part
        // of the invoice snapshot: read them so a public link/PDF shows exactly
        // what the sender sees. Only when the columns are there, so a database
        // without database/adomin-updates.sql applied still renders.
        if ($id && isset($this->CI->db)) {
            try {
                $select = 'show_logo';
                if ($this->CI->db->field_exists('logo_url', 'firms_users')) {
                    $select .= ', logo_url';
                }
                $row = $this->CI->db->select($select)->where('id', $id)->limit(1)->get('firms_users')->row_array();
                if ($row) {
                    $info['show_logo'] = isset($row['show_logo']) ? (int) $row['show_logo'] : 1;
                    if (isset($row['logo_url'])) {
                        $info['logo_url'] = $row['logo_url'];
                    }
                }
            } catch (Exception $e) {
                // a settings lookup must never break a document
                log_message('error', 'AdominDocs: could not read firm settings for company ' . $id);
            }
        }
        return $info;
    }

    /**
     * Translated document type of an invoice row ('tax_inv' => Invoice, ...).
     *
     * @param array $invoice
     * @return string
     */
    public function typeLabel(array $invoice)
    {
        $map = array(
            'tax_inv' => 'invoice',
            'prof' => 'pro_forma',
            'debit' => 'debit_note',
            'credit' => 'credit_note',
        );
        $type = isset($invoice['inv_type']) ? $invoice['inv_type'] : 'tax_inv';
        $key = isset($map[$type]) ? $map[$type] : 'invoice';
        if (isset($invoice['translation'][$key]) && $invoice['translation'][$key] !== '') {
            return $invoice['translation'][$key];
        }
        return ucfirst(str_replace('-', ' ', $type));
    }

    /**
     * Translated word for "Page" (used in the PDF footer).
     *
     * @param array $invoice
     * @return string
     */
    public function pageLabel(array $invoice)
    {
        if (isset($invoice['translation']['page']) && $invoice['translation']['page'] !== '') {
            return $invoice['translation']['page'];
        }
        $line = lang('inv_page');
        return $line ? $line : 'Page';
    }

    /**
     * Filename for a downloaded document.
     *
     * @param string $type invoice | pro-forma | debit-note | credit-note
     * @param mixed  $number
     * @param string $origin
     * @return string
     */
    public function filename($type, $number, $origin = 'original')
    {
        $parts = array(
            trim(preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $type)),
            trim(preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $number)),
        );
        if ($origin === 'copy') {
            $parts[] = 'copy';
        }
        return implode('-', $parts) . '.pdf';
    }

}
