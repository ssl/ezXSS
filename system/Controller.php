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
     * @return mixed|null The loaded model or null if not found
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
            } else {
                return null;
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
        $content = $theme != 'classic' ? str_replace('{theme}', '<link rel="stylesheet" href="/assets/css/' . e($theme) . '.css?v='.version.'">', $content) : str_replace('{theme}', '', $content);
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
            if (!httpmode && !(isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1') || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
                throw new Exception('ezXSS does not work without SSL');
            }
            throw new Exception('Invalid CSRF token');
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
                    throw new Exception('Password has been changed');
                }

                // Check if the username has been changed
                if ($this->session->data('username') != $account['username']) {
                    throw new Exception('Username has been changed');
                }

                // Check if the rank has been changed
                if ($this->session->data('rank') != $account['rank']) {
                    throw new Exception('Rank has been changed');
                }

                // Log if the user ip has been changed
                if ($this->session->data('ip') != userip) {
                    $this->log('New IP address in session');
                    $this->session->set('ip', userip);
                }
            }
        } catch (Exception $e) {
            // If session failed to validate, clear the session
            $this->session->deleteSession();
            redirect('/manage/account/login');
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
            if (preg_match('/^\/manage\/([a-z0-9\/?&=]+)$/i', $_SERVER['REQUEST_URI'])) {
                $this->session->set('redirect', $_SERVER['REQUEST_URI']);
            }
            redirect('/manage/account/login');
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
            redirect('/manage/dashboard/index');
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
            redirect('/manage/dashboard/my');
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
     * Parses user agent and returns string with browser and OS
     * 
     * @param string $userAgent The user agent string
     * @return string
     */
    public function parseUserAgent($userAgent)
    {
        $browser = 'Unknown';
        $os = 'Unknown';

        if ($userAgent === 'Not collected') {
            return $userAgent;
        }

        $browsers = [
            '/MSIE/i' => 'IE',
            '/Trident/i' => 'IE',
            '/Edge/i' => 'Edge',
            '/Edg/i' => 'Edge',
            '/Firefox/i' => 'Firefox',
            '/OPR/i' => 'Opera',
            '/Chrome/i' => 'Chrome',
            '/Opera/i' => 'Opera',
            '/UCBrowser/i' => 'UC Browser',
            '/SamsungBrowser/i' => 'SamsungBrowser',
            '/YaBrowser/i' => 'Yandex',
            '/Vivaldi/i' => 'Vivaldi',
            '/Brave/i' => 'Brave',
            '/Safari/i' => 'Safari',
            '/PlayStation/i' => 'PlayStation'
        ];

        $oses = [
            '/Googlebot/i' => 'Googlebot',
            '/Windows/i' => 'Windows',
            '/iPhone/i' => 'iPhone',
            '/Mac/i' => 'macOS',
            '/Linux/i' => 'Linux',
            '/Unix/i' => 'Unix',
            '/Android/i' => 'Android',
            '/iOS/i' => 'iOS',
            '/BlackBerry/i' => 'BlackBerry',
            '/FirefoxOS/i' => 'Firefox OS',
            '/Windows Phone/i' => 'Windows Phone',
            '/CrOS/i' => 'ChromeOS',
            '/YandexBot/i' => 'YandexBot',
            '/PlayStation/i' => 'PlayStation',
        ];

        // Get the browser
        foreach ($browsers as $regex => $name) {
            if (preg_match($regex, $userAgent)) {
                $browser = $name;
                break;
            }
        }

        // Get the operating system
        foreach ($oses as $regex => $name) {
            if (preg_match($regex, $userAgent)) {
                $os = $name;
                break;
            }
        }

        $browser = $os === 'Unknown' && $browser === 'Unknown' ? 'Unknown' : "{$os} with {$browser}";

        return $browser;
    }

    /**
     * Parses timestamp and returns string with last x
     * 
     * @param string $timestamp The timestamp
     * @param string $syntax Syntax type
     * @return string
     */
    public function parseTimestamp($timestamp, $syntax = 'short')
    {
        if ($timestamp === 0) {
            return 'never';
        }

        $elapsed = time() - $timestamp;

        if ($elapsed < 60) {
            $text = $elapsed == 1 ? 'second' : 'seconds';
            return ($syntax == 'short') ? $elapsed . ' sec' : $elapsed . " {$text} ago";
        } elseif ($elapsed < 3600) {
            $minutes = floor($elapsed / 60);
            $text = $minutes == 1 ? 'minute' : 'minutes';
            return ($syntax == 'short') ? $minutes . ' min' : $minutes . " {$text} ago";
        } elseif ($elapsed < 86400) {
            $hours = floor($elapsed / 3600);
            $text = $hours == 1 ? 'hour' : 'hours';
            return ($syntax == 'short') ? $hours . ' hr' : $hours . " {$text} ago";
        } elseif ($elapsed < 2592000) {
            $days = floor($elapsed / 86400);
            $text = $days == 1 ? 'day' : 'days';
            return ($syntax == 'short') ? $days . ' ' . $text : $days . " {$text} ago";
        } else {
            $months = floor($elapsed / 2592000);
            $text = $months == 1 ? 'month' : 'months';
            return ($syntax == 'short') ? $months . ' mon' : $months . " {$text} ago";
        }
    }

    /**
     * Log item
     *
     * @param string $description The description
     * @return void
     */
    public function log($description)
    {
        if ($this->model('Setting')->get('logging') === '1') {
            $userId = $this->session->data('id');
            $this->model('Log')->add($userId !== '' ? $userId : 0, $description, userip);
        }
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
                    redirect('/');
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
            if (path !== '/manage/install') {
                // Fetch timezone will throw exception if no database exists
                $this->model('Setting')->get('timezone');
            }
        } catch (Exception $e) {
            redirect('/manage/install');
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
            if (explode('?', path)[0] !== '/manage/update' && path !== '/manage/install') {
                $version = $this->model('Setting')->get('version');
                if ($version !== version) {
                    throw new Exception('ezXSS is not up-to-date');
                }
            }
        } catch (Exception $e) {
            redirect('/manage/update');
        }
    }

    /**
     * Returns the payload list array
     * 
     * @return array
     */
    public function payloadList($type = 1)
    {
        $payloadList = [];

        if ($type === 1) {
            // '0' correspondents to 'all'
            array_push($payloadList, 0);
        } else {
            if ($this->isAdmin()) {
                // Add default/fallback payload to list for admins
                array_push($payloadList, 1);
            }
        }

        // Push all payloads of user to list
        $user = $this->model('User')->getById($this->session->data('id'));
        $payloads = $this->model('Payload')->getAllByUserId($user['id']);
        foreach ($payloads as $payload) {
            array_push($payloadList, $payload['id']);
        }

        return $payloadList;
    }
}
