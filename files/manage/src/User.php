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

    public function updateFilters($save, $alert) {
      //> Make sure all fields are correct
      if(!$this->isLoggedIn()) return array("echo" => "You need to be logged in!");
      if($save != 0 && $save != 1 || $alert != 0 && $alert != 1) return array("echo" => "Value needs to be true or false (1 or 0)");

      $this->database->newQueryArray("UPDATE settings SET value = :save WHERE setting='filter-save'", array(":save" => $save));
      $this->database->newQueryArray("UPDATE settings SET value = :alert WHERE setting='filter-alert'", array(":alert" => $alert));
      return array("echo" => "Your new filter settings are saved!");
    }

    public function shareReport($id, $domain, $secretKey) {
      //> Make sure all fields are correct
      if(!$this->isLoggedIn()) return array("echo" => "You need to be logged in!");

      $report = $this->database->newQueryArray("SELECT * FROM reports WHERE id = :id LIMIT 1", array(":id" => $id));

      if(!isset($report["id"])) return array("echo" => "The report ID is not found.");
      $report["screenshot"] = base64_encode(file_get_contents("https://" . $_SERVER['SERVER_NAME'] . "/manage/assets/images/reports/" . $report["screenshot"] . ".png"));
      $report["referrer"] = !empty($report["referer"]) ? "Shared via " . $_SERVER['SERVER_NAME'] . " - ". $report["referer"] : "Shared via " . $_SERVER['SERVER_NAME'];
      $report["shared"] = $secretKey;

      $ch = curl_init(urlencode($domain) . "/Callback");
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($report));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

      $result = curl_exec($ch);

      if($result == "github.com/ssl/ezXSS0") return array("echo" => "Wrong secret key, unable to share it.");
      if($result != "github.com/ssl/ezXSS") return array("echo" => "Unable to find a valid ezXSS installation. Please check the domain.");

      return array("echo" => "Successfully shared the report.");

    }

    public function generateKey() {
      //> Make sure all fields are correct
      if(!$this->isLoggedIn()) return array("echo" => "You need to be logged in!");

      $key = substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil(30/strlen($x)))), 1, 30);
      $this->database->newQueryArray("UPDATE settings SET value = :key WHERE setting='secretkey'", array(":key" => $key));
      return array("echo" => "Your new key is generated and saved!");
    }

    public function updatePassword($current, $new, $new2) {
      //> Make sure all fields are correct
      if(!$this->isLoggedIn()) return array("echo" => "You need to be logged in!");
      if (!password_verify($current, $this->sessionInfo("password"))) return array("echo" => "Current password is not correct.");
      if($new != $new2) return array("echo" => "The retypted password is not the same as the new password.");
      if(strlen($new) < 8) return array("echo" => "The new password needs to be atleast 8 characters long.");

      //> Update settings in database, refresh session and return
      $this->database->newQueryArray("UPDATE settings SET value = :password WHERE setting='password'", array(":password" => password_hash($new, PASSWORD_BCRYPT, ['cost' => 11])));
      $this->createSession();
      return array("echo" => "Your new password is saved!");
    }

    public function updateMain($email, $dompart, $timezone) {
      //> Make sure all fields are correct
      if(!$this->isLoggedIn()) return array("echo" => "You need to be logged in!");
      if(!filter_var($email, FILTER_VALIDATE_EMAIL)) return array("echo" => "This is not a correct e-mailadres.");
      if(!is_int((int)$dompart)) return array("echo" => "The dom lenght needs to be a int number.");
      if(!in_array($timezone, timezone_identifiers_list())) return array("echo" => "The timezone is not a valid timezone.");

      //> Update settings in database, refresh session and return
      $this->database->newQueryArray("UPDATE settings SET value = :email WHERE setting='email'", array(":email" => $email));
      $this->database->newQueryArray("UPDATE settings SET value = :dompart WHERE setting='dompart'", array(":dompart" => (int)$dompart));
      $this->database->newQueryArray("UPDATE settings SET value = :timezone WHERE setting='timezone'", array(":timezone" => $timezone));
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
