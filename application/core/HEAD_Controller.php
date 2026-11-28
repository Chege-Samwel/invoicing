<?php

class HEAD_Controller extends MX_Controller
{

    public function __construct()
    {
        parent::__construct();

        /*
         * A controller constructor only runs after CodeIgniter's Input class has
         * verified the CSRF token, so this breadcrumb answers a question that
         * otherwise needs guesswork: did the POST get past the CSRF check and
         * into a controller, or was it turned away before any of our code ran?
         * No-op unless the request is being traced.
         */
        function_exists('adomin_trace') && adomin_trace('controller: ' . get_class($this));

        $this->output->enable_profiler(ENVIRONMENT == 'development');
    }

}
