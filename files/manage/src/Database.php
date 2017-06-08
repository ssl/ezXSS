<?php

  class Database {

    //> Setup your database settings and put isSet on true
    public  $isSet = false;
    private $databaseHost = '127.0.0.1';
    private $databaseUser = '';
    private $databasePassword = '';
    private $databaseName = '';
    private $DB;

    public function __construct() {
      $this->DB = new PDO('mysql:host='. $this->databaseHost .';dbname='. $this->databaseName, $this->databaseUser, $this->databasePassword);
    }

    public function query($query) {
      return $this->DB->query($query);
    }

    public function fetch($query, $array = []) {
      $fetch = $this->DB->prepare($query);
      $fetch->execute($array);
      return $fetch->fetch();
    }

    public function lastInsertId($query, $array = []) {
      $lastInsertId = $this->DB->prepare($query);
      $lastInsertId->execute($array);
      return $this->DB->lastInsertId();
    }

    public function fetchAll($query, $array = []) {
      $fetchAll = $this->DB->prepare($query);
      $fetchAll->execute($array);
      return $fetchAll->fetchAll();
    }

    public function rowCount($query, $array = []) {
      $rowCount = $this->DB->prepare($query);
      $rowCount->execute($array);
      return $rowCount->rowCount();
    }


  }
