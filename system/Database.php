<?php

class Database
{

    /**
     * @var null
     */
    private $connection = null;

    /**
     * @var null
     */
    private static $database = null;

    /**
     * Starts new PDO database connection
     */
    private function __construct()
    {
        if ($this->connection === null) {
            // Construct a new connection
            $this->connection = new PDO('mysql:dbname=' . DB_NAME . ';host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8', DB_USER, DB_PASS);

            $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
    }

    /**
     * Opens connection
     *
     * @return class
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
     * @param string $table
     * @param int $id
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
     * @param string $table
     * @param string $id
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
     * @param string $table
     * @param string $username
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
     * @param string $table
     * @return void
     */
    public function getAll($table)
    {
        $this->statement = $this->connection->prepare('SELECT * FROM ' . $table);
        $this->execute();
    }

    /**
     * Prepares a query
     *
     * @param string $query
     * @return void
     */
    public function prepare($query)
    {
        $this->statement = $this->connection->prepare($query);
    }

    /**
     * Binds value to prepared query
     *
     * @param string $param
     * @param string $value
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
     * @return void
     */
    public function countRows()
    {
        return $this->statement->rowCount();
    }

    /**
     * Executes prepared query
     *
     * @return void
     */
    public function execute()
    {
        return $this->statement->execute();
    }

    /**
     * Returns all rows
     *
     * @return void
     */
    public function fetchAll()
    {
        return $this->statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Returns single row
     *
     * @return void
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
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }

    /**
     * Returns type by value
     *
     * @param mixed $value
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
