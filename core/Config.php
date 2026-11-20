<?php
class Config
{
    private static $config = array();
    private static $loaded = false;

    /**
     * Load configuration from the .env file.
     */
    public static function load()
    {
        if (self::$loaded) {
            return;
        }

        self::$config = array(
            'VERSION'     => '5.0',
            'DEBUG'       => false,
            'DOMAIN'      => 'localhost',
            'DB_HOST'     => 'localhost',
            'DB_NAME'     => 'ezxss',
            'DB_USER'     => 'root',
            'DB_PASS'     => '',
            'DB_PORT'     => 3306,
            'DB_CHARSET'  => 'utf8mb4',
            'IP_LOCK'     => false,
            'ALLOWED_IPS' => array(),
            'TIMEZONE'    => 'UTC',
            'SESSION_TTL' => 604800,

            'TRUST_PROXY'   => false,
            'PANEL_DOMAINS' => '',
        );

        $envFile = FRAMEWORK_ROOT . '/.env';
        if (file_exists($envFile)) {
            self::parseEnvContent(file_get_contents($envFile));
        }

        self::legacyDbFallback();

        date_default_timezone_set(self::$config['TIMEZONE']);

        self::$loaded = true;
    }

    /**
     * Parse .env content and merge it into the config.
     *
     * @param string $content
     */
    private static function parseEnvContent($content)
    {
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }

            if ($value === 'true') {
                $value = true;
            } elseif ($value === 'false') {
                $value = false;
            } elseif ($key === 'ALLOWED_IPS') {
                $value = array_values(array_filter(array_map('trim', explode(',', $value))));
            } elseif (is_numeric($value) && !preg_match('/(_PASS|PASSWORD|_SECRET|_KEY|_TOKEN|_HASH|^VERSION$)/i', $key)) {

                $value = (strpos($value, '.') !== false) ? (float) $value : (int) $value;
            }

            self::$config[$key] = $value;
        }
    }

    /**
     * Get a configuration value.
     *
     * @param string $key
     * @param string $default
     * @param bool $throw
     * @throws Exception
     */
    public static function get($key, $default = null, $throw = false)
    {
        if ($throw && !isset(self::$config[$key])) {
            throw new Exception("Config key '$key' not found");
        }
        return isset(self::$config[$key]) ? self::$config[$key] : $default;
    }

    /**
     * Set a configuration value at runtime.
     *
     * @param string $key
     * @param string $value
     */
    public static function set($key, $value)
    {
        self::$config[$key] = $value;
    }

    /**
     * Fall back to 4.x-style camelCase DB env vars when the 5.0 keys are absent.
     */
    private static function legacyDbFallback()
    {
        $map = array(
            'DB_HOST' => 'dbHost',
            'DB_USER' => 'dbUser',
            'DB_PASS' => 'dbPassword',
            'DB_NAME' => 'dbName',
            'DB_PORT' => 'dbPort',
        );

        $defaults = array(
            'DB_HOST' => 'localhost',
            'DB_NAME' => 'ezxss',
            'DB_USER' => 'root',
            'DB_PASS' => '',
            'DB_PORT' => 3306,
        );

        foreach ($map as $new => $old) {
            if (self::$config[$new] === $defaults[$new] && isset(self::$config[$old])) {
                self::$config[$new] = self::$config[$old];
            }
        }
    }

    /**
     * Reset loaded state (used by the test runner).
     */
    public static function reset()
    {
        self::$config = array();
        self::$loaded = false;
    }

    /**
     * Parse a specific .env file into the config (used for testing/tools).
     *
     * @param string $path
     */
    public static function loadFile($path)
    {
        if (file_exists($path)) {
            self::parseEnvContent(file_get_contents($path));
        }
    }
}
