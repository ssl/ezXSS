<?php

class Controller
{

    /**
     * @var [type]
     */
    public $model;

    /**
     * 
     */
    public function __construct()
    {
        $this->session = new Session();
        $this->view = new View();
    }

    /**
     * Load a model
     *
     * @param string $name
     * @return class
     */
    public function model($name)
    {
        // Check if model has already been set
        if (!is_object($this->model) || get_class($this->model) !== $name . '_model') {
            $file = __DIR__ . "/../app/models/$name.php";
            if (file_exists($file)) {
                // Load model
                require $file;
                $modelName = $name . '_model';
                $this->model = new $modelName();
            }
        }

        return $this->model;
    }

    /**
     * Validate if the posted csrf token is valid
     *
     * @return Exception|void
     */
    public function validateCsrfToken()
    {
        $csrf = $this->getPostValue('csrf');

        if (!$this->session->isValidCsrfToken($csrf)) {
            throw new Exception("Invalid CSRF token");
        }
    }

    /**
     * Validate session if user still needs to be logged in
     *
     * @return Exception|void
     */
    public function validateSession()
    {
        try {
            if ($this->session->isLoggedIn()) {
                // This tries getting the account by id, which fails if the account is deleted
                $account = $this->model('User')->getById($this->session->data('id'));

                // Check if the password has been changed
                if ($this->session->data('password_hash') != md5($account['password'])) {
                    throw new Exception("Password has been changed");
                }

                // Check if the rank has been changed
                if ($this->session->data('rank') != $account['rank']) {
                    throw new Exception("Rank has been changed");
                }
            }
        } catch (Exception $e) {
            // If session failed to validate, clear the session
            $this->session->deleteSession();
            header('Location: /manage/account/login');
            exit();
        }
    }

    /**
     * Redirect user if session is not logged in
     *
     * @return void
     */
    public function isLoggedInOrExit()
    {
        $this->validateSession();
        if (!$this->session->isLoggedIn()) {
            header('Location: /manage/account/login');
            exit();
        }
    }

    /**
     * Redirect user if session is logged in
     *
     * @return void
     */
    public function isLoggedOutOrExit()
    {
        if ($this->session->isLoggedIn()) {
            header('Location: /manage/dashboard/index');
            exit();
        }
    }

    /**
     * Redirect user if session is not admin
     *
     * @return void
     */
    public function isAdminOrExit()
    {
        $this->isLoggedInOrExit();
        if (!$this->isAdmin()) {
            header('Location: /manage/dashboard/my');
            exit();
        }
    }

    /**
     * Check if user is a admin
     *
     * @return boolean
     */
    public function isAdmin()
    {
        return $this->session->data('rank') == 7;
    }

    /**
     * Checks if request method is POST
     *
     * @return boolean
     */
    public function isPOST()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return true;
        }
        return false;
    }

    /**
     * Returns post value
     *
     * @param string $param
     * @return string|null
     */
    public function getPostValue($param)
    {
        return isset($_POST[$param]) ? $_POST[$param] : null;
    }
}
