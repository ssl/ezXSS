<?php

  class Request {

    public function __construct() {
      $this->user = new User();
    }

    public function json() {
      //> Make sure the action is valid
      $action = $this->post("action");
      if(!in_array($action, array("login", "main-settings", "pwd-settings"))) {
        return $this->toJson(array("echo" => "Could not found this action, what did you do?"));
      }

      if($this->user->sessionInfo("csrfToken") != $this->post("csrf")) return $this->toJson(array("echo" => "CSRF token is not valid"));

      //> Call the correct function based on the action
      switch($action) {
        case "login" : return $this->toJson($this->user->login($this->post("username"), $this->post("password"))) ; break;
        case "main-settings" : return $this->toJson($this->user->updateMain($this->post("email"))); break;
        case "pwd-settings" : return $this->toJson($this->user->updatePassword($this->post("password"), $this->post("newpassword"), $this->post("newpassword2"))); break;
        default : return $this->toJson(array("echo" => "Could not found this action, what did you do?")); break;
      }

    }

    private function post($key) {
      return $_POST[$key];
    }

    private function toJson($array) {
      //> Return json
      $array["echo"] = (isset($array["echo"])) ? $array["echo"] : false;
      $array["redirect"] = (isset($array["redirect"])) ? $array["redirect"] : false;
      return json_encode($array);
    }
  }
