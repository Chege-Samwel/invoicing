<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Registration extends MY_Controller
{
    /*
     * Breadcrumbs. A login POST on the hosted instance answers 500 with an
     * Apache page and no log line, so the only way to learn where it stopped is
     * to record each step as it happens (see application/adomin_trace.php).
     * These are no-ops unless the request is traced.
     */
    private function mark($label, $data = NULL)
    {
        function_exists('adomin_trace') && adomin_trace($label, $data);
    }

    /**
     * The terms and conditions.
     *
     * A plain page, no layout wrapper: it is read before anyone has an account,
     * and it is linked from the signup checkbox. The content lives in the view
     * so a deployment can amend it without touching the controller.
     */
    public function terms()
    {
        $head = array();
        $head['title'] = 'Terms and conditions · ' . adomin_conf('brand', 'name');
        $this->load->view('registration/terms', $head);
    }

    public function index()
    {
        $data = array();
        $head = array();
        if (isset($_POST['email'])) {
            $this->mark('register: POST accepted (CSRF passed)');

            // Keep what was typed so a rejected form comes back filled in. The
            // password is deliberately not kept.
            $this->session->set_flashdata('name', isset($_POST['name']) ? $_POST['name'] : '');
            $this->session->set_flashdata('phone', isset($_POST['phone']) ? $_POST['phone'] : '');
            $this->session->set_flashdata('email', $_POST['email']);

            /*
             * What the business does. Checked against the configured list rather
             * than stored as sent: the key is used to pick a set of starter
             * products, and an unknown one would seed nothing while looking
             * like it had. An empty answer means the browser did not submit the
             * field at all, which is not a reason to reject a registration
             * (the column is a nicety) - it simply keeps the default.
             */
            $this->load->library('AdominIndustry');
            $industry = isset($_POST['industry']) ? trim((string) $_POST['industry']) : '';
            if ($industry !== '' && ! $this->adominindustry->isValid($industry)) {
                $this->session->set_flashdata('resultRegister', 'Pick the trade you work in from the list.');
                redirect(lang_url('registration'));
            }
            $_POST['industry'] = $industry !== '' ? $industry : $this->adominindustry->fallback();
            $this->session->set_flashdata('industry', $_POST['industry']);

            if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                $this->session->set_flashdata('resultRegister', 'That email address does not look right. Check it and try again.');
                redirect(lang_url('registration'));
            }

            /*
             * The terms checkbox is required, and required HERE rather than
             * only in the browser. `required` on an input is a convenience for
             * the person filling the form; a POST that skips the field - an old
             * browser, a script, a replayed request - would otherwise create an
             * account that never agreed to anything.
             */
            $acceptedTerms = isset($_POST['accept_terms']) && (string) $_POST['accept_terms'] === '1';
            if (! $acceptedTerms) {
                $this->session->set_flashdata('resultRegister', 'Please read and accept the terms and conditions to create an account.');
                $this->session->set_flashdata('termsMissing', 1);
                redirect(lang_url('registration'));
            }
            $this->session->set_flashdata('termsAccepted', 1);

            $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
            $repeat = isset($_POST['password_repeat']) ? (string) $_POST['password_repeat'] : '';

            if (strlen($password) < 8) {
                $this->session->set_flashdata('resultRegister', 'Choose a password of at least 8 characters.');
                redirect(lang_url('registration'));
            }

            // The confirmation is optional for a client that posts only the two
            // original fields; when it is sent it must agree.
            if ($repeat !== '' && !hash_equals($password, $repeat)) {
                $this->session->set_flashdata('resultRegister', 'The two passwords did not match. Nothing was created.');
                redirect(lang_url('registration'));
            }

            $takenEmail = $this->PublicModel->checkUserFreeEmail($_POST['email']);
            if($takenEmail !== true) {
                $this->session->set_flashdata('resultRegister', $takenEmail);
                redirect(lang_url('registration'));
            }

            $this->mark('register: email free, inserting');
            $result = $this->PublicModel->registerUser($_POST);
            $this->mark('register: insert finished', array('result' => var_export($result, true)));
            if ($result === true) {
                // $returned_email = $this->registerUser();
                // $this->setUserLogin($returned_email, 1);
                redirect(lang_url('login'));
            } else {
                $this->session->set_flashdata('resultRegister', $result);
                redirect(lang_url('registration'));
            }
        }
        $this->load->library('AdominIndustry');
        $data['industries'] = $this->adominindustry->options();
        $data['industryDefault'] = $this->adominindustry->fallback();
        $head['title'] = lang('title_register');
        $head['description'] = lang('description_register');
        $this->render('registration/index', $head, $data);
    }

    public function login()
    {
        $data = array();
        $head = array();
        if (isset($_POST['email'])) {
            $this->mark('login: POST accepted (CSRF passed)', array(
                'email' => isset($_POST['email']) ? $_POST['email'] : '',
                'has_password' => isset($_POST['password']) && $_POST['password'] !== '',
            ));
            $this->loginCheck();
            $this->mark('login: loginCheck returned (no redirect)');
        }
        $head['title'] = lang('title_login');
        $head['description'] = lang('description_login');
        $this->render('registration/login', $head, $data);
    }

    /**
     * "Which account did you mean?" - shown when one e-mail and password match
     * two accounts, a business owner and an employee of it.
     *
     * The route for this page has pointed here since it was written, and the
     * redirect below has always sent people to it, but the method was missing:
     * signing in with an address that has two accounts landed on a 404 with
     * the POST already consumed - the one path a user cannot recover from,
     * because they cannot even get back to the form.
     *
     * The POST is kept in the session and replayed here, so choosing a type
     * completes the sign-in the user already started. Without a saved POST
     * there is nothing to continue, so it sends them to the form rather than
     * showing buttons that cannot work.
     */
    public function choosetype()
    {
        $saved = isset($_SESSION['savedPost']) ? $_SESSION['savedPost'] : NULL;
        if (!is_array($saved) || empty($saved['email'])) {
            redirect(lang_url('login'));
        }

        $type = (int) $this->input->get('type');
        if ($type === 1 || $type === 2) {
            $this->mark('login: two accounts, chosen type', array('type' => $type));
            unset($_SESSION['savedPost']);
            $this->setUserLogin($saved['email'], $type);
            return;
        }

        $head = array();
        $head['title'] = lang('choose_type_of_login') . ' · ' . adomin_conf('brand', 'name');
        $this->load->view('registration/choosetype', array('head' => $head));
    }

    /**
     * "Forgot your password?"
     *
     * There is no self-service reset: it needs a token column on users, a
     * migration to add it, and working outbound mail - and mail is not
     * configured on every deployment. Rather than 404 the link that every
     * sign-in page shows, this says what to do instead, which is the honest
     * answer and the one a locked-out person can act on.
     */
    public function forgotten()
    {
        $head = array();
        $head['title'] = 'Password · ' . adomin_conf('brand', 'name');
        $this->load->view('registration/forgotten', array('head' => $head));
    }

    private function loginCheck()
    {
        $result = $this->PublicModel->loginCheck($_POST);
        $this->mark('login: credentials checked', array('result' => var_export($result, true)));

        if ($result !== false) {
            if ($result == 3) {
                $this->mark('login: two accounts, choosing type');
                $_SESSION['savedPost'] = $_POST;
                redirect(lang_url('choose-type-of-login'));
            } else {
                $this->mark('login: resolving user info', array('type' => $result));
                $this->setUserLogin($_POST['email'], $result);
            }
        } else {
            $this->mark('login: rejected, setting flashdata');
            $this->session->set_flashdata('email', $_POST['email']);
            $this->session->set_flashdata('loginErrors', lang('usr_or_pass_invalid'));
            $this->mark('login: redirecting back to the form');
            redirect(lang_url('login'));
        }

        $this->mark('login: end of loginCheck');
    }
}
