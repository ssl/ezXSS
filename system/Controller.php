<?php

class Controller
{
    /**
     * Model
     * 
     * @var mixed
     */
    public $model;

    /**
     * Session
     * 
     * @var object
     */
    public $session;
    
    
    /**
     * View
     * 
     * @var object
     */
    public $view;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->session = new Session();
        $this->view = new View();

        // Check if kill switcher is turned on
        $this->checkKillSwitch();

        // Check if platform is installed
        $this->checkIfInstalled();

        // Check for updates
        $this->checkForUpdates();

        // Set timezone
        try {
            date_default_timezone_set($this->model('Setting')->get('timezone'));
        } catch (Exception $e) {
            date_default_timezone_set('Europe/Amsterdam');
        }
    }

    /**
     * Load model
     * 
     * @param string $name The model name
     * @return class
     */
    public function model($name)
    {
        // Check if model has already been set
        if (!isset($this->model[$name])) {
            $file = __DIR__ . "/../app/models/$name.php";
            if (file_exists($file)) {
                // Load model
                require $file;
                $modelName = $name . '_model';
                $this->model[$name] = new $modelName();
            }
        }

        return $this->model[$name];
    }

    /**
     * Shows the page content
     * 
     * @return string
     */
    public function showContent()
    {
        // Check if a theme is set
        try {
            $theme = $this->model('Setting')->get('theme');
        } catch (Exception $e) {
            $theme = 'classic';
        }

        // Get content and add correct theme stylsheet
        $content = $this->view->showContent();
        $content = str_replace('{theme}', '<link rel="stylesheet" href="/assets/css/' . e($theme) . '.css">', $content);
        return $content;
    }

    /**
     * Validate if the posted csrf token is valid
     * 
     * @throws Exception
     * @return void
     */
    public function validateCsrfToken()
    {
        $csrf = $this->getPostValue('csrf');

        if (!$this->session->isValidCsrfToken($csrf)) {
            if (!httpmode && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === "")) {
                throw new Exception("ezXSS does not work without SSL");
            }
            throw new Exception("Invalid CSRF token");
        }
    }

    /**
     * Validate session if user still needs to be logged in
     * 
     * @throws Exception
     * @return void
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

                // Check if the username has been changed
                if ($this->session->data('username') != $account['username']) {
                    throw new Exception("Username has been changed");
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
     * @param string $param The param
     * @return string|null
     */
    public function getPostValue($param)
    {
        return isset($_POST[$param]) && is_string($_POST[$param]) ? $_POST[$param] : null;
    }

    /**
     * Returns get value
     *
     * @param string $param The param
     * @return string|null
     */
    public function getGetValue($param)
    {
        return isset($_GET[$param]) ? $_GET[$param] : null;
    }

    /**
     * Checks if platform is in kill switch mode
     *
     * @return void
     */
    private function checkKillSwitch()
    {
        try {
            $killswitch = $this->model('Setting')->get('killswitch');

            if (!empty($killswitch)) {
                if ($this->getGetValue('pass') === $killswitch) {
                    $this->model('Setting')->set('killswitch', '');
                    header('Location: /');
                } else {
                    http_response_code(404);
                    exit();
                }
            }
        } catch (Exception $e) {
        }
    }

    /**
     * Checks if platform if installed
     * 
     * @return void|bool
     */
    private function checkIfInstalled()
    {
        try {
            if(path !== '/manage/install') {
                // Fetch current version will throw exception if no database exists
                $this->model('Setting')->get('version');
            }
        } catch (Exception $e) {
            header('Location: /manage/install');
            exit();
        }
    }

    /**
     * Checks if platform needs updates
     * 
     * @return void
     */
    private function checkForUpdates()
    {
        try {
            if(path !== '/manage/update' && path !== '/manage/install') {
                $version = $this->model('Setting')->get('version');
                if($version !== version) {
                    throw new Exception('ezXSS is not up-to-date');
                }
            }
        } catch (Exception $e) {
            header('Location: /manage/update');
            exit();
        }
    }
}
