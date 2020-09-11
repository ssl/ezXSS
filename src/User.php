<?php

class User
{

    /**
     * Default values stored & session info
     * @method __construct
     */
    public function __construct()
    {
        $this->database = new Database();
        $this->basic = new Basic();

        if (!isset($_SESSION)) {
            session_set_cookie_params(6000000, '/', null, false, true);
            session_start();
        }
    }

    /**
     * Get or create a csrf-token
     * @method getCsrf
     * @return string 32 character long csrf-token
     */
    public function getCsrf()
    {
        return (!isset($_SESSION['csrfToken'])) ? $_SESSION['csrfToken'] = bin2hex(
            openssl_random_pseudo_bytes(32)
        ) : $_SESSION['csrfToken'];
    }

    /**
     * Check credentials and login user
     * @method install
     * @param string $password Password for login
     * @param string $code 2FA code
     * @return string error/redirect
     */
    public function login($password, $code)
    {
        if ($this->isLoggedIn()) {
            return ['redirect' => 'dashboard'];
        }

        $passwordHash = $this->database->fetch('SELECT * FROM settings WHERE setting = "password"');
        $secret = $this->database->fetch('SELECT * FROM settings WHERE setting = "secret"');

        if (!password_verify($password, $passwordHash['value'])) {
            return 'The password you entered is not valid.';
        }

        if (strlen($secret['value']) == 16 && $this->basic->getCode($secret['value']) != $code) {
            return 'The code is incorrect.';
        }

        $this->createSession();

        if (isset($_SESSION['redirect'])) {
            return ['redirect' => $_SESSION['redirect']];
            unsset($_SESSION['redirect']);
        } else {
            return ['redirect' => 'dashboard'];
        }
    }

    /**
     * Check if user is logged in
     * @method isLoggedIn
     * @return boolean If user is logged in true/false
     */
    public function isLoggedIn()
    {
        return (isset($_SESSION['login'])) ? $_SESSION['login'] : false;
    }

    /**
     * Set settings in session cookie
     * @method createSession
     */
    public function createSession(): void
    {
        $_SESSION['login'] = true;
    }

    /**
     * Install website
     * @method install
     * @param string $password Password for login
     * @param string $email Email for email alert
     * @return string error/redirect
     */
    public function install($password, $email)
    {
        if ($this->database->rowCount('SELECT * FROM settings') > 0) {
            return 'This website is already installed.';
        }

        if (strlen($password) < 8) {
            return 'The password needs to be atleast 8 characters long.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'This is not a correct email address.';
        }

        $this->database->query(
            'CREATE TABLE IF NOT EXISTS `settings` (`id` int(11) NOT NULL AUTO_INCREMENT,`setting` varchar(500) NOT NULL,`value` text NOT NULL,PRIMARY KEY (`id`)) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;'
        );
        $this->database->query(
            'CREATE TABLE IF NOT EXISTS `reports` (`id` int(11) NOT NULL AUTO_INCREMENT,`shareid` VARCHAR(50) NOT NULL,`cookies` text,`dom` longtext,`origin` varchar(500) DEFAULT NULL,`referer` varchar(500) DEFAULT NULL,`payload` varchar(500) DEFAULT NULL,`uri` varchar(500) DEFAULT NULL,`user-agent` varchar(500) DEFAULT NULL,`ip` varchar(50) DEFAULT NULL,`time` int(11) DEFAULT NULL,`archive` int(11) DEFAULT 0,`screenshot` LONGTEXT NULL DEFAULT NULL,`localstorage` LONGTEXT NULL DEFAULT NULL, `sessionstorage` LONGTEXT NULL DEFAULT NULL,PRIMARY KEY (`id`)) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=0;'
        );
        $this->database->query(
            'INSERT INTO `settings` (`setting`, `value`) VALUES ("filter-save", "0"),("filter-alert", "0"),("dompart", "500"),("timezone", "Europe/Amsterdam"),("customjs", ""),("blocked-domains", ""),("notepad", "Welcome :-)"),("screenshot", "0"),("secret", ""),("killswitch", "");'
        );
        $this->database->fetch(
            'INSERT INTO `settings` (`setting`, `value`) VALUES ("password", :password),("email", :email),("payload-domain", :domain),("version", :version),("emailfrom", "ezXSS");',
            [
                ':password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 11]),
                ':email' => $email,
                ':domain' => $this->basic->domain(),
                ':version' => version
            ]
        );

        $this->createSession();
        return ['redirect' => 'dashboard'];
    }

