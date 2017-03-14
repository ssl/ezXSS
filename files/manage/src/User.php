<?php

  class User {

    public function __construct() {
      $this->database = new Database();

      session_set_cookie_params(6000000, "/", null, true, true); 
      if(!isset($_SESSION)) session_start();

    }

    public function isLoggedIn() {
      return (isset($_SESSION["login"])) ? $_SESSION["login"] : false;
    }

    public function sessionInfo($key) {
      return $_SESSION[$key];
    }

    public function createSession() {
      //> Get each column and value from user
      $username = $this->database->newQueryArray("SELECT * FROM settings WHERE setting='username' LIMIT 1");
      $password = $this->database->newQueryArray("SELECT * FROM settings WHERE setting='password' LIMIT 1");
      $email = $this->database->newQueryArray("SELECT * FROM settings WHERE setting='email' LIMIT 1");

      //> Add this information to session
      $_SESSION["login"] = true;
      $_SESSION["username"] = $username["value"];
      $_SESSION["password"] = $password["value"];
      $_SESSION["email"] = $email["value"];

    }

    public function getCsrf() {
      //> Get the CSRF code or create a new one
      return (!isset($_SESSION["csrfToken"])) ? $_SESSION["csrfToken"] = bin2hex(openssl_random_pseudo_bytes(32)) : $_SESSION["csrfToken"];
    }

    public function updatePassword($current, $new, $new2) {
      //> Make sure all fields are correct
      if(!$this->isLoggedIn()) return array("echo" => "You need to be logged in!");
      if (!password_verify($current, $this->sessionInfo("password"))) return array("echo" => "Current password is not correct.");
      if($new != $new2) return array("echo" => "The retyped password is not the same as the new password.");
      if(strlen($new) < 8) return array("echo" => "The new password needs to be atleast 8 characters long.");

      //> Update settings in database, refresh session and return
      $this->database->newQueryArray("UPDATE settings SET value = :password WHERE setting='password'", array(":password" => password_hash($new, PASSWORD_BCRYPT, ['cost' => 11])));
      $this->createSession();
      return array("echo" => "Your new password is saved!");
    }

    public function updateMain($email) {
      //> Make sure all fields are correct
      if(!$this->isLoggedIn()) return array("echo" => "You need to be logged in!");
      if(!filter_var($email, FILTER_VALIDATE_EMAIL)) return array("echo" => "This is not a correct e-mailadres.");

      //> Update settings in database, refresh session and return
      $this->database->newQueryArray("UPDATE settings SET value = :email WHERE setting='email'", array(":email" => $email));
      $this->createSession();
      return array("echo" => "Your new settings are saved!");
    }

    public function login($username, $password) {
      //> Query to get user information
      $usernameCheck = $this->database->newQueryArray("SELECT * FROM settings WHERE setting='username' AND value = :username LIMIT 1", array(":username" => $username));
      $passwordCheck = $this->database->newQueryArray("SELECT * FROM settings WHERE setting='password' LIMIT 1", array(":password" => $password));

      //> Check if account is valid
      if(!isset($usernameCheck["id"])) return array("echo" => "This combination of username and password is not found.");
      if (!password_verify($password, $passwordCheck["value"])) return array("echo" => "This combination of username and password is not found. {2}");

      $this->createSession();
      return array("redirect" => "dashboard");
    }
  }
