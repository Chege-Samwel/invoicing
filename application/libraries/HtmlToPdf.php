<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * Convert html of a document (invoice, protocol, movement, warranty card) to PDF.
 *
 * Adomin Technologies fork: this class used to build a wkhtmltopdf command line
 * whose --footer-html was an *HTTP URL pointing back at this same application*.
 * On shared/free hosting (Render free tier: 0.1 CPU, one PHP worker, cold start
 * of ~1 minute) the PDF request and the footer request are the same worker, so
 * wkhtmltopdf waited forever for a page it could never be served: the "broken
 * PDF". The old code also assumed a hard-coded /home/kirilkirkov91 binary.
 *
 * Everything now goes through AdominPdf, which renders locally (bundled dompdf
 * by default, wkhtmltopdf only if you opt in with PDF_ENGINE=wkhtmltopdf and the
 * binary exists) and builds its footer in-process, so no network call at all.
 *
 * The public API below is unchanged, so every existing controller keeps working.
 */
class HtmlToPdf
{

    /** @var string document number, printed in the footer */
    private $num = '';

    /** @var string translated document type, printed in the footer */
    private $type = '';

    /** @var string translated word for "Page", printed in the footer */
    private $pageTranslation = 'Page';

    /** @var AdominPdf */
    private $renderer;

    /** @var string last engine used */
    private $engine = '';

    public function __construct()
    {
        $CI = & get_instance();
        if (!isset($CI->adominpdf)) {
            $CI->load->library('AdominPdf');
        }
        $this->renderer = $CI->adominpdf;
    }

    /**
     * @param  string $html
     * @param  int    $left_right margin in mm
     * @param  int    $top_bot    margin in mm
     * @param  int    $dpi
     * @param  string $size       paper size
     * @param  string $extra_params raw wkhtmltopdf params (kept for compatibility)
     * @return string PDF contents
     */
    public function generatePdf(
    $html, $left_right = null, $top_bot = null, $dpi = null, $size = null, $extra_params = null
    )
    {
        $margins = array(
            'left' => $left_right === null ? 8 : (float) $left_right,
            'right' => $left_right === null ? 8 : (float) $left_right,
            'top' => $top_bot === null ? 12 : (float) $top_bot,
            'bottom' => $top_bot === null ? 16 : (float) $top_bot + 4,
        );

        $page = $this->pageTranslation !== '' ? $this->pageTranslation : 'Page';

        $pdf = $this->renderer->fromHtml($html, array(
            'margins' => $margins,
            'dpi' => $dpi === null ? NULL : (int) $dpi,
            'paper' => $size === null ? NULL : $size,
            'orientation' => strpos((string) $extra_params, 'landscape') !== FALSE ? 'landscape' : NULL,
            'footer' => array(
                'left' => trim($this->type . ($this->num !== '' ? ' № ' . $this->num : '')),
                'right' => $page . ' {PAGE_NUM} / {PAGE_COUNT}',
            ),
            'stamp' => TRUE,
        ));
        $this->engine = $this->renderer->getLastEngine();

        foreach ($this->renderer->getNotices() as $notice) {
            log_message('info', 'HtmlToPdf: ' . $notice);
        }

        return $pdf;
    }

    public function setNum($num)
    {
        $this->num = (string) $num;
    }

    /**
     * Stream a generated document to the browser (inline preview, or a
     * download when $download is TRUE or ?download=1 is present).
     *
     * @param string $pdf
     * @param string $filename
     * @param bool   $download
     */
    public function output($pdf, $filename = 'document.pdf', $download = FALSE)
    {
        if (isset($_GET['download']) && $_GET['download'] == '1') {
            $download = TRUE;
        }
        $this->renderer->output($pdf, $filename, $download);
    }

    public function setType($type)
    {
        $this->type = (string) $type;
    }

    public function setPageTranslate($translation)
    {
        $this->pageTranslation = (string) $translation;
    }

    /**
     * 'dompdf' or 'wkhtmltopdf' - handy when debugging a deployment.
     *
     * @return string
     */
    public function getEngine()
    {
        return $this->engine;
    }

}