    public function update() {
        $currentVersion = $this->database->fetchSetting('version');

        if($currentVersion === version) {
            return 'You are already up-to-date.';
        }

        $updateQuerys = [
            '3.5' => [
                'INSERT INTO `settings` (`setting`, `value`) VALUES ("killswitch", "");',
                'ALTER TABLE `reports` ADD `payload` VARCHAR(500) NULL AFTER `referer`;'
            ],
            '3.6' => [
                'INSERT INTO `settings` (`setting`, `value`) VALUES ("emailfrom", "ezXSS");'
            ]
        ];

        foreach($updateQuerys as $version => $sqlQuerys) {
            if($version > $currentVersion) {
                foreach ($sqlQuerys as $sqlQuery) {
                    $this->database->query($sqlQuery);
                }
            }
        }

        if(empty($currentVersion)) {
          $this->database->query('INSERT INTO `settings` (`setting`, `value`) VALUES ("version", "' . version . '");');
        } else {
          $this->database->query('UPDATE settings SET value = "' . version . '" WHERE setting = "version"');
        }

        return ['redirect' => 'dashboard'];
    }

    /**
     * Update notepad value
     * @method notepad
     * @param string $notepad Value of the notepad
     * @return string success
     */
    public function notepad($notepad)
    {
        $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "notepad"', [':value' => $notepad]);
        return 'Your notepad is saved!';
    }

