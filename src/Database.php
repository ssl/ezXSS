<?php

  class Database {

    private $databaseHost = getenv('DATABASE_HOST');
    private $databaseUser = getenv('DATABASE_USERT');
    private $databasePassword = getenv('DATABASE_PASSWORD');
    private $databaseName = getenv('DATABASE_NAME');
    private $DB;

    /**
  	* Try to connect to database
  	* @method __construct
  	*/
    public function __construct() {
      try {
        $this->DB = new PDO('mysql:host='. $this->databaseHost .';dbname='. $this->databaseName, $this->databaseUser, $this->databasePassword);
      } catch(PDOException $e) {
        if(debug == true) {
          print $e->getMessage();
        }
      }
    }

    /**
  	* Send a basic SQL query
  	* @method query
  	* @param string $query     SQL query
    * @return array            result of query
  	*/
    public function query($query) {
      return $this->DB->query($query);
    }

    /**
    * Fetch one row with query
    * @method fetch
    * @param string $query     SQL query
    * @param string $array     Array with bind values
    * @return array            result of query
    */
    public function fetch($query, $array = []) {
      $fetch = $this->DB->prepare($query);
      $fetch->execute($array);
      return $fetch->fetch();
    }

    /**
    * Return last id from query
    * @method lastInsertId
    * @param string $query     SQL query
    * @param string $array     Array with bind values
    * @return array            result of query
    */
    public function lastInsertId($query, $array = []) {
      $lastInsertId = $this->DB->prepare($query);
      $lastInsertId->execute($array);
      return $this->DB->lastInsertId();
    }

    /**
    * Fetch all rows with query
    * @method fetchAll
    * @param string $query     SQL query
    * @param string $array     Array with bind values
    * @return array            result of query
    */
    public function fetchAll($query, $array = []) {
      $this->DB->setAttribute( PDO::ATTR_EMULATE_PREPARES, false );
      $fetchAll = $this->DB->prepare($query);
      $fetchAll->execute($array);
      return $fetchAll->fetchAll();
    }

    /**
    * Return row count of query
    * @method rowCount
    * @param string $query     SQL query
    * @param string $array     Array with bind values
    * @return array            result of query
    */
    public function rowCount($query, $array = []) {
      $rowCount = $this->DB->prepare($query);
      $rowCount->execute($array);
      return $rowCount->rowCount();
    }

    /**
    * Return value of setting
    * @method fetchSetting
    * @param string $setting   Setting name
    * @return string           Setting value
    */
    public function fetchSetting($setting) {
      $query = $this->fetch('SELECT value FROM settings WHERE setting = :setting LIMIT 1', [':setting' => $setting]);
      return $query[0];
    }

  }
