<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
  |--------------------------------------------------------------------------
  | Adomin Technologies helper
  |--------------------------------------------------------------------------
  |
  | Branding, personalisation, timestamps and PDF asset helpers. Loaded
  | automatically (see application/config/autoload.php).
 */

if (!function_exists('adomin_conf')) {
    /**
     * Read a value from application/config/adomin.php
     *
     * @param string $key    top level key, e.g. 'brand'
     * @param string $second optional sub key, e.g. 'name'
     * @return mixed
     */
    function adomin_conf($key = NULL, $second = NULL)
    {
        static $loaded = FALSE;
        $CI = & get_instance();
        if (!$loaded) {
            $CI->config->load('adomin', TRUE);
            $loaded = TRUE;
        }
        $value = $CI->config->item($key, 'adomin');
        if ($value === NULL) {
            // Config file missing entirely -> fall back to safe defaults.
            $value = array(
                'brand'  => array('name' => 'anvoice', 'short' => 'anvoice', 'app_name' => 'anvoice',
                    'company' => 'Adomin Technologies',
                    'powered_by' => 'Powered by Adomin Technologies', 'website' => 'https://adomin.co.ke',
                    'accent' => '#0b6bcb', 'theme_color' => '#0b6bcb', 'logo' => 'assets/public/imgs/logo.png'),
                'locale' => array('timezone' => 'Africa/Nairobi', 'date_format' => 'd.m.Y', 'stamp_format' => 'd M Y, H:i'),
                'pdf'    => array('engine' => 'dompdf', 'stamp' => TRUE, 'remote_images' => TRUE),
                'share'  => array('prefix' => 'invoice', 'enabled' => TRUE),
                'ui'     => array('animations' => TRUE, 'pwa' => TRUE),
            )[$key];
        }
        if ($second !== NULL && is_array($value)) {
            return isset($value[$second]) ? $value[$second] : NULL;
        }
        return $value;
    }
}

if (!function_exists('adomin_timezone')) {

    function adomin_timezone()
    {
        return adomin_conf('locale', 'timezone') ?: 'Africa/Nairobi';
    }
}

if (!function_exists('adomin_now')) {
    /**
     * Current time in the configured (East Africa) timezone.
     *
     * @return int unix timestamp
     */
    function adomin_now()
    {
        return time();
    }
}

if (!function_exists('adomin_date')) {
    /**
     * Format a timestamp with the app date format (safe on PHP 7 & 8).
     *
     * @param int|null    $timestamp
     * @param string|null $format
     * @return string
     */
    function adomin_date($timestamp = NULL, $format = NULL)
    {
        if ($format === NULL) {
            $format = adomin_conf('locale', 'date_format') ?: 'd.m.Y';
        }
        if (empty($timestamp)) {
            $timestamp = time();
        }
        return date($format, (int) $timestamp);
    }
}

if (!function_exists('adomin_tz_abbr')) {

    function adomin_tz_abbr()
    {
        try {
            $abbr = (new DateTime('now', new DateTimeZone(adomin_timezone())))->format('T');
        } catch (Exception $e) {
            $abbr = 'EAT';
        }
        return $abbr !== '' ? $abbr : 'EAT';
    }
}

if (!function_exists('adomin_plan_count')) {
    /**
     * Render a plan counter for a human.
     *
     * AdominEntitlements::planUnits() answers with 999999 when a plan has no
     * limit, because the templates it replaced did arithmetic on the number and
     * printed it directly. The header then printed that sentinel verbatim:
     * "999999 documents" and "999994 companies" in the corner of every screen.
     * The number was never meant to be read - it means "no limit" - so it is
     * translated here, at the point of display, rather than by changing the
     * sentinel and breaking the comparisons that rely on it.
     *
     * @param int|string|null $n
     * @return string
     */
    function adomin_plan_count($n)
    {
        if ($n === NULL) {
            return lang('unlimited');
        }
        $n = (int) $n;
        if ($n >= 999999) {
            return lang('unlimited');
        }
        return number_format($n);
    }
}

