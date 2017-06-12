<?php

  class User {

    public function __construct() {
      $this->database = new Database();
      $this->base32Characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

      session_set_cookie_params(6000000, '/', null, false, true);
      if(!isset($_SESSION)) session_start();

    }

    public function isLoggedIn() {
      return (isset($_SESSION['login'])) ? $_SESSION['login'] : false;
    }

    public function sessionInfo($key) {
      return $_SESSION[$key];
    }

    public function createSession() {
      #) Get each column and value from user
      $email = $this->database->fetch('SELECT * FROM settings WHERE setting = "email" LIMIT 1');

      #) Add this information to session
      $_SESSION['login'] = true;
      $_SESSION['email'] = $email['value'];

    }

    public function getCsrf() {
      #) Get the CSRF code or create a new one
      return (!isset($_SESSION['csrfToken'])) ? $_SESSION['csrfToken'] = bin2hex(openssl_random_pseudo_bytes(32)) : $_SESSION['csrfToken'];
    }

    public function updateFilters($save, $alert) {
      #) Make sure everything is OK
      if(!$this->isLoggedIn()) return 'You need to be logged in!';
      if($save != 0 && $save != 1 || $alert != 0 && $alert != 1) return 'Value needs to be true or false (1 or 0)';

      $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "filter-save"', [':value' => $save]);
      $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "filter-alert"', [':value' => $alert]);
      return 'Your new filter settings are saved!';
    }

    public function shareReport($id, $domain) {
      #) Make sure everything is OK
      if(!$this->isLoggedIn()) return 'You need to be logged in!';
      $report = $this->database->fetch('SELECT * FROM reports WHERE id = :id LIMIT 1', [':id' => $id]);
      if(!isset($report['id'])) return 'The report ID is not found.';
      $report['screenshot'] = base64_encode(file_get_contents('https://' . $_SERVER['SERVER_NAME'] . '/manage/assets/images/reports/' . $report['screenshot'] . '.png'));
      $report['referrer'] = !empty($report['referer']) ? 'Shared via ' . $_SERVER['SERVER_NAME'] . ' - '. $report['referer'] : 'Shared via ' . $_SERVER['SERVER_NAME'];
      $report['shared'] = true;

      #) Send the information to the other user
      $cb = curl_init(urlencode($domain) . '/callback');
      curl_setopt($cb, CURLOPT_CUSTOMREQUEST, 'POST');
      curl_setopt($cb, CURLOPT_POSTFIELDS, json_encode($report));
      curl_setopt($cb, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($cb, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
      $result = curl_exec($cb);

      if($result != 'github.com/ssl/ezXSS') return 'Unable to find a valid ezXSS installation. Please check the domain.';
      return 'Successfully shared the report.';
    }

    public function deleteReport($id) {
      #) Make sure everything is OK
      if(!$this->isLoggedIn()) return 'You need to be logged in!';

      #) Delete screenshot
      $report = $this->database->fetch('SELECT * FROM reports WHERE id = :id', [':id' => $id]);
      unlink('assets/images/reports/' . $report['screenshot'] . '.png');

      $this->database->fetch('DELETE FROM reports WHERE id = :id', [':id' => $id]);
      return 'Report is deleted.';
    }

    public function updatePayload($customjs) {
      #) Make sure everything is OK
      if(!$this->isLoggedIn()) return 'You need to be logged in!';

      $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "customjs"', [':value' => $customjs]);
      return 'Your new custom javascript is saved!';
    }

    public function updatePassword($current, $new, $new2) {
      #) Make sure everything is OK
      if(!$this->isLoggedIn()) return 'You need to be logged in!';
      $password = $this->database->fetch('SELECT * FROM settings WHERE setting = "password" LIMIT 1');
      if (!password_verify($current, $password['value'])) return 'Current password is not correct.';
      if($new != $new2) return 'The retypted password is not the same as the new password.';
      if(strlen($new) < 8) return 'The new password needs to be atleast 8 characters long.';

      #) Update settings in database, refresh session and return
      $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "password"', [':value' => password_hash($new, PASSWORD_BCRYPT, ['cost' => 11])]);
      $this->createSession();
      return 'Your new password is saved!';
    }

    public function updateMain($email, $dompart, $timezone) {
      #) Make sure everything is OK
      if(!$this->isLoggedIn()) return 'You need to be logged in!';
      if(!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'This is not a correct email address.';
      if(!is_int((int)$dompart)) return 'The dom lenght needs to be a int number.';
      if(!in_array($timezone, timezone_identifiers_list())) return 'The timezone is not a valid timezone.';

      #) Update settings in database, refresh session and return
      $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "email"', [':value' => $email]);
      $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "dompart"', [':value' => (int)$dompart]);
      $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "timezone"', [':value' => $timezone]);
      $this->createSession();
      return 'Your new settings are saved!';
    }

    public function updateTwofactor($secret, $code) {
      #) Make sure everything is OK
      if(!$this->isLoggedIn()) return ['redirect' => 'dashboard'];
      if(strlen($code) != 6) return 'Code length needs to be 6 characters long';
      $secretCode = $this->database->fetch('SELECT * FROM settings WHERE setting = "secret"');

      if(strlen($secret) == 16) {

        if(strlen($secretCode['value']) == 16) return '2FA settings are already enabled.';
        if(strlen($secret) != 16) return 'Secret length needs to be 16 characters long';
        if($this->getCode($secret) != $code) return 'Code is incorrect.';

      } else {

        if(strlen($secretCode['value']) != 16) return '2FA settings are already disabled.';
        if($this->getCode($secretCode['value']) != $code) return 'Code is incorrect.';
        $secret = 0;

      }

      #) Save secret 2FA in database
      $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "secret"', [':value' => $secret]);
      return 'Your new 2FA settings are saved!';

    }

    public function install($password, $email) {
      #) Make sure everything is OK
      $installCheck = $this->database->rowCount('SELECT * FROM settings');
      if($installCheck > 0) return 'This website is already installed! :-)';
      if(strlen($password) < 8) return 'The password needs to be atleast 8 characters long.';
      if(!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'This is not a correct email address.';

      #) Input information in database
      $this->database->query('CREATE TABLE IF NOT EXISTS `settings` (`id` int(11) NOT NULL AUTO_INCREMENT,`setting` varchar(500) NOT NULL,`value` text NOT NULL,PRIMARY KEY (`id`)) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;');
      $this->database->query('CREATE TABLE IF NOT EXISTS `reports` (`id` int(11) NOT NULL AUTO_INCREMENT,`cookies` text,`dom` longtext,`origin` varchar(500) DEFAULT NULL,`referer` varchar(500) DEFAULT NULL,`screenshot` varchar(500) DEFAULT NULL,`uri` varchar(500) DEFAULT NULL,`user-agent` varchar(500) DEFAULT NULL,`ip` varchar(50) DEFAULT NULL,`time` int(11) DEFAULT NULL,PRIMARY KEY (`id`)) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=0;');
      $this->database->query('INSERT INTO `settings` (`setting`, `value`) VALUES ("secret", ""),("filter-save", "0"),("filter-alert", "0"),("dompart", "500"),("timezone", "Europe/Amsterdam"),("customjs", "");');
      $this->database->fetch('INSERT INTO `settings` (`setting`, `value`) VALUES ("password", :password),("email", :email);', [':password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 11]), 'email' => $email]);

      $this->createSession();
      return ['redirect' => 'dashboard'];
    }

    public function login($password, $code) {
      #) Make sure everything is OK
      if($this->isLoggedIn()) return ['redirect' => 'dashboard'];

      #) Query to get user information
      $passwordCheck = $this->database->fetch('SELECT * FROM settings WHERE setting = "password" LIMIT 1', [':password' => $password]);
      $secretCheck = $this->database->fetch('SELECT * FROM settings WHERE setting = "secret"');

      #) Check if account is valid
      if (!password_verify($password, $passwordCheck['value'])) return 'The password you entered is not valid.';
      if(strlen($secretCheck['value']) == 16 && $this->getCode($secretCheck['value']) != $code) return 'The code is incorrect.';

      $this->createSession();
      return ['redirect' => 'dashboard'];
    }


    #) Private functions

    private function getCode($secret) {
      $secretKey = $this->baseDecode($secret);

      $hash = hash_hmac('SHA1', chr(0) . chr(0) . chr(0) . chr(0) . pack('N*', floor(time() / 30)), $secretKey, true);
      $value = unpack('N', substr($hash, ord(substr($hash, -1)) & 0x0F, 4));
      $value = $value[1] & 0x7FFFFFFF;

      return str_pad($value % pow(10, 6), 6, '0', STR_PAD_LEFT);
    }

    private function baseDecode($data) {
      $characters = $this->base32Characters;
      $buffer = 0;
      $bufferSize = 0;
      $result = '';

      for ($i = 0; $i < strlen($data); $i++) {
        $position = strpos($characters, $data[$i]);
        $buffer = ($buffer << 5) | $position;
        $bufferSize += 5;

        if ($bufferSize > 7) {
          $bufferSize -= 8;
          $position = ($buffer & (0xff << $bufferSize)) >> $bufferSize;
          $result .= chr($position);
        }
      }

      return $result;
    }
  }
