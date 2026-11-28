<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
  |--------------------------------------------------------------------------
  | Adomin Technologies — personalisation & branding
  |--------------------------------------------------------------------------
  |
  | Every value below can be overridden with an environment variable, so you
  | can personalise a deployment (Render, Vercel, VPS) without touching code.
  | On Render: Dashboard -> your service -> Environment -> add the variable.
  |
  | Branding:   ADOMIN_BRAND_NAME, ADOMIN_BRAND_SHORT, ADOMIN_APP_NAME,
  |             ADOMIN_TAGLINE, ADOMIN_WEBSITE, ADOMIN_SUPPORT_EMAIL,
  |             ADOMIN_SUPPORT_PHONE, ADOMIN_ACCENT, ADOMIN_POWERED_BY
  | Locale:     ADOMIN_TIMEZONE, ADOMIN_CURRENCY, ADOMIN_CURRENCY_NAME,
  |             ADOMIN_DATE_FORMAT
  | PDF:        PDF_ENGINE (dompdf|wkhtmltopdf), WKHTMLTOPDF_PATH,
  |             ADOMIN_PDF_STAMP (0/1), ADOMIN_PDF_FOOTER_TEXT
  | Share link: ADOMIN_SHARE_PREFIX
  | Animations: ADOMIN_ANIMATIONS (0/1)
  | PWA/App:    ADOMIN_THEME_COLOR, ADOMIN_ANDROID_PACKAGE
 */

if (!function_exists('adomin_env')) {

    function adomin_env($key, $default = NULL)
    {
        $value = getenv($key);
        if ($value === FALSE || $value === '') {
            return $default;
        }
        return $value;
    }

    function adomin_env_bool($key, $default = TRUE)
    {
        $value = adomin_env($key, NULL);
        if ($value === NULL) {
            return $default;
        }
        return !in_array(strtolower($value), array('0', 'false', 'no', 'off'), TRUE);
    }
}

/*
 | Brand
 */
$config['brand'] = array(
    /*
     * The product is anvoice. Adomin Technologies is the company behind it, and
     * appears as the "powered by" line rather than as the product name.
     */
    'name'         => adomin_env('ADOMIN_BRAND_NAME', 'anvoice'),
    'short'        => adomin_env('ADOMIN_BRAND_SHORT', 'anvoice'),
    'app_name'     => adomin_env('ADOMIN_APP_NAME', 'anvoice'),
    'company'      => adomin_env('ADOMIN_COMPANY', 'Adomin Technologies'),
    'tagline'      => adomin_env('ADOMIN_TAGLINE', 'Electronic invoicing & warehouse management'),
    'website'      => adomin_env('ADOMIN_WEBSITE', 'https://adomin.co.ke'),
    'support_email'=> adomin_env('ADOMIN_SUPPORT_EMAIL', 'support@adomin.co.ke'),
    'support_phone'=> adomin_env('ADOMIN_SUPPORT_PHONE', ''),
    'powered_by'   => adomin_env('ADOMIN_POWERED_BY', 'Powered by Adomin Technologies'),
    'accent'       => adomin_env('ADOMIN_ACCENT', '#0b6bcb'),
    'theme_color'  => adomin_env('ADOMIN_THEME_COLOR', '#0b6bcb'),
    'copyright_year' => adomin_env('ADOMIN_COPYRIGHT_YEAR', date('Y')),
    'logo'         => adomin_env('ADOMIN_LOGO', 'assets/public/imgs/logo.png'),
    'icon'         => adomin_env('ADOMIN_ICON', 'assets/public/imgs/icons/icon-512.png'),
);

/*
 | Locale / personalisation
 */
$config['locale'] = array(
    'timezone'     => adomin_env('ADOMIN_TIMEZONE', 'Africa/Nairobi'),
    'currency'     => adomin_env('ADOMIN_CURRENCY', 'KES'),
    'currency_name'=> adomin_env('ADOMIN_CURRENCY_NAME', 'Kenyan Shilling'),
    'date_format'  => adomin_env('ADOMIN_DATE_FORMAT', 'd.m.Y'),
    'stamp_format' => adomin_env('ADOMIN_STAMP_FORMAT', 'd M Y, H:i'),
    'greeting'     => adomin_env('ADOMIN_GREETING', 'Karibu'),
);

/*
 | PDF generation.
 |
 | The engine defaults to the bundled dompdf (pure PHP): no shell binary, no
 | root access, no self HTTP request. That is what makes PDFs work on the
 | Render free tier, where wkhtmltopdf's headless fetch of its own footer URL
 | dead-locks the single PHP worker and the download "breaks".
 |
 | Set PDF_ENGINE=wkhtmltopdf only on a host where the binary is baked into the
 | image and the app can reach itself over HTTP (VPS / Docker with php-fpm pm>1).
 */
$config['pdf'] = array(
    'engine'        => strtolower(adomin_env('PDF_ENGINE', 'dompdf')),
    'wkhtmltopdf'   => adomin_env('WKHTMLTOPDF_PATH', '/usr/bin/wkhtmltopdf'),
    'stamp'         => adomin_env_bool('ADOMIN_PDF_STAMP', TRUE),
    'footer_text'   => adomin_env('ADOMIN_PDF_FOOTER_TEXT', ''),
    'paper'         => adomin_env('ADOMIN_PDF_PAPER', 'A4'),
    'dpi'           => (int) adomin_env('ADOMIN_PDF_DPI', 96),
    'margin_left'   => (float) adomin_env('ADOMIN_PDF_MARGIN_LR', 8),
    'margin_top'    => (float) adomin_env('ADOMIN_PDF_MARGIN_TB', 12),
    // off-site images (a Cloudinary/S3 logo URL, the free-tier answer to a wiped
    // disk) are fetched once per render and inlined as data URIs; turn this off
    // if the app must not make outbound requests
    'remote_images'   => adomin_env_bool('ADOMIN_PDF_REMOTE_IMAGES', TRUE),
    'inline_assets' => adomin_env_bool('ADOMIN_PDF_INLINE_ASSETS', TRUE),
    'framework_css' => adomin_env_bool('ADOMIN_PDF_FRAMEWORK_CSS', FALSE),
    'cache_dir'     => adomin_env('ADOMIN_PDF_TMP', ''),
);

/*
 | Tax
 |
 | The default VAT rate offered on a new invoice. It was hardcoded as 20 in
 | three forms - the Bulgarian rate, from the codebase this was forked from -
 | so every Kenyan invoice started at a rate that does not exist here. 16 is
 | Kenya's standard rate. Override per host with ADOMIN_VAT_DEFAULT.
 */
$config['tax'] = array(
    'vat_default' => adomin_env('ADOMIN_VAT_DEFAULT', '16'),
);

/*
 | Public "send via link" sharing
 */
$config['share'] = array(
    'prefix'   => trim(adomin_env('ADOMIN_SHARE_PREFIX', 'invoice'), '/'),
    // a client's phone number is not stored by this app, so the WhatsApp and
    // SMS actions open the picker with the message pre-filled only
    'needs_picker' => TRUE,
    'enabled'  => adomin_env_bool('ADOMIN_SHARE_LINKS', TRUE),
);

/*
 | UI animations + PWA / Android shell
 */
$config['ui'] = array(
    'animations'    => adomin_env_bool('ADOMIN_ANIMATIONS', TRUE),
    'pwa'           => adomin_env_bool('ADOMIN_PWA', TRUE),
    'android_pkg'   => adomin_env('ADOMIN_ANDROID_PACKAGE', 'ke.co.adomin.invoices'),
);