if (!function_exists('adomin_stamp_label')) {
    /**
     * "Powered by Adomin Technologies"
     *
     * @return string
     */
    function adomin_stamp_label()
    {
        return adomin_conf('brand', 'powered_by') ?: 'Powered by Adomin Technologies';
    }
}

if (!function_exists('adomin_asset')) {
    /**
     * URL for a static asset, stamped with its modification time.
     *
     * Every stylesheet used to be linked as a bare URL. A browser caches that
     * indefinitely, so edits to the layout shipped and then kept being served
     * from cache - the page looked unfixed even though the new CSS was on the
     * server. ?v=<mtime> changes the URL when the file changes, so a new build
     * is always fetched and an unchanged one is still cached.
     *
     * @param string $path  path relative to the web root, e.g. assets/users/css/anvoice.css
     * @return string
     */
    function adomin_asset($path)
    {
        $path = ltrim((string) $path, '/');
        $url = base_url($path);
        $file = FCPATH . $path;
        if (is_file($file)) {
            $url .= '?v=' . filemtime($file);
        }
        return $url;
    }
}

if (!function_exists('adomin_stamp_time')) {
    /**
     * "26 Nov 2026, 14:03 EAT"
     *
     * @return string
     */
    function adomin_stamp_time()
    {
        $format = adomin_conf('locale', 'stamp_format') ?: 'd M Y, H:i';
        return date($format) . ' ' . adomin_tz_abbr();
    }
}

if (!function_exists('adomin_stamp')) {
    /**
     * "Powered by Adomin Technologies · 26 Nov 2026, 14:03 EAT"
     *
     * @param bool $withTime
     * @return string
     */
    function adomin_stamp($withTime = TRUE)
    {
        $brand = adomin_stamp_label();
        if (!$withTime) {
            return $brand;
        }
        return $brand . ' · ' . adomin_stamp_time();
    }
}

if (!function_exists('adomin_pdf_credit')) {
    /**
     * The credit line a generated PDF carries: the product, and the company
     * behind it.
     *
     * "PDF generated by anvoice · powered by Adomin Technologies" - the two are
     * different claims and both matter: anvoice generated the file, Adomin
     * Technologies made anvoice. Configured entirely through brand config, so a
     * white-label deployment that sets ADOMIN_BRAND_NAME still reads correctly,
     * and one that sets ADOMIN_POWERED_BY to an empty string drops the second
     * half instead of printing "powered by" with nothing after it.
     *
     * @param bool $asHtml link the company name to the website
     * @return string
     */
    function adomin_pdf_credit($asHtml = FALSE)
    {
        $app = trim((string) adomin_conf('brand', 'app_name'));
        if ($app === '') {
            $app = trim((string) adomin_conf('brand', 'name'));
        }

        $company = trim((string) adomin_conf('brand', 'company'));
        $powered = trim((string) adomin_conf('brand', 'powered_by'));
        // powered_by is a whole phrase ("Powered by Adomin Technologies"): take
        // the company name out of it rather than assume the wording
        if ($company !== '' && $powered !== '' && stripos($powered, $company) !== FALSE) {
            $powered = trim(str_ireplace($company, '', $powered));
        }
        if ($company === '') {
            $company = trim($powered);
            $powered = '';
        }

        $companyText = $company;
        if ($asHtml && $company !== '') {
            $site = trim((string) adomin_conf('brand', 'website'));
            if ($site !== '') {
                $companyText = '<a href="' . html_escape($site) . '">' . html_escape($company) . '</a>';
            } else {
                $companyText = html_escape($company);
            }
        }

        $out = 'PDF generated by ' . html_escape($app);
        if ($company !== '') {
            $out .= ' · ' . ($powered !== '' ? html_escape($powered) . ' ' : 'powered by ') . $companyText;
        }

        return $out;
    }
}

