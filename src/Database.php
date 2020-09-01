<?php

class Database
{
    private $DB;

    /**
     * Try to connect to database
     * @method __construct
     */
    public function __construct()
    {
        try {
            $this->DB = new PDO(
                'mysql:host=' . config['dbHost'] . ';dbname=' . config['dbName'],
                config['dbUser'],
                config['dbPassword']
            );
        } catch (PDOException $e) {
            if (debug === true) {
                print $e->getMessage();
            }
            error('Database connection failed. Check your config file.', true);
        }
    }

    /**
     * Send a basic SQL query
     * @method query
     * @param string $query SQL query
     * @return false|PDOStatement
     */
    public function query($query)
    {
        return $this->DB->query($query);
    }

    /**
     * Return last id from query
     * @method lastInsertId
     * @param string $query SQL query
     * @param array $array Array with bind values
     * @return string result of query
     */
    public function lastInsertId($query, $array = [])
    {
        $lastInsertId = $this->DB->prepare($query);
        $lastInsertId->execute($array);
        return $this->DB->lastInsertId();
    }

    /**
     * Fetch all rows with query
     * @method fetchAll
     * @param string $query SQL query
     * @param array $array Array with bind values
     * @return array            result of query
     */
    public function fetchAll($query, $array = [])
    {
        $this->DB->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $fetchAll = $this->DB->prepare($query);
        $fetchAll->execute($array);
        return $fetchAll->fetchAll();
    }

    /**
     * Return row count of query
     * @method rowCount
     * @param string $query SQL query
     * @param array $array Array with bind values
     * @return int result of query
     */
    public function rowCount($query, $array = [])
    {
        $rowCount = $this->DB->prepare($query);
        $rowCount->execute($array);
        return $rowCount->rowCount();
    }

    /**
     * Return value of setting
     * @method fetchSetting
     * @param string $setting Setting name
     * @return string Setting value
     */
    public function fetchSetting($setting)
    {
        $query = $this->fetch('SELECT value FROM settings WHERE setting = :setting LIMIT 1', [':setting' => $setting]);
        return $query[0];
    }

    /**
     * Fetch one row with query
     * @method fetch
     * @param string $query SQL query
     * @param array $array Array with bind values
     * @return array result of query
     */
    public function fetch($query, $array = [])
    {
        $fetch = $this->DB->prepare($query);
        $fetch->execute($array);
        return $fetch->fetch();
    }

}
