<?php

class Database
{
    /**
     * Connection
     * 
     * @var mixed
     */
    private $connection = null;

    /**
     * Database
     * 
     * @var mixed
     */
    private static $database = null;

    /**
     * Statement
     * 
     * @var mixed
     */
    private $statement = null;

    /**
     * Starts new PDO database connection
     */
    private function __construct()
    {
        if ($this->connection === null) {
            // Construct a new connection
            $this->connection = new PDO('mysql:dbname=' . DB_NAME . ';host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS);

            $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
    }

    /**
     * Opens connection
     *
     * @return self
     */
    public static function openConnection()
    {
        if (self::$database === null) {
            self::$database = new Database();
        }
        return self::$database;
    }

    /**
     * Delete row by id
     *
     * @param string $table The table
     * @param int $id The id
     * @return void
     */
    public function deleteById($table, $id)
    {
        $this->statement = $this->connection->prepare('DELETE FROM ' . $table . ' WHERE id = :id');
        $this->bindValue(':id', $id);
        $this->execute();
    }

    /**
     * Get row by id
     *
     * @param string $table The table
     * @param string $id The id
     * @return void
     */
    public function getById($table, $id)
    {
        $this->statement = $this->connection->prepare('SELECT * FROM ' . $table . ' WHERE id = :id LIMIT 1');
        $this->bindValue(':id', $id);
        $this->execute();
    }

    /**
     * Get row by username
     *
     * @param string $table The table
     * @param string $username The username
     * @return void
     */
    public function getByUsername($table, $username)
    {
        $this->statement = $this->connection->prepare('SELECT * FROM ' . $table . ' WHERE username = :username');
        $this->bindValue(':username', $username);
        $this->execute();
    }

    /**
     * Get all rows of table
     *
     * @param string $table The table
     * @return object|null
     */
    public function getAll($table)
    {
        $this->statement = $this->connection->prepare('SELECT * FROM ' . $table);
        $this->execute();
    }

    /**
     * Prepares a query
     *
     * @param string $query The query
     * @return void
     */
    public function prepare($query)
    {
        $this->statement = $this->connection->prepare($query);
    }

    /**
     * Binds value to prepared query
     *
     * @param string $param The param
     * @param string $value The value
     * @return void
     */
    public function bindValue($param, $value)
    {
        $type = self::getPDOType($value);
        $this->statement->bindValue($param, $value, $type);
    }

    /**
     * Returns row count
     *
     * @return int
     */
    public function countRows()
    {
        return $this->statement->rowCount();
    }

    /**
     * Executes prepared query
     *
     * @return bool
     */
    public function execute()
    {
        return $this->statement->execute();
    }

    /**
     * Executes query
     *
     * @param string query The query
     * @return bool|int
     */
    public function exec($query)
    {
        return $this->connection->exec($query);
    }

    /**
     * Returns all rows
     *
     * @return array
     */
    public function fetchAll()
    {
        return $this->statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Returns single row
     *
     * @return mixed
     */
    public function fetch()
    {
        return $this->statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Returns last inserted id
     *
     * @return string
     */
    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Returns type by value
     *
     * @param mixed $value The value to be checked
     * @return mixed
     */
    private static function getPDOType($value)
    {
        switch ($value) {
            case is_int($value):
                return PDO::PARAM_INT;
            case is_bool($value):
                return PDO::PARAM_BOOL;
            case is_null($value):
                return PDO::PARAM_NULL;
            default:
                return PDO::PARAM_STR;
        }
    }
}
