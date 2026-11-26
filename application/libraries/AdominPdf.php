<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/*
  |--------------------------------------------------------------------------
  | AdominPdf — HTML to PDF for Adomin Technologies deployments
  |--------------------------------------------------------------------------
  |
  | Why this exists
  | ---------------
  | The upstream app shelled out to wkhtmltopdf and let that binary fetch its
  | own footer over HTTP ("--footer-html https://host/pdffooter"). On a free
  | tier box (Render free = 0.1 CPU, effectively one PHP worker, service sleeps
  | after 15 minutes) the request that renders the PDF is the very request
  | wkhtmltopdf is waiting for: a self-request deadlock. That is "the PDF
  | break" - the download hangs, then dies. The invoice path had the same bug
  | in another shape: it read its own stylesheet with
  | file_get_contents(base_url(...)), which fails when allow_url_fopen is off
  | or the container cannot reach its own public URL.
  |
  | This library never touches the network:
  |   * local stylesheets and <img> are inlined into the HTML (data: URIs);
  |   * the footer is drawn by the renderer itself (page numbers supported);
  |   * the default engine is the bundled dompdf (pure PHP: no root, no apt,
  |     no 200 MB binary) - which is what makes PDFs work on Render free tier.
  | wkhtmltopdf is used only if you explicitly ask for it (PDF_ENGINE
  | =wkhtmltopdf) and the binary really exists; even then the footer is written
  | to a temp file and passed as file://, so no HTTP round trip happens.
  |
  | Public API
  | ----------
  |   $pdf = $this->adominpdf->fromHtml($html, $options);       // PDF string
  |   $this->adominpdf->output($pdf, 'invoice-1.pdf', false);  // stream + exit
  |   $this->adominpdf->renderTemplate($file, $data, $options); // include + PDF
  |   $html = $this->adominpdf->captureTemplate($file, $data, $options);
  |
  | $options:
  |   footer       array('left'=>..,'center'=>..,'right'=>..) - {PAGE_NUM} and
  |                {PAGE_COUNT} are substituted on every page
  |   stamp        bool   draw "Powered by Adomin Technologies · <timestamp>"
  |   paper        'A4' | 'letter'
  |   orientation  'portrait' | 'landscape'
  |   margins      array('left'=>8,'right'=>8,'top'=>12,'bottom'=>18)  [mm]
  |   dpi          int
  |   font_face    bool   register DejaVu Sans (unicode, cyrillic safe)
  |   inline_assets bool  inline local <link rel=stylesheet> and <img>
  |   css          string extra CSS
  |   extra_css_files array of document-root relative css files to inline
  |   download     bool   used by output()
 */

require_once APPPATH . 'libraries/dompdf/autoload.inc.php';

class AdominPdf
{

    /** @var CI_Controller */
    private $CI;

    /** @var string engine used by the last render */
    private $lastEngine = '';

    /** @var array human readable notes about the last render */
    private $notices = array();

    /** @var array url => data URI, so a shared logo is fetched once per render */
    private $remoteImages = array();

    public function __construct()
    {
        $this->CI = & get_instance();
        if (!function_exists('adomin_conf')) {
            $this->CI->load->helper('adomin');
        }
    }

    /* ------------------------------------------------------------------ *
     *  Public API
     * ------------------------------------------------------------------ */

    /**
     * @param  string $html    complete (or partial) HTML document
     * @param  array  $options see class docblock
     * @return string          PDF contents
     */
    public function fromHtml($html, array $options = array())
    {
        $this->notices = array();
        $opt = $this->resolveOptions($options);
        $html = $this->prepare($html, $opt);

        $pdf = NULL;
        $engine = $opt['engine'];
        if ($engine === 'wkhtmltopdf') {
            if (!$this->binaryAvailable($opt['wkhtmltopdf'])) {
                $this->notices[] = 'wkhtmltopdf not found at ' . $opt['wkhtmltopdf'] . ' - using dompdf';
            } else {
                $pdf = $this->withWkhtmltopdf($html, $opt);
            }
        }
        if (!$pdf) {
            $pdf = $this->withDompdf($html, $opt);
        }
        if (!$pdf) {
            log_message('error', 'AdominPdf: render produced no output for user ' . (defined('USER_ID') ? USER_ID : 'n/a'));
            show_error('Could not generate the PDF. Check application/logs for details.', 500);
        }
        return $pdf;
    }