if (!function_exists('adomin_pdf_credit_text')) {
    /**
     * The same line as plain text, for the PDF canvas (which cannot take HTML).
     *
     * @return string
     */
    function adomin_pdf_credit_text()
    {
        return str_replace('&amp;', '&', strip_tags(adomin_pdf_credit(FALSE)));
    }
}

if (!function_exists('adomin_generated_line')) {
    /**
     * Document generation line: "Generated 26 Nov 2026, 14:03 EAT · Adomin Technologies"
     */
    function adomin_generated_line($label = 'Generated')
    {
        $format = adomin_conf('locale', 'stamp_format') ?: 'd M Y, H:i';
        $short = adomin_conf('brand', 'short') ?: 'Adomin';
        return $label . ' ' . date($format) . ' ' . adomin_tz_abbr() . ' · ' . $short;
    }
}

if (!function_exists('adomin_logo_url')) {
    /**
     * Site logo URL, falling back to an in-file brand mark so the UI never
     * shows a broken image (assets/public/imgs/logo.png is not in git).
     *
     * @return string
     */
    function adomin_logo_url()
    {
        $CI = & get_instance();
        $rel = adomin_conf('brand', 'logo') ?: 'assets/public/imgs/logo.png';
        if (is_file(FCPATH . $rel)) {
            return base_url($rel);
        }
        return base_url('assets/public/imgs/adomin-mark.svg');
    }
}

if (!function_exists('adomin_firm_logo_src')) {
    /**
     * Where a firm logo really comes from, in priority order:
     *   1. firms_users.logo_url  (external URL: survives an ephemeral filesystem)
     *   2. attachments/companiesimages/<firm_id>/<image> (the upload), but only
     *      when that file is actually on disk - a missing file used to render a
     *      broken <img> in the PDF
     *   3. nothing
     *
     * @param array $invoice  invoice row with ['firm']
     * @param array $firmInfo selected company row (show_logo, logo_url)
     * @return string url or ''
     */
    function adomin_firm_logo_src($invoice, $firmInfo)
    {
        $showLogo = TRUE;
        if (is_array($firmInfo) && array_key_exists('show_logo', $firmInfo)) {
            $showLogo = (bool) $firmInfo['show_logo'];
        }
        if (!$showLogo) {
            return '';
        }
        if (is_array($firmInfo) && !empty($firmInfo['logo_url'])) {
            return trim($firmInfo['logo_url']);
        }
        $image = NULL;
        if (isset($invoice['firm']['image']) && $invoice['firm']['image'] !== '') {
            $image = $invoice['firm']['image'];
        } elseif (is_array($firmInfo) && !empty($firmInfo['image'])) {
            $image = $firmInfo['image'];
        }
        if (!$image) {
            return '';
        }
        $firmId = 0;
        if (is_array($firmInfo) && !empty($firmInfo['id'])) {
            $firmId = $firmInfo['id'];
        } elseif (isset($invoice['for_company'])) {
            $firmId = $invoice['for_company'];
        }
        $rel = 'attachments/' . (defined('COMPANIES_IMAGES_DIR') ? COMPANIES_IMAGES_DIR : 'companiesimages') . '/' . $firmId . '/' . $image;
        if (!is_file(FCPATH . $rel)) {
            return '';
        }
        return base_url($rel);
    }
}

if (!function_exists('adomin_data_uri')) {
    /**
     * Absolute path -> base64 data URI (dompdf cannot read local files unless
     * they are inside its chroot, and remote fetching would mean an HTTP
     * request from the app to itself - the thing that breaks PDFs on Render).
     *
     * @param string $absPath
     * @return string|null
     */
    function adomin_data_uri($absPath)
    {
        if (!$absPath || !is_file($absPath) || !function_exists('base64_encode')) {
            return NULL;
        }
        $contents = @file_get_contents($absPath);
        if ($contents === FALSE || $contents === '') {
            return NULL;
        }
        $mime = 'application/octet-stream';
        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
        $map = array(
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'webp' => 'image/webp',
        );
        if (isset($map[$ext])) {
            $mime = $map[$ext];
        }
        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }
}

