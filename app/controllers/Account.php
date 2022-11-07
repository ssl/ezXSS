<?php

class Account extends Controller
{

    /**
     * Account index.
     *
     * @return string
     */
    public function index()
    {
        $this->isLoggedInOrExit();
        $this->view->setTitle('Account');
        $this->view->renderTemplate('account/index');
        return $this->view->showContent();
    }

    /**
     * Login page
     *
     * @return string
     */
    public function login()
    {
        $this->isLoggedOutOrExit();
        $this->view->setTitle('Login');
        $this->view->renderTemplate('account/login');

        if ($this->isPOST()) {
            try {
                $this->validateCsrfToken();

                $username = $this->getPostValue('username');
                $password = $this->getPostValue('password');

                $account = $this->model('User')->login($username, $password);
                $this->session->createSession($account);
                header('Location: dashboard/index');
            } catch (Exception $e) {
                $this->view->renderMessage($e->getMessage());
            }
        }

        return $this->view->showContent();
    }
}