    /**
     * Include a PHP template (it expects $data in scope) and render it as PDF.
     *
     * @return string
     */
    public function renderTemplate($templateFile, array $data = array(), array $options = array())
    {
        return $this->fromHtml($this->captureTemplate($templateFile, $data, $options), $options);
    }

    /**
     * Wrap a template in a printable HTML document, CSS inlined from disk.
     *
     * @return string
     */
    public function captureTemplate($templateFile, array $data = array(), array $options = array())
    {
        if (!is_file($templateFile)) {
            show_error('Template file is missing: ' . html_escape($templateFile), 404);
        }
        $opt = $this->resolveOptions($options);

        ob_start();
        if (!empty($data)) {
            extract($data);
        }
        include $templateFile;
        $body = ob_get_clean();

        $css = '';
        foreach ($opt['extra_css_files'] as $file) {
            $abs = $this->publicPath($file);
            if ($abs) {
                $css .= "\n" . file_get_contents($abs);
            }
        }
        if (!empty($opt['css'])) {
            $css .= "\n" . $opt['css'];
        }
        return '<!DOCTYPE html>' . "\n" . '<html lang="en"><head><meta charset="utf-8">'
            . '<title>Document</title>' . ($css !== '' ? '<style>' . $css . '</style>' : '')
            . '</head><body>' . $body . '</body></html>';
    }

