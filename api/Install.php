<?php
class InstallController extends Controller
{
    /**
     * Routable HTTP actions (everything else is an internal helper).
     */
    public static $routable = array('index');

    /**
     * Default settings seeded on a fresh install (key => value).
     */
    private static $defaultSettings = array(
        'version'              => '5.0',
        'timezone'             => 'UTC',
        'dompart'              => '500',
        'killswitch'           => '',
        'customjs'             => '',
        'customjs2'            => '',
        'theme'                => 'classic',
        'callback-url'         => '',
        'filter-save'          => '1',
        'filter-alert'         => '1',
        'duplicate_columns'    => 'cookies,origin,referer,uri,useragent,dom,ip',
        'dom_threshold'        => '2097152',

        'callback_max_body_kb' => '8192',

        'log_logins'           => '0',
        'log_reports'          => '0',
        'log_payloads'         => '0',
        'log_users'            => '0',
        'log_settings'         => '0',
        'log_alerts'           => '0',
        'persistent'           => '1',
        'spider'               => '1',
        'storescreenshot'      => '0',
        'compress'             => '0',
        'extensions'           => '',
        'allow_customjs'       => '1',
        'allow_pages'          => '1',
        'allow_filters'        => '1',
        'allow_extensions'     => '1',
        'beacon_fallback'      => '1',

        'signup_enabled'       => '0',
        'signup_mode'          => 'public',
        'signup_code_hash'     => '',

        'smtp_enabled'         => '0',
        'smtp_host'            => '',
        'smtp_port'            => '587',
        'smtp_security'        => 'tls',
        'smtp_username'        => '',
        'smtp_password'        => '',
        'smtp_from'            => '',
        'alert-mail'           => '1',
        'alert-telegram'       => '1',
        'alert-callback'       => '1',
        'alert-slack'          => '1',
        'alert-discord'        => '1',
        'collect_uri'          => '1',
        'collect_ip'           => '1',
        'collect_referer'      => '1',
        'collect_useragent'    => '1',
        'collect_cookies'      => '1',
        'collect_localstorage' => '1',
        'collect_sessionstorage' => '1',
        'collect_dom'          => '1',
        'collect_screenshot'   => '0',
        'collect_csp'          => '1',
    );

    /**
     * The default settings map seeded on a fresh install.
     */
    public static function defaultSettings()
    {
        return self::$defaultSettings;
    }

    /**
     * Run the installer (HTTP entry point).
     *
     * @throws HttpException
     */
    public function index()
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

        if ($method === 'GET') {
            $installed = false;
            try {
                $installed = $this->isAlreadyInstalled();
            } catch (Throwable $e) {
                $installed = false;
            }
            return $this->respond(array('installed' => $installed));
        }

        if (!isPOST()) {
            throw new HttpException(405, 'Method not allowed. Use POST.');
        }

        if ($this->isAlreadyInstalled()) {
            throw new HttpException(409, 'ezXSS is already installed.');
        }

        $body = $this->input();
        $username = isset($body['username']) && is_string($body['username']) ? trim($body['username']) : '';
        $password = isset($body['password']) && is_string($body['password']) ? $body['password'] : '';

        try {
            self::validateUsername($username);
            self::validatePassword($password);
        } catch (Exception $e) {
            throw new HttpException(422, $e->getMessage());
        }

        try {
            $data = $this->runInstall($username, $password);
        } catch (Exception $e) {
            throw new HttpException(409, $e->getMessage());
        }

        return $this->respond($data, array(), 201);
    }

    /**
     * Perform the install: apply migrations, seed settings, create the admin.
     *
     * @param string $username
     * @param string $password
     * @throws Exception
     * @return array
     */
    public function runInstall($username, $password)
    {

        Migrator::run();

        if ($this->adminExists()) {
            throw new Exception('ezXSS is already installed.');
        }

        $this->seedDefaultSettings();
        $this->createAdmin($username, $password);

        return array(
            'installed' => true,
            'message'   => 'ezXSS installed. Log in to continue.',
        );
    }

    /**
     * Installed = migrations table exists AND an admin user exists.
     *
     * @return bool
     */
    public function isAlreadyInstalled()
    {
        $migrator = new Migrator();
        return $migrator->isInstalled() && $this->adminExists();
    }

    /**
     * Whether at least one admin user exists (table may not exist yet).
     *
     * @return bool
     */
    private function adminExists()
    {
        $db = Database::getInstance();
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM `users` WHERE `role` = 'admin'");
            $stmt->execute();
            return (int) $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {

            return false;
        }
    }

    public static function validateUsername($username)
    {
        if ($username === '') {
            throw new Exception('Username is required');
        }
        if (preg_match('/[^A-Za-z0-9]/', $username)) {
            throw new Exception('Invalid characters in the username. Use a-Z0-9');
        }
        if (strlen($username) < 2 || strlen($username) > 25) {
            throw new Exception('Username needs to be between 2-25 long');
        }
    }

    public static function validatePassword($password)
    {

        if (mb_strlen((string) $password) < 12) {
            throw new Exception('Password too short. Use at least 12 characters.');
        }
    }

    /**
     * Insert default settings (idempotent via INSERT IGNORE on the unique key).
     */
    private function seedDefaultSettings()
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT IGNORE INTO `settings` (`setting`, `value`) VALUES (?, ?)");
        foreach (self::$defaultSettings as $setting => $value) {
            $stmt->execute(array($setting, $value));
        }
    }

    /**
     * Create the first admin user (bcrypt hashed password).
     *
     * @param string $username
     * @param string $password
     */
    private function createAdmin($username, $password)
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "INSERT INTO `users` (`username`, `password`, `role`, `notepad`) VALUES (?, ?, 'admin', ?)"
        );
        $stmt->execute(array(
            $username,
            password_hash($password, PASSWORD_BCRYPT, array('cost' => 12)),
            'Welcome to ezXSS 5',
        ));
    }
}