if (!function_exists('adomin_public_path')) {
    /**
     * Absolute filesystem path of a document-root relative URL such as
     * https://host/assets/x.css or /attachments/a/b.png.
     *
     * @param string $url
     * @return string|null absolute path or NULL when it is not a local file
     */
    function adomin_public_path($url)
    {
        $path = $url;
        if (preg_match('#^(?:https?:)?//[^/]+(.*)$#i', $path, $m)) {
            $path = $m[1];
        }
        $path = parse_url($path, PHP_URL_PATH);
        if (!$path) {
            return NULL;
        }
        $path = ltrim(rawurldecode($path), '/');
        if ($path === '') {
            return NULL;
        }
        $abs = FCPATH . $path;
        if (is_file($abs)) {
            return $abs;
        }
        // Fallback: strip an index.php/ prefix when the app lives in a subdir.
        $alt = FCPATH . preg_replace('#^index\.php/#', '', $path);
        return is_file($alt) ? $alt : NULL;
    }
}

if (!function_exists('adomin_share_url')) {
    /**
     * Public, read-only link for an invoice row.
     *
     * @param array $invoice row with 'uniqid' (and optionally 'inv_number')
     * @return string
     */
    function adomin_share_url($invoice)
    {
        $prefix = adomin_conf('share', 'prefix') ?: 'invoice';
        $token = '';
        if (is_array($invoice)) {
            $token = isset($invoice['uniqid']) ? $invoice['uniqid'] : '';
        } elseif (is_string($invoice)) {
            $token = $invoice;
        }
        return base_url($prefix . '/' . rawurlencode($token));
    }
}

if (!function_exists('adomin_animations_on')) {

    function adomin_animations_on()
    {
        return (bool) adomin_conf('ui', 'animations');
    }
}

if (!function_exists('adomin_body_class')) {
    /**
     * Class attribute for <body>: switches the motion layer on.
     *
     * @param string $extra
     * @return string
     */
    function adomin_body_class($extra = '')
    {
        $classes = array();
        if (adomin_animations_on()) {
            $classes[] = 'adomin-motion';
        }
        if (!empty($extra)) {
            $classes[] = trim($extra);
        }
        return $classes ? ' class="' . implode(' ', $classes) . '"' : '';
    }
}

if (!function_exists('adomin_pwa_head')) {
    /**
     * Manifest + theme colour + iOS/Android home screen tags, plus the
     * <meta> tags adomin-app.js needs (base url, scope, share endpoint).
     *
     * @param string $scope    directory the service worker controls
     * @param string $manifest path under the document root
     * @return string
     */
    function adomin_pwa_head($scope = NULL, $manifest = 'manifest.json')
    {
        if (!adomin_conf('ui', 'pwa')) {
            return '';
        }
        $CI = & get_instance();
        $base = rtrim($CI->config->base_url(''), '/') . '/';
        if ($scope === NULL) {
            // scope = the directory the app lives in, so a sub-folder install
            // still gets a working worker + manifest
            $scope = parse_url($base, PHP_URL_PATH);
            if (!$scope) {
                $scope = '/';
            }
        }
        $brand = html_escape(adomin_conf('brand', 'app_name') ?: 'Adomin Invoices');
        $theme = html_escape(adomin_conf('brand', 'theme_color') ?: '#0b6bcb');
        $url = function ($rel) use ($CI) {
            return html_escape($CI->config->base_url($rel));
        };
        $icon = $url(adomin_conf('brand', 'icon') ?: 'assets/public/imgs/icons/icon-512.png');
        $tag = '<meta name="adomin-base" content="' . html_escape($base) . '">' . "\n        ";
        $tag .= '<meta name="adomin-scope" content="' . html_escape($scope) . '">' . "\n        ";
        $tag .= '<link rel="manifest" href="' . $url($manifest) . '">' . "\n        ";
        $tag .= '<meta name="theme-color" content="' . $theme . '">' . "\n        ";
        $tag .= '<meta name="mobile-web-app-capable" content="yes">' . "\n        ";
        $tag .= '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n        ";
        $tag .= '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n        ";
        $tag .= '<meta name="apple-mobile-web-app-title" content="' . $brand . '">' . "\n        ";
        $tag .= '<meta name="application-name" content="' . $brand . '">' . "\n        ";
        $tag .= '<meta name="format-detection" content="telephone=no">' . "\n        ";
        $tag .= '<link rel="apple-touch-icon" href="' . $url('assets/public/imgs/icons/apple-touch-icon.png') . '">' . "\n        ";
        $tag .= '<link rel="icon" type="image/png" sizes="32x32" href="' . $url('assets/public/imgs/icons/favicon-32.png') . '">' . "\n        ";
        $tag .= '<link rel="icon" type="image/png" sizes="192x192" href="' . $icon . '">' . "\n        ";
        $tag .= '<link rel="mask-icon" href="' . $url('assets/public/imgs/adomin-mark.svg') . '" color="' . $theme . '">';
        return $tag;
    }
}

