<?php

class MY_Controller extends HEAD_Controller
{

    public function __construct()
    {
        parent::__construct();

        /*
         * PublicModel backs nearly every public path: registration, login, the
         * share link, the customer accept page. Nothing loads it - the model
         * autoload array is empty and no controller calls $this->load->model()
         * - so `$this->PublicModel` used to resolve through MX_Controller's
         * __get() to an undefined property and come back NULL.
         *
         * That only showed up *after* the CSRF gate was passed: until then every
         * POST was rejected before reaching a line that touched the model, so
         * GET worked and POST answered 500 with "Call to a member function on
         * null". Loading it here fixes all of those callers at once.
         */
        if ($this->PublicModel === NULL)
        {
            $this->load->model('PublicModel');
        }
    }

    public function render($view, $head, $data = null)
    {
        $this->load->view('parts/header', $head);
        $this->load->view($view, $data);
        $this->load->view('parts/footer');
    }

    public function setUserLogin($email, $type)
    {
        $userInfo = $this->PublicModel->getUserInfoFromEmail($email, $type);
        if (!empty($userInfo)) {
            if ($type == 2) {
                $email = $userInfo['employee']['email'];
            } else {
                $email = $userInfo['user']['email'];
            }
            $_SESSION['user_login'] = array(
                'email' => $email,
                'type' => $type
            );
            redirect(lang_url('user'));
        } else {
            log_message('error', ':Error: - Cant set user login for email: ' . $email);
            redirect(base_url());
        }
    }

}
