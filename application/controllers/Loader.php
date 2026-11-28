<?php

defined('BASEPATH') OR exit('No direct script access allowed');
/* Set internal character encoding to UTF-8 */
mb_internal_encoding("UTF-8");

class Loader extends MY_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->helper('file');
    }

    /*
     * Load language javascript file
     */

    public function jsFile($file = null)
    {
        $contents = file_get_contents(APPPATH . 'language' . DIRECTORY_SEPARATOR . MY_LANGUAGE_FULL_NAME . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . $file);
        if (!$contents) {
            header('HTTP/1.1 404 Not Found');
            return;
        }

        /*
         * This is a <script src> target, so it has to arrive as JavaScript.
         * PHP answers with its default_mimetype (text/html) for anything that
         * does not set a type, and .htaccess sends
         * X-Content-Type-Options: nosniff, so the browser refuses to run it:
         *
         *   Refused to execute script from '.../loadlanguage/all.js' because
         *   its MIME type ('text/html') is not executable
         *
         * The file defines the `lang` object general.js uses for the form
         * validation messages, so blocking it breaks more than the messages:
         * registerValidate() throws at lang.invalid_email and never calls
         * submit(), which is the "nothing happens" registration failure.
         *
         * application/javascript is on the Fetch spec's JavaScript MIME type
         * list, so it is accepted with nosniff still enforced. (The `js` entry
         * in config/mimes.php offers the legacy application/x-javascript and
         * text/plain, neither of which is worth relying on.)
         */
        header('Content-Type: application/javascript; charset=UTF-8');
        header('Cache-Control: public, max-age=3600');
        echo $contents;
    }

}
