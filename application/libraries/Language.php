<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * Loads the language/currency for the site and defines the constants every
 * view, invoice template and PDF uses (MY_LANGUAGE_ABBR, CURRENCY, LANG_URL,
 * ...).
 *
 * This library runs from the autoloader, i.e. before any controller does, so it
 * is the first thing on a deployed instance that talks to the database. Three
 * situations used to turn that into an HTTP 500 with nothing in it - and on a
 * fresh TiDB deployment all three are normal:
 *
 *   1. the database is not reachable yet (wrong DB_* values, TLS not
 *      negotiated, the TiDB Serverless instance is waking up);
 *   2. the database is reachable but the schema is empty, because the import
 *      has not finished (every statement is a missing table);
 *   3. the schema exists but `languages` has no row for the requested
 *      abbreviation - which is ALWAYS the case for the home page, because the
 *      home page has no language segment in its URL.
 *
 * The constants are needed to render anything at all, so the library never
 * gives up: it falls back to the default language from
 * application/config/config.php, logs the exact database error once, and lets
 * the request continue. A visitor then gets the login page instead of a blank
 * 500, and the log (Render -> Logs) names the problem. When the schema really is
 * unusable, the controller that needs data raises its own, more specific error.
 */

class Language
{

    protected $CI;

    private $urlAbbrevation;

    /**
     * Set when the language lookup failed because the database is unreachable
     * or the schema is not installed. Readable so a controller or a health
     * check can tell "the app is up, the database is not" apart.
     *
     * @var bool
     */
    private $databaseUnavailable = FALSE;

    public function __construct()
    {
        $this->CI = & get_instance();

        /*
         * The model is only needed for the lookup. Loading it is also the point
         * where CodeIgniter connects to the database, so it is wrapped: a
         * connection failure must degrade to the default language rather than
         * become a fatal error ("Call to a member function ... on null").
         */
        $model_loaded = FALSE;
        try {
            $this->CI->load->model('PublicModel');
            $model_loaded = isset($this->CI->PublicModel);
        } catch (Exception $e) {
            $this->databaseUnavailable = TRUE;
            log_message('error', 'Language: could not load PublicModel - ' . $e->getMessage());
        } catch (Throwable $e) {
            // PHP 7+ errors are Throwable, not Exception.
            $this->databaseUnavailable = TRUE;
            log_message('error', 'Language: could not load PublicModel - ' . $e->getMessage());
        }

        $this->urlAbbrevation = strtolower((string) $this->CI->uri->segment(1));

        try {
            $this->setLanguage($model_loaded);
        } catch (Exception $e) {
            $this->setDefaultLanguage();
            log_message('error', 'Language: falling back to the default language - ' . $e->getMessage());
        } catch (Throwable $e) {
            $this->setDefaultLanguage();
            log_message('error', 'Language: falling back to the default language - ' . $e->getMessage());
        }
    }

    /**
     * True when the language lookup could not reach the database or found no
     * `languages` table. Not fatal: the app runs with the configured default.
     *
     * @return bool
     */
    public function databaseUnavailable()
    {
        return $this->databaseUnavailable;
    }

    private function setLanguage($model_loaded)
    {
        $defaultLanguageName = $language = $this->CI->config->item('language');
        $defaultLanguageAbbr = $myLanguage = strtolower((string) $this->CI->config->item('language_abbr'));
        $currency = $this->CI->config->item('currency');
        $currencyKey = $this->CI->config->item('currencyKey');
        $langLinkStart = '';

        /*
         * If try to select default language, clean it and return to url
         * Else get the language
         */
        if ($this->urlAbbrevation !== '' && $this->urlAbbrevation === $defaultLanguageAbbr) {
            $currentUrl = str_replace($this->urlAbbrevation, '', uri_string());
            redirect(base_url($currentUrl));
        } elseif ($this->urlAbbrevation !== '' && $model_loaded) {
            /*
             * An empty abbreviation (the home page) is never looked up: no
             * `languages` row has an empty abbr, so the query can only waste a
             * round trip to a hosted database and, before this guard, could
             * also fail the request.
             */
            $myLang = $this->getLanguageRow();
            if (is_array($myLang)) {
                $myLanguage = $myLang['abbr'];
                $language = $myLang['name'];
                $currency = $myLang['currency'];
                $currencyKey = $myLang['currencyKey'];
                $langLinkStart = $myLanguage . '/';
            }
        }

        $this->defineConstants($language, $myLanguage, $defaultLanguageName, $defaultLanguageAbbr, $currency, $currencyKey, $langLinkStart);
    }