    /**
     * Update main settings
     * @method settings
     * @param string $email New email for alerts
     * @param string $emailFrom Send email from
     * @param string $domPart DOM Length for mail
     * @param string $timezone Timezone for reports
     * @param string $payload Payload domain used
     * @return string success
     */
    public function settings($email, $emailFrom, $domPart, $timezone, $payload)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'This is not a correct email address.';
        }

        if (!is_int((int)$domPart)) {
            return 'The dom lenght needs to be a int number.';
        }

        if (!in_array($timezone, timezone_identifiers_list())) {
            return 'The timezone is not a valid timezone.';
        }

        $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "email"', [':value' => $email]);
        $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "emailfrom"', [':value' => $emailFrom]);
        $this->database->fetch(
            'UPDATE settings SET value = :value WHERE setting = "dompart"',
            [':value' => (int)$domPart]
        );
        $this->database->fetch(
            'UPDATE settings SET value = :value WHERE setting = "timezone"',
            [':value' => $timezone]
        );
        $this->database->fetch(
            'UPDATE settings SET value = :value WHERE setting = "payload-domain"',
            [':value' => $payload]
        );
        $this->createSession();
        return 'Your new settings are saved!';
    }

    /**
     * Update password
     * @method password
     * @param string $password Current password
     * @param string $newPassword New password
     * @param string $newPassword2 New password retyped
     * @return string success
     */
    public function password($password, $newPassword, $newPassword2)
    {
        $currentPassword = $this->database->fetch('SELECT * FROM settings WHERE setting = "password" LIMIT 1');

        if (!password_verify($password, $currentPassword['value'])) {
            return 'Current password is not correct.';
        }

        if (strlen($newPassword) < 8) {
            return 'The new password needs to be atleast 8 characters long.';
        }

        if ($newPassword != $newPassword2) {
            return 'The retypted password is not the same as the new password.';
        }

        $this->database->fetch(
            'UPDATE settings SET value = :value WHERE setting = "password"',
            [':value' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 11])]
        );
        $this->createSession();

        return 'Your new password is saved!';
    }

    /**
     * Update filter values
     * @method filter
     * @param string $id Filter combination id
     * @return string success
     */
    public function filter($id)
    {
        switch ($id) {
            case 1 :
                $filterSave = 1;
                $filterAlert = 1;
                break;
            case 2 :
                $filterSave = 1;
                $filterAlert = 0;
                break;
            case 3 :
                $filterSave = 0;
                $filterAlert = 1;
                break;
            case 4 :
                $filterSave = 0;
                $filterAlert = 0;
                break;
            default :
                $filterSave = 0;
                $filterAlert = 0;
                break;
        }

        $this->database->fetch(
            'UPDATE settings SET value = :value WHERE setting = "filter-save"',
            [':value' => $filterSave]
        );
        $this->database->fetch(
            'UPDATE settings SET value = :value WHERE setting = "filter-alert"',
            [':value' => $filterAlert]
        );
        return 'Your new filter options are saved!';
    }

    /**
     * Update screenshot value
     * @method screenshot
     * @param string $id Filter combination id
     * @return string success
     */
    public function screenshot($value)
    {
        $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "screenshot"', [':value' => $value]);
        return 'Your new screenshot options are saved!';
    }

    /**
     * Update blocked domains
     * @method blockDomains
     * @param string $domains List of domains
     * @return string success
     */
    public function blockDomains($domains)
    {
        $this->database->fetch(
            'UPDATE settings SET value = :value WHERE setting = "blocked-domains"',
            [':value' => $domains]
        );
        return 'Your new settings are saved!';
    }

    /**
     * Update custom payload
     * @method payload
     * @param string $customjs Custom created javascript code
     * @return string success
     */
    public function payload($customjs)
    {
        $this->database->fetch(
            'UPDATE settings SET value = :value WHERE setting = "customjs"',
            [':value' => $customjs]
        );
        return 'Your new settings are saved!';
    }

    /**
     * Update twofactor settings
     * @method twofactor
     * @param string $secret generated secret code
     * @param string $code 6 digit 2fa code
     * @return string success
     */
    public function twofactor($secret, $code)
    {
        $secretCode = $this->database->fetch('SELECT * FROM settings WHERE setting = "secret"');

        if (strlen($secret) == 16) {
            if (strlen($secretCode['value']) == 16) {
                return '2FA settings are already enabled.';
            }

            if (strlen($secret) != 16) {
                return 'Secret length needs to be 16 characters long';
            }

            if ($this->basic->getCode($secret) != $code) {
                return 'Code is incorrect.';
            }
        } else {
            if (strlen($secretCode['value']) != 16) {
                return '2FA settings are already disabled.';
            }

            if ($this->basic->getCode($secretCode['value']) != $code) {
                return 'Code is incorrect.';
            }

            $secret = 0;
        }

        $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "secret"', [':value' => $secret]);
        return 'Your new 2FA settings are saved!';
    }

    /**
     * Update archive status
     * @method archiveReport
     * @param string $id report id
     * @param string $archive either 1 of 0
     * @return string success
     */
    public function archiveReport($id)
    {
        $report = $this->database->fetch('SELECT archive FROM reports WHERE id = :id', [':id' => $id]);
        $archive = $report['archive'] == '0' ? '1' : '0';

        $this->database->fetch(
            'UPDATE reports SET archive = :archive WHERE id = :id',
            [':id' => $id, 'archive' => $archive]
        );
        return 'Report is archived!';
    }

    /**
     * Delete report
     * @method deleteReport
     * @param string $id report id
     * @return string success
     */
    public function deleteReport($id)
    {
        $report = $this->database->fetch('SELECT screenshot FROM reports WHERE id = :id', [':id' => $id]);
        if($report['screenshot'] != '') {
            unlink(__DIR__ . '/../assets/img/report-' . $report['screenshot'] . '.png');
        }

        $this->database->fetch('DELETE FROM reports WHERE id = :id', [':id' => $id]);
        return 'Report is deleted!';
    }

    /**
     * Share report
     * @method shareReport
     * @param string $id report id
     * @param string $domain domain to share with
     * @param string $email email to share with
     * @return string success
     */
    public function shareReport($id, $domain, $email)
    {
        $report = $this->database->fetch('SELECT * FROM reports WHERE id = :id LIMIT 1', [':id' => $id]);

        if (!isset($report['id'])) {
            return 'This report does not exists.';
        }

        if(empty($domain) && empty($email)) {
            return 'No domain or email submitted.';
        }

        if(!empty($domain)) {
            $report['referrer'] = !empty($report['referer']) ? 'Shared via ' . $_SERVER['SERVER_NAME'] . ' - ' . $report['referer'] : 'Shared via ' . $_SERVER['SERVER_NAME'];
            $report['shared'] = true;

            $cb = curl_init((parse_url($domain, PHP_URL_SCHEME) ? '' : 'https://') . $domain . '/callback');
            curl_setopt($cb, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($cb, CURLOPT_POSTFIELDS, json_encode($report));
            curl_setopt($cb, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($cb, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $result = curl_exec($cb);

            if ($result != 'github.com/ssl/ezXSS') {
                return 'Unable to find a valid ezXSS installation. Please check the domain.';
            }

            return 'Report is successfully shared via domain!';
        }

        if(!empty($email)) {
            if (!empty($report['screenshot'])) {
                $report['screenshot'] = $this->basic->screenshotPath($report['screenshot']);
            }

            $htmlTemplate = str_replace(
                [
                    '{{id}}',
                    '{{domain}}',
                    '{{url}}',
                    '{{ip}}',
                    '{{referer}}',
                    '{{payload}}',
                    '{{user-agent}}',
                    '{{cookies}}',
                    '{{localstorage}}',
                    '{{sessionstorage}}',
                    '{{dom}}',
                    '{{origin}}',
                    '{{time}}',
                    '{{screenshot}}'
                ],
                [
                    $report['shareid'],
                    htmlspecialchars($this->basic->domain()),
                    htmlspecialchars($report['uri']),
                    htmlspecialchars($report['ip']),
                    htmlspecialchars($report['referer']),
                    htmlspecialchars($report['payload']),
                    htmlspecialchars($report['user-agent']),
                    htmlspecialchars($report['cookies']),
                    htmlspecialchars($report['localstorage']),
                    htmlspecialchars($report['sessionstorage']),
                    htmlspecialchars($report['dom']),
                    htmlspecialchars($report['origin']),
                    date('F j, Y, g:i a', $report['time']),
                    $report['screenshot']
                ],
                $this->basic->htmlBlocks('mail')
            );

            $emailfrom = $this->database->fetch('SELECT * FROM settings WHERE setting = "emailfrom"');

            $headers[] = 'From: ' . $emailfrom['value'];
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-type: text/html; charset=iso-8859-1';
            mail(
                $email,
                '[ezXSS] Shared XSS on ' . htmlspecialchars($report['uri']),
                $htmlTemplate,
                implode("\r\n", $headers)
            );

            return 'Report is successfully shared via email!';
        }

        return 'Something went wrong..';
    }

    /**
     * Kills ezXSS setup
     * @method killSwitch
     * @param string $pass password to re-enable
     * @return string success
     */
    public function killSwitch($pass) {
        $this->database->fetch(
            "UPDATE settings SET value = :pass WHERE setting = 'killswitch';",
            [':pass' => $pass]
        );
        return 'Killed switch activated.';
    }

    /**
     * Delete selected reports
     * @method deleteSelected
     * @param string $ids report ids
     * @return string success
     */
    public function deleteSelected($ids) {
        foreach($ids as $id) {
            $report = $this->database->fetch('SELECT screenshot FROM reports WHERE id = :id', [':id' => $id]);
            unlink(__DIR__ . '/../assets/img/report-' . $report['screenshot'] . '.png');

            $this->database->fetch('DELETE FROM reports WHERE id = :id', [':id' => $id]);
        }
        return 'Reports are deleted.';
    }

    /**
     * Update archive status on selected
     * @method archiveSelected
     * @param string $ids report ids
     * @param string $archive either 1 of 0
     * @return string success
     */
    public function archiveSelected($ids, $archive) {
        foreach($ids as $id) {
            $this->database->fetch(
                'UPDATE reports SET archive = :archive WHERE id = :id',
                [':id' => $id, 'archive' => $archive]
            );
        }
        return 'Reports are archived.';
    }
}