if (!function_exists('adomin_app_js')) {
    /**
     * The UI runtime (motion, share link, service worker).
     *
     * @return string
     */
    function adomin_app_js()
    {
        $CI = & get_instance();
        /*
         * Stamped with its modification time, like every other asset.
         *
         * This was the one script linked by bare URL, and it is the file that
         * binds the share modal's Copy button. So a fix to that handler
         * deployed correctly, passed its tests, and then kept being served from
         * a browser cache - the page rendered the new markup with the old
         * script, and Copy did nothing at all: no toast, no animation, no
         * console error. The service worker had the same problem at its own
         * layer (see sw.js), and both are now keyed to the file's contents.
         */
        $out = '<script src="' . html_escape(adomin_asset('assets/public/js/adomin-app.js')) . '"></script>';
        return $out;
    }
}

if (!function_exists('adomin_csrf_field')) {
    /**
     * The hidden CSRF input a POSTing form needs, rendered by the server.
     *
     * application/config/config.php has csrf_protection enabled, so CodeIgniter
     * rejects any POST whose body does not repeat the token it put in the
     * csrf_cookie_name cookie - with show_error() and a 403 page. The footer used
     * to add that input from JavaScript, which works only if the script runs,
     * the DOM is parsed and the meta tag was rendered. On a cold Render start
     * (30-60 s) or a browser with a stale/broken asset cache, the form is
     * submitted without it and the user sees "The action you have requested is
     * not allowed." for a login that was typed correctly.
     *
     * Printing it in the markup removes that dependency; the footer script still
     * covers forms that are added later.
     *
     * @return string
     */
    function adomin_csrf_field()
    {
        $CI = & get_instance();
        if (!$CI->config->item('csrf_protection')) {
            return '';
        }
        $name = $CI->security->get_csrf_token_name();
        $hash = $CI->security->get_csrf_hash();
        if ($name === '' || $hash === '') {
            return '';
        }
        return '<input type="hidden" name="' . html_escape($name) . '" value="' . html_escape($hash) . '">';
    }
}

if (!function_exists('adomin_foot')) {
    /**
     * The "Powered by Adomin Technologies · <timestamp>" line used in the app
     * footer, the login page and the public invoice pages.
     *
     * @param bool $linked
     * @return string html
     */
    function adomin_foot($linked = TRUE)
    {
        $brand = html_escape(adomin_conf('brand', 'name') ?: 'Adomin Technologies');
        $site = adomin_conf('brand', 'website');
        $powered = html_escape(adomin_stamp_label());
        $time = html_escape(adomin_stamp_time());
        $name = ($linked && $site)
            ? '<a href="' . html_escape($site) . '" target="_blank" rel="noopener">' . $brand . '</a>'
            : $brand;
        $out = '<span class="adomin-powered">' . $powered . ' · ' . $name . '</span>';
        $out .= ' <span class="adomin-stamp-time">' . $time . '</span>';
        return $out;
    }
}

