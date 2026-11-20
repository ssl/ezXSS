<?php
class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        try {
            $host    = Config::get('DB_HOST', 'localhost');
            $dbname  = Config::get('DB_NAME', 'ezxss');
            $user    = Config::get('DB_USER', 'root');
            $pass    = Config::get('DB_PASS', '');
            $port    = Config::get('DB_PORT', 3306);
            $charset = Config::get('DB_CHARSET', 'utf8mb4');

            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";

            $options = array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $charset",
            );

            $this->connection = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            if (Config::get('DEBUG', false)) {
                throw new Exception('Database connection failed: ' . $e->getMessage());
            }
            throw new Exception('Database connection failed');
        }
    }

    /**
     * Get the shared PDO connection.
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->connection;
    }

    private function __clone() {}

    public function __wakeup()
    {
        throw new Exception('Cannot unserialize singleton');
    }
}