    /**
     * Send the PDF to the browser (inline preview or download) and stop.
     *
     * @param string $pdf
     * @param string $filename
     * @param bool   $download
     */
    public function output($pdf, $filename = 'document.pdf', $download = FALSE)
    {
        if (is_array($filename)) {
            $download = !empty($filename['download']);
            $filename = isset($filename['name']) ? $filename['name'] : 'document.pdf';
        }
        $filename = preg_replace('/[^A-Za-z0-9._ -]/', '', (string) $filename);
        if ($filename === '' || substr($filename, -4) !== '.pdf') {
            $filename = ($filename !== '' ? $filename : 'document') . '.pdf';
        }
        if (headers_sent($file, $line)) {
            log_message('error', 'AdominPdf: headers already sent from ' . $file . ':' . $line);
            echo $pdf;
            return;
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        echo $pdf;
    }

    /**
     * Convenience: render + stream + exit.
     */
    public function streamAndExit($html, $filename, array $options = array())
    {
        $download = !empty($options['download']) || (isset($_GET['download']) && $_GET['download'] == '1');
        $pdf = $this->fromHtml($html, $options);
        $this->output($pdf, $filename, $download);
        exit;
    }

    public function getLastEngine()
    {
        return $this->lastEngine;
    }

    public function getNotices()
    {
        return $this->notices;
    }

    /* ------------------------------------------------------------------ *
     *  dompdf renderer (the free tier friendly default)
     * ------------------------------------------------------------------ */

    private function withDompdf($html, array $opt)
    {
        $tmp = $this->writableDir($opt['cache_dir']);
        $fontDir = rtrim(APPPATH, '/\\') . '/libraries/dompdf/lib/fonts';
        $dompdfDir = realpath(APPPATH . 'libraries/dompdf');

        $options = new \Dompdf\Options();
        $chroot = array($dompdfDir, rtrim(APPPATH, '/\\'), rtrim(FCPATH, '/\\'), $tmp);
        $options->set('chroot', array_values(array_unique(array_filter($chroot))));
        $options->set('tempDir', $tmp);
        // Keep dompdf's own font dir (it holds the family cache + the DejaVu
        // subset it generates). Only redirect it when it is not writable, which
        // is the case on read-only container filesystems.
        if (!@is_writable($fontDir)) {
            $alt = $tmp . '/dompdf-fonts';
            if (!is_dir($alt)) {
                @mkdir($alt, 0777, TRUE);
            }
            if (is_dir($alt) && @is_writable($alt)) {
                foreach ((array) glob($fontDir . '/*') as $f) {
                    if (is_file($f)) {
                        @copy($f, $alt . '/' . basename($f));
                    }
                }
                $options->set('fontDir', $alt);
                $options->set('fontCache', $alt);
            }
        }
        $options->set('isRemoteEnabled', FALSE);
        $options->set('isHtml5ParserEnabled', TRUE);
        $options->set('isFontSubsettingEnabled', TRUE);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('dpi', $opt['dpi']);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->setPaper($opt['paper'], $opt['orientation']);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        if (!empty($opt['footer_lines'])) {
            try {
                $this->drawFooter($dompdf, $opt);
            } catch (Exception $e) {
                $this->notices[] = 'PDF footer skipped: ' . $e->getMessage();
            }
        }

        $this->lastEngine = 'dompdf';
        try {
            return $dompdf->output(array('compress' => 1));
        } catch (Exception $e) {
            log_message('error', 'AdominPdf: dompdf output failed - ' . $e->getMessage());
            $this->notices[] = 'dompdf output failed: ' . $e->getMessage();
            return NULL;
        }
    }

    /**
     * dompdf has no --footer-html, so the footer is queued on the canvas and
     * painted on every page; {PAGE_NUM}/{PAGE_COUNT} are substituted there.
     */
    private function drawFooter($dompdf, array $opt)
    {
        $canvas = $dompdf->getCanvas();
        if (!$canvas) {
            return;
        }
        $font = $this->canvasFont($dompdf);
        if (!$font) {
            $this->notices[] = 'no font could be resolved for the PDF footer';
            return;
        }
        $size = 7;
        $grey = array(110, 118, 129);
        $pageWidth = $canvas->get_width();
        $pageHeight = $canvas->get_height();
        $left = $opt['margins']['left'] * 2.834645;
        $right = $opt['margins']['right'] * 2.834645;
        $lines = $opt['footer_lines'];
        $baseY = $pageHeight - 6 * 2.834645;

        foreach ($lines as $index => $line) {
            $y = $baseY - (count($lines) - 1 - $index) * ($size + 4);
            foreach (array('left', 'center', 'right') as $align) {
                if (!isset($line[$align]) || $line[$align] === '') {
                    continue;
                }
                $text = trim(strip_tags($line[$align]));
                $width = strlen($text) * $size * 0.52;
                try {
                    $width = $canvas->get_text_width($text, $font, $size);
                } catch (Exception $e) {

                }
                $x = $left;
                if ($align === 'right') {
                    $x = max($left, $pageWidth - $right - $width);
                } elseif ($align === 'center') {
                    $x = max($left, ($pageWidth - $width) / 2);
                }
                $canvas->page_text($x, $y, $text, $font, $size, $index ? $grey : array(60, 64, 67));
            }
            // hairline above the first footer line
            if ($index === 0 && count($lines) > 1) {
                try {
                    $canvas->line($left, $y - $size - 3, $pageWidth - $right, $y - $size - 3, $grey, 0.4);
                } catch (Exception $e) {

                }
            }
        }
    }

    private function canvasFont($dompdf)
    {
        $metrics = NULL;
        if (method_exists($dompdf, 'getFontMetrics')) {
            $metrics = $dompdf->getFontMetrics();
        } elseif (method_exists($dompdf, 'get_font_metrics')) {
            $metrics = $dompdf->get_font_metrics();
        }
        foreach (array('DejaVu Sans', 'sans-serif', 'serif', 'helvetica') as $family) {
            if (!$metrics) {
                break;
            }
            foreach (array('getFont', 'get_font') as $method) {
                if (!method_exists($metrics, $method)) {
                    continue;
                }
                try {
                    $path = $metrics->$method($family, 'normal');
                } catch (Exception $e) {
                    $path = NULL;
                }
                if (is_string($path) && $path !== '') {
                    return $path;
                }
            }
        }
        return NULL;
    }

    /* ------------------------------------------------------------------ *
     *  wkhtmltopdf renderer (opt in, e.g. on a VPS with patched-qt build)
     * ------------------------------------------------------------------ */

    private function withWkhtmltopdf($html, array $opt)
    {
        if (!function_exists('exec')) {
            $this->notices[] = 'exec() is disabled - using dompdf';
            return NULL;
        }
        $tmp = $this->writableDir($opt['cache_dir']);
        $uniq = $tmp . '/adomin-' . getmypid() . '-' . uniqid();
        $htmlFile = $uniq . '.html';
        $pdfFile = $uniq . '.pdf';
        $footerFile = $uniq . '-footer.html';
        $logFile = $tmp . '/pdfs.log';

        if (@file_put_contents($htmlFile, $html . $this->inlineFooterBlock($opt)) === FALSE) {
            $this->notices[] = 'cannot write a temporary HTML file - using dompdf';
            return NULL;
        }
        @file_put_contents($footerFile, $this->footerDocument($opt));

        $cmd = escapeshellarg($opt['wkhtmltopdf'])
            . ' --quiet --encoding utf-8 --enable-local-file-access --disable-smart-shrinking'
            . ' --load-error-handling ignore --stop-load-error --disable-js'
            . ' -d ' . (int) $opt['dpi']
            . ' -s ' . escapeshellarg($opt['paper'])
            . ($opt['orientation'] === 'landscape' ? ' -O Landscape' : '')
            . ' -L ' . round($opt['margins']['left']) . 'mm -R ' . round($opt['margins']['right']) . 'mm'
            . ' -T ' . round($opt['margins']['top']) . 'mm -B ' . round($opt['margins']['bottom']) . 'mm';
        if (is_file($footerFile)) {
            $cmd .= ' --footer-html ' . escapeshellarg(realpath($footerFile)) . ' --footer-spacing 3';
        }
        $cmd .= ' ' . escapeshellarg($htmlFile) . ' ' . escapeshellarg($pdfFile)
            . ' >> ' . escapeshellarg($logFile) . ' 2>&1';

        $start = microtime(TRUE);
        $status = 1;
        @exec($cmd, $out, $status);
        $pdf = NULL;
        if (is_file($pdfFile) && filesize($pdfFile) > 600) {
            $head = file_get_contents($pdfFile, FALSE, NULL, 0, 5);
            if ($head === '%PDF-') {
                $pdf = file_get_contents($pdfFile);
                $this->lastEngine = 'wkhtmltopdf';
            }
        }
        if (!$pdf) {
            $this->notices[] = 'wkhtmltopdf failed after ' . round(microtime(TRUE) - $start, 1) . 's (exit ' . $status . ') - using dompdf';
            log_message('error', 'AdominPdf: wkhtmltopdf failed, exit=' . $status . ' log=' . $this->tail($logFile));
        }
        foreach (array($htmlFile, $pdfFile, $footerFile) as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        return $pdf;
    }

    private function binaryAvailable($binary)
    {
        return $binary && is_file($binary) && is_executable($binary);
    }

    private function tail($file, $bytes = 1500)
    {
        if (!is_file($file)) {
            return '';
        }
        $size = filesize($file);
        return @file_get_contents($file, FALSE, NULL, max(0, $size - $bytes));
    }

    /* ------------------------------------------------------------------ *
     *  Document preparation
     * ------------------------------------------------------------------ */

    private function resolveOptions(array $options)
    {
        $conf = adomin_conf('pdf');
        if (!is_array($conf)) {
            $conf = array();
        }
        $opt = array(
            'engine'        => 'dompdf',
            'wkhtmltopdf'   => '/usr/bin/wkhtmltopdf',
            'stamp'         => TRUE,
            'paper'         => 'A4',
            'dpi'           => 96,
            'orientation'   => 'portrait',
            'inline_assets' => TRUE,
            'framework_css' => FALSE,
            'font_face'     => TRUE,
            'cache_dir'     => '',
            'css'           => '',
            'extra_css_files' => array('assets/users/css/pdf-base.css'),
            'footer_text'   => '',
            'margins'       => array('left' => 8, 'right' => 8, 'top' => 12, 'bottom' => 18),
            'footer_lines'  => array(),
        );
        if (isset($conf['engine'])) {
            $opt['engine'] = strtolower($conf['engine']) === 'wkhtmltopdf' ? 'wkhtmltopdf' : 'dompdf';
        }
        foreach (array('wkhtmltopdf', 'paper', 'dpi', 'cache_dir', 'footer_text') as $key) {
            if (isset($conf[$key]) && $conf[$key] !== '') {
                $opt[$key] = $conf[$key];
            }
        }
        $opt['stamp'] = isset($conf['stamp']) ? (bool) $conf['stamp'] : TRUE;
        $opt['inline_assets'] = isset($conf['inline_assets']) ? (bool) $conf['inline_assets'] : TRUE;
        $opt['framework_css'] = !empty($conf['framework_css']);
        foreach (array('left' => 'margin_left', 'right' => 'margin_left', 'top' => 'margin_top') as $side => $key) {
            if (isset($conf[$key]) && $conf[$key] !== '') {
                $opt['margins'][$side] = (float) $conf[$key];
            }
        }
        foreach (array('engine', 'paper', 'dpi', 'orientation', 'stamp', 'inline_assets', 'framework_css',
            'cache_dir', 'css', 'extra_css_files', 'font_face', 'footer_text') as $key) {
            // NULL means "caller did not care": keep the configured default,
            // a null paper or dpi would break dompdf
            if (array_key_exists($key, $options) && $options[$key] !== NULL && $options[$key] !== '') {
                $opt[$key] = $options[$key];
            }
        }
        if (isset($options['margins']) && is_array($options['margins'])) {
            foreach ($options['margins'] as $k => $v) {
                if (isset($opt['margins'][$k]) || in_array($k, array('left', 'right', 'top', 'bottom'), TRUE)) {
                    $opt['margins'][$k] = (float) $v;
                }
            }
        }
        if (isset($options['extra_css_files'])) {
            $opt['extra_css_files'] = (array) $options['extra_css_files'];
        }
        $opt['engine'] = strtolower($opt['engine']) === 'wkhtmltopdf' ? 'wkhtmltopdf' : 'dompdf';
        $opt['orientation'] = strtolower($opt['orientation']) === 'landscape' ? 'landscape' : 'portrait';
        $opt['footer_lines'] = $this->footerLines($opt, isset($options['footer']) && is_array($options['footer']) ? $options['footer'] : NULL);
        return $opt;
    }

    /**
     * Footer = document reference line (+ page counter) and the Adomin stamp.
     */
    private function footerLines(array $opt, $custom = NULL)
    {
        $lines = array();
        if ($custom) {
            $line = array();
            foreach (array('left', 'center', 'right') as $align) {
                if (!empty($custom[$align])) {
                    $line[$align] = $custom[$align];
                }
            }
            if ($line) {
                if (!isset($line['right']) && !$opt['stamp']) {
                    $line['right'] = '{PAGE_NUM} / {PAGE_COUNT}';
                }
                $lines[] = $line;
            }
        } elseif (!empty($opt['footer_text'])) {
            $lines[] = array('left' => $opt['footer_text'], 'right' => '{PAGE_NUM} / {PAGE_COUNT}');
        }
        if ($opt['stamp']) {
            $lines[] = array('right' => $lines ? adomin_stamp() : adomin_stamp() . ' · {PAGE_NUM} / {PAGE_COUNT}');
        }
        if (!$lines && $opt['engine'] === 'wkhtmltopdf') {
            $lines[] = array('right' => '{PAGE_NUM} / {PAGE_COUNT}');
        }
        return $lines;
    }

    /**
     * Wrap partial HTML, inline local assets, inject the base CSS.
     */
    private function prepare($html, array $opt)
    {
        $isDoc = stripos($html, '<html') !== FALSE;
        if (!$isDoc) {
            $css = '';
            foreach ($opt['extra_css_files'] as $file) {
                $abs = $this->publicPath($file);
                if ($abs) {
                    $css .= "\n" . file_get_contents($abs);
                }
            }
            $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Document</title>'
                . ($css !== '' ? '<style>' . $css . '</style>' : '') . '</head><body>' . $html . '</body></html>';
        }
        if ($opt['inline_assets']) {
            $html = $this->inlineStylesheets($html, $opt);
            $html = $this->inlineImages($html);
        } else {
            // never let the renderer block on a CDN
            $html = preg_replace('#<link[^>]+href=["\']https?://[^"\']+["\'][^>]*>#i', '', $html);
            $html = preg_replace('#<script[^>]+src=["\']https?://[^"\']+["\'][^>]*>#i', '', $html);
        }
        $html = $this->injectHead($html, $this->baseCss($opt));
        return $html;
    }

    private function baseCss(array $opt)
    {
        $m = $opt['margins'];
        $accent = adomin_conf('brand', 'accent') ?: '#0b6bcb';
        $css = '
        @page { margin: 0; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11px;
            line-height: 1.45;
            color: #1f2328;
            margin: ' . $m['top'] . 'mm ' . $m['right'] . 'mm ' . ($m['bottom'] + 6) . 'mm ' . $m['left'] . 'mm;
        }
        table { border-collapse: collapse; }
        img { max-width: 100%; }
        .adomin-pdf-stamp {
            margin-top: 16px; padding-top: 6px;
            border-top: 1px solid #e3e6ea;
            font-size: 8.5px; color: #6e7681; text-align: right;
        }
        .adomin-pdf-stamp b { color: ' . $accent . '; }
        .adomin-pdf-line .f-left { float: left; }
        .adomin-pdf-line .f-center { text-align: center; }
        .adomin-pdf-line .f-right { float: right; }
        .adomin-pdf-line:after { content: ""; display: table; clear: both; }
        ';
        if ($opt['font_face']) {
            $ttf = realpath(APPPATH . 'libraries/dompdf/lib/fonts/DejaVuSans.ttf');
            if ($ttf) {
                $bold = realpath(APPPATH . 'libraries/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
                $css .= '@font-face { font-family: "DejaVu Sans"; font-style: normal; font-weight: normal;'
                    . ' src: url("' . str_replace('\\', '/', $ttf) . '") format("truetype"); }';
                if ($bold) {
                    $css .= '@font-face { font-family: "DejaVu Sans"; font-style: normal; font-weight: bold;'
                        . ' src: url("' . str_replace('\\', '/', $bold) . '") format("truetype"); }';
                }
            }
        }
        if (!empty($opt['css'])) {
            $css .= $opt['css'];
        }
        return $css;
    }

    /**
     * Replace <link rel="stylesheet"> that point at our own document root with
     * inline <style> so the renderer never blocks on the network.
     */
    private function inlineStylesheets($html, array $opt)
    {
        return preg_replace_callback(
            '#<link\b[^>]*>#i',
            function ($m) use ($opt) {
                $tag = $m[0];
                if (stripos($tag, 'stylesheet') === FALSE) {
                    return $tag;
                }
                if (!preg_match('#href=["\']([^"\']+)["\']#i', $tag, $h)) {
                    return $tag;
                }
                $abs = $this->publicPath($h[1]);
                if (!$abs) {
                    return '';
                }
                if (!$opt['framework_css'] && preg_match('#/(bootstrap|font-awesome|animate)[^/]*\.css$#i', $abs)) {
                    // framework css is heavy and mostly noise for a PDF: our own
                    // pdf-base.css carries the grid/table spacing that matters
                    return '';
                }
                $css = @file_get_contents($abs);
                if (!$css) {
                    return '';
                }
                $dir = rtrim(str_replace('\\', '/', dirname($abs)), '/') . '/';
                $css = preg_replace('#url\(\s*([\'"]?)(?!data:|https?:|#)([^\'")]+)\1#i', 'url($1' . $dir . '$2', $css);
                return '<style>' . $css . '</style>';
            },
            $html
        );
    }

    /**
     * Inline <img> as data URIs. dompdf has remote fetching disabled, so a URL
     * src would silently disappear (and a missing file made logos vanish).
     */
    private function inlineImages($html)
    {
        return preg_replace_callback(
            '#<img\b[^>]*>#i',
            function ($m) {
                $tag = $m[0];
                if (!preg_match('#src=["\']([^"\']*)["\']#i', $tag, $h)) {
                    return $tag;
                }
                $src = $h[1];
                if ($src === '' || stripos($src, 'data:') === 0) {
                    return $tag;
                }
                // a file this app serves itself is always read from disk - no
                // request, whatever the host does
                $abs = $this->publicPath($src);
                if ($abs) {
                    $data = adomin_data_uri($abs);
                    return $data ? str_replace($src, $data, $tag) : '';
                }
                // an off-site logo (the Cloudinary/S3 URL a free-tier install
                // uses because its own disk is wiped) has no local file and
                // dompdf has isRemoteEnabled off: fetch it once, bounded.
                if (preg_match('#^https?://#i', $src)) {
                    $data = $this->remoteImageUri($src);
                    return $data ? str_replace($src, $data, $tag) : '';
                }
                return '';
            },
            $html
        );
    }

    /**
     * Fetch an off-site image and turn it into a data URI.
     *
     * Deliberately paranoid, because this runs inside a request on a 0.1 CPU
     * instance with one worker: https only, three second timeout, a 2 MB cap,
     * a per-render cache, and any URL whose host is this app itself is
     * refused outright - a self-request is what dead-locked the old
     * wkhtmltopdf footer. Returns NULL on any problem, which just drops the
     * <img> the way an unreadable local file already does.
     *
     * @param string $url
     * @return string|null
     */
    private function remoteImageUri($url)
    {
        if (isset($this->remoteImages[$url])) {
            return $this->remoteImages[$url];
        }
        $this->remoteImages[$url] = NULL;

        if (!adomin_conf('pdf', 'remote_images') || stripos($url, 'https://') !== 0) {
            $this->notices[] = 'AdominPdf: image not fetched (https only, or ADOMIN_PDF_REMOTE_IMAGES=0): ' . $url;
            return NULL;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $own = strtolower((string) parse_url((string) base_url(), PHP_URL_HOST));
        if ($host === '' || ($own !== '' && $host === $own)) {
            $this->notices[] = 'AdominPdf: refusing to fetch its own host during a render (would deadlock): ' . $url;
            return NULL;
        }

        $max = 2097152;
        $context = stream_context_create(array(
            'http' => array(
                'method' => 'GET',
                'timeout' => 3,
                'max_redirects' => 2,
                'protocol_version' => 1.1,
                'user_agent' => 'AdominInvoices/1.0 (+pdf)',
                'ignore_errors' => FALSE,
            ),
            'ssl' => array(
                'verify_peer' => TRUE,
                'verify_peer_name' => TRUE,
            ),
        ));
        $body = @file_get_contents($url, FALSE, $context, 0, $max + 1);
        if ($body === FALSE || $body === '' || strlen($body) > $max) {
            $this->notices[] = 'AdominPdf: could not fetch the logo, printing the document without it: ' . $url;
            return NULL;
        }

        $mime = $this->imageMime($body);
        if ($mime === NULL) {
            $this->notices[] = 'AdominPdf: unsupported image body from ' . $url;
            return NULL;
        }
        $this->remoteImages[$url] = 'data:' . $mime . ';base64,' . base64_encode($body);
        return $this->remoteImages[$url];
    }

    /**
     * Sniff the format from the bytes rather than trusting the URL, so a CDN
     * that answers with an error page never reaches dompdf's image parser.
     * SVG is excluded: dompdf cannot rasterise it from a data URI.
     *
     * @param string $body
     * @return string|null
     */
    private function imageMime($body)
    {
        $head = substr($body, 0, 12);
        if (strncmp($head, "\x89PNG\x0D\x0A\x1A\x0A", 8) === 0) { return 'image/png'; }
        if (strncmp($head, "\xFF\xD8\xFF", 3) === 0) { return 'image/jpeg'; }
        if (strncmp($head, 'GIF87A', 6) === 0 || strncmp($head, 'GIF89a', 6) === 0) { return 'image/gif'; }
        if (strncmp($head, 'BM', 2) === 0) { return 'image/bmp'; }
        if (strncmp($head, 'RIFF', 4) === 0 && substr($body, 8, 4) === 'WEBP') { return 'image/webp'; }
        return NULL;
    }

    private function injectHead($html, $css)
    {
        $style = '<style>' . $css . '</style>';
        if (stripos($html, '</head>') !== FALSE) {
            return preg_replace('#</head>#i', $style . '</head>', $html, 1);
        }
        if (stripos($html, '<body') !== FALSE) {
            return preg_replace('#<body[^>]*>#i', '$0' . $style, $html, 1);
        }
        return $style . $html;
    }

    /**
     * Footer repeated visually at the end of the document (single page docs read
     * better with the stamp inline than only in the page margin).
     */
    private function inlineFooterBlock(array $opt)
    {
        if (!$opt['stamp'] || !$opt['footer_lines']) {
            return '';
        }
        $out = '';
        foreach ($opt['footer_lines'] as $line) {
            $parts = '';
            foreach (array('left', 'center', 'right') as $align) {
                if (!isset($line[$align])) {
                    continue;
                }
                $text = str_replace(array('{PAGE_NUM}', '{PAGE_COUNT}'), array('1', '1'), $line[$align]);
                $parts .= '<span class="f-' . $align . '">' . htmlspecialchars(strip_tags($text), ENT_QUOTES, 'UTF-8') . '</span>';
            }
            $out .= '<div class="adomin-pdf-line">' . $parts . '</div>';
        }
        return '<div class="adomin-pdf-stamp">' . $out . '</div>';
    }

    /**
     * Standalone footer document for wkhtmltopdf --footer-html. Local file, no
     * HTTP: the page-number substitution that used to come from /pdffooter is
     * done by the footer's own script plus our server-side values.
     */
    private function footerDocument(array $opt)
    {
        $rows = '';
        foreach ($opt['footer_lines'] as $line) {
            $cells = '';
            foreach (array('left', 'center', 'right') as $align) {
                $text = isset($line[$align]) ? $line[$align] : '';
                $text = htmlspecialchars(strip_tags($text), ENT_QUOTES, 'UTF-8');
                $cells .= '<td style="text-align:' . ($align === 'left' ? 'left' : $align) . ';width:34%;">'
                    . '<span class="' . $align . '">' . $text . '</span></td>';
            }
            $rows .= '<tr>' . $cells . '</tr>';
        }
        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            . 'body{font-family:"DejaVu Sans",Arial,Helvetica,sans-serif;font-size:7.5pt;color:#6e7681;margin:2px 0 0 0;}'
            . 'table{width:100%;border-collapse:collapse;}'
            . 'td{padding:0;vertical-align:middle;}'
            . '</style></head><body><table>' . $rows . '</table></body></html>';
    }

    /* ------------------------------------------------------------------ *
     *  Filesystem helpers
     * ------------------------------------------------------------------ */

    /**
     * Absolute path of a document-root relative file or URL, or NULL.
     */
    private function publicPath($url)
    {
        $path = (string) $url;
        if (preg_match('#^(?:https?:)?//[^/]+#i', $path)) {
            $host = parse_url($path, PHP_URL_HOST);
            $self = isset($_SERVER['HTTP_HOST']) ? preg_replace('#:\d+$#', '', $_SERVER['HTTP_HOST']) : '';
            if ($host && $self && $host !== $self) {
                return NULL; // third party asset: never fetched
            }
            $path = parse_url($path, PHP_URL_PATH);
        } elseif (strpos($path, '//') === 0) {
            $path = substr($path, 2);
        }
        $path = parse_url($path, PHP_URL_PATH);
        if (!$path) {
            return NULL;
        }
        $path = ltrim(rawurldecode($path), '/');
        foreach (array(FCPATH . $path, FCPATH . preg_replace('#^index\.php/#', '', $path)) as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }
        return NULL;
    }

    private function writableDir($preferred = '')
    {
        $candidates = array();
        if ($preferred) {
            $candidates[] = rtrim($preferred, '/\\');
        }
        $candidates[] = rtrim(sys_get_temp_dir(), '/\\');
        $candidates[] = rtrim(APPPATH, '/\\') . '/cache';
        $candidates[] = rtrim(FCPATH, '/\\') . '/tmp';
        foreach ($candidates as $dir) {
            if ($dir === '' || $dir === FALSE) {
                continue;
            }
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, TRUE);
            }
            if (is_dir($dir) && @is_writable($dir)) {
                return $dir;
            }
        }
        return rtrim(APPPATH, '/\\') . '/cache';
    }

}
