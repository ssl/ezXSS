<?php

  class Database {

    //> Setup your database settings and put isSet on true
    public  $isSet = false;
    private $databaseHost = "127.0.0.1";
    private $databaseUser = "";
    private $databasePassword = "";
    private $databaseName = "";
    private $DB;

    public function __construct() {
      if(!isset($this->DB)) {
        $this->DB = new PDO("mysql:host=". $this->databaseHost .";dbname=". $this->databaseName, $this->databaseUser, $this->databasePassword);
      }
    }

    public function newQuery($query) {
      return $this->DB->query($query);
    }

    public function newQueryArray($query, $array = array()) {
      $newQueryArray = $this->DB->prepare($query);
      $newQueryArray->execute($array);
      return $newQueryArray->fetch();
    }

    public function lastInsertId($query, $array) {
      $lastInsertId = $this->DB->prepare($query);
      $lastInsertId->execute($array);
      return $this->DB->lastInsertId();
    }

    public function allQueryArray($query, $array) {
      $newQueryArray = $this->DB->prepare($query);
      $newQueryArray->execute($array);
      return $newQueryArray->fetchAll();
    }

    public function rowCount($query, $array) {
      $rowCount = $this->DB->prepare($query);
      $rowCount->execute($array);
      return $rowCount->rowCount();
    }


  }
