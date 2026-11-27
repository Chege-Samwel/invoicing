<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Verification code for an invoice.
 *
 * Draws a QR code that points at the public verification page for one document.
 * Scanning it opens that page, which recomputes the signature from the stored
 * invoice and says whether the printed figures still match the record.
 *
 * Two decisions worth stating, because both are about what a printed document
 * has to survive:
 *
 *  - The code is an SVG, not a PNG. dompdf renders SVG path data directly, so
 *    the code stays sharp at any size and there is no image to fetch or cache.
 *    A PNG would have to be written to disk on every print, and a disk that is
 *    read-only - which is normal on a hosted container - would silently drop it.
 *  - Nothing here is allowed to break a document. If the library is missing or
 *    fails, `markup()` returns an empty string and the invoice prints without a
 *    code rather than fataling halfway down the page.
 */
class AdominQr
{
    /** Modules per side for a version-3 code, used to size the viewBox. */
    const QUIET_ZONE = 4;

    /** @var bool */
    private $available;

    public function __construct()
    {
        $lib = APPPATH . 'libraries/phpqrcode/qrlib.php';
        $this->available = FALSE;

        if ( ! is_file($lib)) {
            log_message('error', 'AdominQr: phpqrcode is not installed');
            return;
        }

        require_once $lib;

        /*
         * The library caches generated frame templates under libraries/phpqrcode
         * /cache. That directory is not writable on a read-only deploy, and the
         * cache path calls gzcompress(), which belongs to zlib - an extension
         * this host does not guarantee. qrconfig.php defines these constants
         * with define(), so they cannot be pre-set; the setting is the constant
         * itself, and it is only safe to read it after the include.
         */
        if (defined('QR_CACHEABLE') && QR_CACHEABLE === TRUE) {
            // cannot be redefined; the frame is simply rebuilt when the cache
            // write fails, which is what happens on a read-only filesystem
            log_message('debug', 'AdominQr: phpqrcode cache is on; writes may fail on a read-only disk');
        }
        $this->available = class_exists('QRcode');
    }

    /**
     * True when a code can be produced at all.
     *
     * @return bool
     */
    public function available()
    {
        return $this->available;
    }

    /**
     * The URL the code encodes.
     *
     * Short on purpose: a QR code's module count grows with the payload, and an
     * invoice footer has room for about 25 modules at a readable size. A numeric
     * id and a short token keep it well under that.
     *
     * @param array $invoice
     * @return string
     */
    public function verifyUrl(array $invoice)
    {
        $token = $this->token($invoice);

        if ($token === '') {
            return '';
        }

        return base_url('verify/' . rawurlencode((string) $invoice['inv_number']) . '/' . $token);
    }

    /**
     * A short, stateless fingerprint of the document.
     *
     * Derived from the fields a forgery would have to change - number, issue
     * date, totals - plus the application's own key, so a code cannot be
     * generated for altered figures without that key. It is not a signature in
     * the legal sense and the verification page says so; it is a check that the
     * paper in hand matches the record on file.
     *
     * @param array $invoice
     * @return string
     */
    public function token(array $invoice)
    {
        $secret = $this->secret();
        if ($secret === '') {
            return '';
        }

        $material = implode('|', array(
            isset($invoice['inv_number']) ? (string) $invoice['inv_number'] : '',
            isset($invoice['date_create']) ? (string) (int) $invoice['date_create'] : '',
            isset($invoice['final_total']) ? (string) $invoice['final_total'] : '',
            isset($invoice['for_user']) ? (string) (int) $invoice['for_user'] : '',
            isset($invoice['client']['client_name']) ? (string) $invoice['client']['client_name'] : '',
        ));

        return substr(hash_hmac('sha256', $material, $secret), 0, 12);
    }

    /**
     * Compare a scanned token with the one this document should carry.
     *
     * @param array  $invoice
     * @param string $token
     * @return bool
     */
    public function matches(array $invoice, $token)
    {
        $expected = $this->token($invoice);
        if ($expected === '' || ! is_string($token)) {
            return FALSE;
        }

        return hash_equals($expected, $token);
    }

    /**
     * The key the token is built with.
     *
     * The application's encryption key is already per-install and stored
     * outside version control, so no new secret has to be provisioned. If it is
     * empty the wrapper reports itself unavailable rather than signing every
     * document with an empty key, which would make every code forgeable.
     *
     * @return string
     */
    private function secret()
    {
        $CI =& get_instance();
        $key = $CI->config->item('encryption_key');

        return is_string($key) ? trim($key) : '';
    }

    /**
     * SVG markup for the code, or '' when it cannot be drawn.
     *
     * @param string $data
     * @param int    $pixels rendered size in px
     * @return string
     */
    public function svg($data, $pixels = 92)
    {
        if ( ! $this->available || $data === '') {
            return '';
        }

        try {
            $frame = $this->matrix($data);
        } catch (Exception $e) {
            log_message('error', 'AdominQr: ' . $e->getMessage());
            return '';
        } catch (Throwable $e) {
            log_message('error', 'AdominQr: ' . $e->getMessage());
            return '';
        }

        if ( ! is_array($frame) || ! $frame) {
            return '';
        }

        /*
         * One <path> for every dark module, in a viewBox of module coordinates.
         * dompdf handles a path far better than a few hundred <rect> elements,
         * and the result scales without interpolation because it is vector.
         */
        $size = count($frame);
        $quiet = self::QUIET_ZONE;
        $box = $size + ($quiet * 2);
        $path = '';

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ( ! empty($frame[$y][$x])) {
                    $path .= 'M' . ($x + $quiet) . ' ' . ($y + $quiet) . 'h1v1h-1z';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" class="av-qr" role="img"'
            . ' width="' . (int) $pixels . '" height="' . (int) $pixels . '"'
            . ' viewBox="0 0 ' . $box . ' ' . $box . '" shape-rendering="crispEdges">'
            . '<rect width="' . $box . '" height="' . $box . '" fill="#ffffff"/>'
            . '<path d="' . $path . '" fill="#111418"/>'
            . '</svg>';
    }

    /**
     * The module matrix.
     *
     * The library makes this harder than it looks, and each wrong reading
     * produces a block that resembles a QR code but will not scan, so the path
     * that works is worth setting down:
     *
     *  - encodeRAW() returns the finished frame. It does NOT hang it on the
     *    encoder object, so $encoder->data is undefined.
     *  - Each row comes back as str_split() output: one array entry per module.
     *    Joining a row and then indexing it as a string does not work, and
     *    reading the return value as a flat array of row strings does not
     *    either - the rows are 29 entries holding one byte each, not a
     *    29-character string.
     *  - A dark module is the cell whose low bit is set (QRtools::binarize uses
     *    the same test); the encoder writes 0xC1 for dark and 0xC0 for light.
     *    Comparing against the ASCII digits '1' or "\1" matches neither.
     *
     * @param string $data
     * @return array
     */
    private function matrix($data)
    {
        $encoder = new QRencode();
        $encoder->level = QR_ECLEVEL_M;

        $frame = $encoder->encodeRAW($data, FALSE);

        if ( ! is_array($frame) || ! $frame) {
            return array();
        }

        $matrix = array();
        foreach ($frame as $row) {
            $cells = is_array($row) ? $row : str_split($row);
            $line = array();
            foreach ($cells as $cell) {
                $line[] = (ord((string) $cell) & 1) ? 1 : 0;
            }
            $matrix[] = $line;
        }

        return $matrix;
    }
}