    /**
     * The `languages` row for the requested abbreviation, or NULL.
     *
     * A failing query (no connection, missing table, missing privileges) is
     * logged once and treated as "language not found", because that is what it
     * means for the caller.
     *
     * @return array|null
     */
    private function getLanguageRow()
    {
        $db = $this->CI->db;

        if ( ! is_object($db) || empty($db->conn_id)) {
            $this->databaseUnavailable = TRUE;
            log_message('error', 'Language: no database connection - using the default language.');
            return NULL;
        }

        try {
            $row = $this->CI->PublicModel->getOneLanguage($this->urlAbbrevation);
        } catch (Exception $e) {
            $row = NULL;
            $this->databaseUnavailable = TRUE;
            log_message('error', 'Language: languages lookup failed - ' . $e->getMessage());
        } catch (Throwable $e) {
            $row = NULL;
            $this->databaseUnavailable = TRUE;
            log_message('error', 'Language: languages lookup failed - ' . $e->getMessage());
        }

        if ($this->databaseUnavailable) {
            return NULL;
        }

        return is_array($row) ? $row : NULL;
    }

    /**
     * Load the language files and define the constants, falling back to the
     * default language whenever a translation set is missing for the requested
     * idiom (a row can name a language whose files were never shipped).
     */
    private function defineConstants($language, $myLanguage, $defaultLanguageName, $defaultLanguageAbbr, $currency, $currencyKey, $langLinkStart)
    {
        $files = array('public', 'users', 'titles');

        foreach (array($language, $defaultLanguageName) as $idiom) {
            if ( ! $this->languageFilesExist($idiom, $files)) {
                continue;
            }
            foreach ($files as $file) {
                $this->CI->lang->load($file, $idiom);
            }
            if ($idiom !== $language) {
                // The row pointed at a language this installation does not have.
                log_message('error', 'Language: no language files for "' . $language . '" - using "' . $idiom . '".');
                $language = $idiom;
                $myLanguage = strtolower((string) $defaultLanguageAbbr);
                $langLinkStart = '';
            }
            break;
        }

        $this->defineOnce('MY_LANGUAGE_FULL_NAME', $language);
        $this->defineOnce('MY_LANGUAGE_ABBR', $myLanguage);
        $this->defineOnce('MY_DEFAULT_LANGUAGE_ABBR', $defaultLanguageAbbr);
        $this->defineOnce('MY_DEFAULT_LANGUAGE_NAME', $defaultLanguageName);
        $this->defineOnce('CURRENCY', $currency);
        $this->defineOnce('CURRENCY_KEY', $currencyKey);
        $this->defineOnce('LANG_URL', rtrim(base_url($langLinkStart), '/'));
    }

    /**
     * define() only if it has not happened yet, so the fallback path can run
     * after a half-finished first attempt without "already defined" warnings.
     */
    private function defineOnce($name, $value)
    {
        if ( ! defined($name)) {
            define($name, $value);
        }
    }

    /**
     * Last resort: the configured defaults, no database involved.
     */
    private function setDefaultLanguage()
    {
        $language = $this->CI->config->item('language');
        $abbr = strtolower((string) $this->CI->config->item('language_abbr'));

        if ( ! $this->languageFilesExist($language, array('public', 'users', 'titles'))) {
            $language = 'english';
            $abbr = 'en';
        }

        // define() warns (and in PHP 8 throws) on a constant defined twice.
        if (defined('MY_LANGUAGE_ABBR')) {
            return;
        }

        $this->defineConstants(
            $language,
            $abbr,
            $language,
            $abbr,
            $this->CI->config->item('currency'),
            $this->CI->config->item('currencyKey'),
            ''
        );
    }

    /**
     * Are all the given language files present for this idiom?
     *
     * Checked before calling Lang::load(), because that helper answers a
     * missing file with show_error() - a 500 page in the middle of boot.
     */
    private function languageFilesExist($idiom, $files)
    {
        $idiom = (string) $idiom;
        if ($idiom === '' || ! preg_match('/^[a-z_-]+$/i', $idiom)) {
            return FALSE;
        }

        foreach ($files as $file) {
            $file = preg_replace('/_lang$/', '', $file) . '_lang.php';
            if ( ! is_file(APPPATH . 'language/' . $idiom . '/' . $file)) {
                return FALSE;
            }
        }

        return TRUE;
    }

    public function getUrlAbbrevation()
    {
        return $this->urlAbbrevation;
    }

}
