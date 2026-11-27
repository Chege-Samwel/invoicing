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

            if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                $this->session->set_flashdata('resultRegister', 'That email address does not look right. Check it and try again.');
                redirect(lang_url('registration'));
            }

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
