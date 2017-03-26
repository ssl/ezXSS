<?php

  class User {

    public function __construct() {
      $this->database = new Database();

      session_set_cookie_params(6000000, '/', null, true, true);
      if(!isset($_SESSION)) session_start();

    }

    public function isLoggedIn() {
      return (isset($_SESSION['login'])) ? $_SESSION['login'] : false;
    }

    public function sessionInfo($key) {
      return $_SESSION[$key];
    }

    public function createSession() {
      //> Get each column and value from user
      $username = $this->database->newQueryArray('SELECT * FROM settings WHERE setting = "username" LIMIT 1');
      $email = $this->database->newQueryArray('SELECT * FROM settings WHERE setting = "email" LIMIT 1');

      //> Add this information to session
      $_SESSION['login'] = true;
      $_SESSION['username'] = $username['value'];
      $_SESSION['email'] = $email['value'];

    }

    public function getCsrf() {
      //> Get the CSRF code or create a new one
      return (!isset($_SESSION['csrfToken'])) ? $_SESSION['csrfToken'] = bin2hex(openssl_random_pseudo_bytes(32)) : $_SESSION['csrfToken'];
    }

    public function updateFilters($save, $alert) {
      //> Make sure all fields are correct
      if(!$this->isLoggedIn()) return ['echo' => 'You need to be logged in!'];
      if($save != 0 && $save != 1 || $alert != 0 && $alert != 1) return ['echo' => 'Value needs to be true or false (1 or 0)'];

      $this->database->newQueryArray('UPDATE settings SET value = :value WHERE setting = "filter-save"', [':value' => $save]);
      $this->database->newQueryArray('UPDATE settings SET value = :value WHERE setting = "filter-alert"', [':value' => $alert]);
      return ['echo' => 'Your new filter settings are saved!'];
    }

    public function shareReport($id, $domain, $secretKey) {
      //> Make sure all fields are correct
      if(!$this->isLoggedIn()) return ['echo' => 'You need to be logged in!'];

      $report = $this->database->newQueryArray('SELECT * FROM reports WHERE id = :id LIMIT 1', [':id' => $id]);

      if(!isset($report['id'])) return ['echo' => 'The report ID is not found.'];
      $report['screenshot'] = base64_encode(file_get_contents('https://' . $_SERVER['SERVER_NAME'] . '/manage/assets/images/reports/' . $report['screenshot'] . '.png'));
      $report['referrer'] = !empty($report['referer']) ? 'Shared via ' . $_SERVER['SERVER_NAME'] . ' - '. $report['referer'] : 'Shared via ' . $_SERVER['SERVER_NAME'];
      $report['shared'] = $secretKey;

      $ch = curl_init(urlencode($domain) . '/Callback');
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($report));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

      $result = curl_exec($ch);

      if($result == 'github.com/ssl/ezXSS0') return ['echo' => 'Wrong secret key, unable to share it.'];
      if($result != 'github.com/ssl/ezXSS') return ['echo' => 'Unable to find a valid ezXSS installation. Please check the domain.'];

      return ['echo' => 'Successfully shared the report.'];

    }

    public function generateKey() {
      //> Make sure all fields are correct
      if(!$this->isLoggedIn()) return ['echo' => 'You need to be logged in!'];

      $key = substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil(30/strlen($x)))), 1, 30);
      $this->database->newQueryArray('UPDATE settings SET value = :value WHERE setting = "secretkey"', [':value' => $key]);
      return ['echo' => 'Your new key is generated and saved!'];
    }

    public function updatePassword($current, $new, $new2) {
      //> Make sure account is logged in
      if(!$this->isLoggedIn()) return ['echo' => 'You need to be logged in!'];

      //> Make sure all fields are correct
      $password = $this->database->newQueryArray('SELECT * FROM settings WHERE setting = "password" LIMIT 1');
      if (!password_verify($current, $password['value'])) return ['echo' => 'Current password is not correct.'];
      if($new != $new2) return ['echo' => 'The retypted password is not the same as the new password.'];
      if(strlen($new) < 8) return ['echo' => 'The new password needs to be atleast 8 characters long.'];

      //> Update settings in database, refresh session and return
      $this->database->newQueryArray('UPDATE settings SET value = :value WHERE setting = "password"', [':value' => password_hash($new, PASSWORD_BCRYPT, ['cost' => 11])]);
      $this->createSession();
      return ['echo' => 'Your new password is saved!'];
    }

    public function updateMain($email, $dompart, $timezone) {
      //> Make sure all fields are correct
      if(!$this->isLoggedIn()) return ['echo' => 'You need to be logged in!'];
      if(!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['echo' => 'This is not a correct e-mailadres.'];
      if(!is_int((int)$dompart)) return ['echo' => 'The dom lenght needs to be a int number.'];
      if(!in_array($timezone, timezone_identifiers_list())) return ['echo' => 'The timezone is not a valid timezone.'];

      //> Update settings in database, refresh session and return
      $this->database->newQueryArray('UPDATE settings SET value = :value WHERE setting = "email"', array(':value' => $email));
      $this->database->newQueryArray('UPDATE settings SET value = :value WHERE setting = "dompart"', array(':value' => (int)$dompart));
      $this->database->newQueryArray('UPDATE settings SET value = :value WHERE setting = "timezone"', array(':value' => $timezone));
      $this->createSession();
      return ['echo' => 'Your new settings are saved!'];
    }

    public function login($username, $password) {
      //> Query to get user information
      $usernameCheck = $this->database->newQueryArray('SELECT * FROM settings WHERE value = :value AND setting = "username" LIMIT 1', [':value' => $username]);
      $passwordCheck = $this->database->newQueryArray('SELECT * FROM settings WHERE setting = "password" LIMIT 1', [':password' => $password]);

      //> Check if account is valid
      if(!isset($usernameCheck['id'])) return ['echo' => 'This combination of username and password is not found.'];
      if (!password_verify($password, $passwordCheck['value'])) return ['echo' => 'This combination of username and password is not found.'];

      $this->createSession();
      return ['redirect' => 'dashboard'];
    }
  }
