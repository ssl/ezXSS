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
     * Get or create a CSRF token
     * @method getCsrf
     * @return string 32 character CSRF token
     */
    public function getCsrf(): string
    {
        return $_SESSION['csrfToken'] ?? $_SESSION['csrfToken'] = bin2hex(
                openssl_random_pseudo_bytes(32)
            );
    }

    /**
     * Check credentials and login user
     * @method install
     * @param string $password Password for login
     * @param string $code 2FA code
     * @return array|string
     */
    public function login($password, $code)
    {
        if ($this->isLoggedIn()) {
            return ['redirect' => 'dashboard'];
        }

        $passwordHash = $this->database->fetchSetting('password');
        $secret = $this->database->fetchSetting('secret');

        if (!password_verify($password, $passwordHash)) {
            return 'The password you entered is not valid.';
        }

        if (strlen($secret) === 16 && $this->basic->getCode($secret) != $code) {
            return 'The code is incorrect.';
        }

        $this->createSession();

        if (isset($_SESSION['redirect'])) {
            return ['redirect' => $_SESSION['redirect']];
        }

        return ['redirect' => 'dashboard'];
    }

    /**
     * Check if user is logged in
     * @method isLoggedIn
     * @return boolean If user is logged in true/false
     */
    public function isLoggedIn(): bool
    {
        return isset($_SESSION['login']) ? true : false;
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
     * @return array|string
     */
    public function install($password, $email)
    {
        if ($this->database->isInstalled() === true) {
            return 'This website is already installed.';
        }

        if (strlen($password) < 8) {
            return 'The password needs to be at least 8 characters long.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'This is not a correct email address.';
        }

        $this->database->query(
            'CREATE TABLE IF NOT EXISTS `settings` (`id` int(11) NOT NULL AUTO_INCREMENT,`setting` varchar(500) NOT NULL,`value` text NOT NULL,PRIMARY KEY (`id`)) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;'
        );
        $this->database->query(
            'CREATE TABLE IF NOT EXISTS `reports` (`id` int(11) NOT NULL AUTO_INCREMENT,`shareid` VARCHAR(50) NOT NULL,`cookies` text,`dom` longtext,`origin` varchar(500) DEFAULT NULL,`referer` varchar(1000) DEFAULT NULL,`payload` varchar(500) DEFAULT NULL,`uri` varchar(1000) DEFAULT NULL,`user-agent` varchar(500) DEFAULT NULL,`ip` varchar(50) DEFAULT NULL,`time` int(11) DEFAULT NULL,`archive` int(11) DEFAULT 0,`screenshot` LONGTEXT NULL DEFAULT NULL,`localstorage` LONGTEXT NULL DEFAULT NULL, `sessionstorage` LONGTEXT NULL DEFAULT NULL,PRIMARY KEY (`id`)) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=0;'
        );
        $this->database->query(
            'INSERT INTO `settings` (`setting`, `value`) VALUES ("filter-save", "0"),("filter-alert", "0"),("dompart", "500"),("timezone", "Europe/Amsterdam"),("customjs", ""),("blocked-domains", ""),("notepad", "Welcome :-)"),("secret", ""),("killswitch", ""),("collect_uri", "1"), ("collect_ip", "1"), ("collect_referer", "1"), ("collect_user-agent", "1"), ("collect_cookies", "1"),("collect_localstorage", "1"), ("collect_sessionstorage", "1"), ("collect_dom", "1"), ("collect_origin", "1"), ("collect_screenshot", "0"),("theme", "classic"),("whitelist-domains", ""), ("telegram-bottoken", ""), ("telegram-chatid", ""), ("callback-url", ""), ("alert-mail", "1"), ("alert-telegram", "0"), ("alert-callback", "0"), ("adminurl", "manage"), ("extract-pages", "");'
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

    /**
     * Update database
     * @return array|string
     */
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
            ],
            '3.9' => [
                'INSERT INTO `settings` (`setting`, `value`) VALUES ("collect_uri", "1"), ("collect_ip", "1"), ("collect_referer", "1"), ("collect_user-agent", "1"), ("collect_cookies", "1"),("theme", "classic");',
                'INSERT INTO `settings` (`setting`, `value`) VALUES ("collect_localstorage", "1"), ("collect_sessionstorage", "1"), ("collect_dom", "1"), ("collect_origin", "1"), ("collect_screenshot", "0");',
                'DELETE FROM `settings` WHERE `setting` = "screenshot"',
            ],
            '3.10' => [
                'INSERT INTO `settings` (`setting`, `value`) VALUES ("whitelist-domains", ""), ("telegram-bottoken", ""), ("telegram-chatid", ""), ("callback-url", ""), ("alert-mail", "1"), ("alert-telegram", "0"), ("alert-callback", "0"), ("adminurl", "manage"), ("extract-pages", "");'
            ],
            '3.11' => [
                'ALTER TABLE `reports` CHANGE `uri` `uri` VARCHAR(1000) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;',
                'ALTER TABLE `reports` CHANGE `referer` `referer` VARCHAR(1000) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;'
            ]
        ];

        foreach($updateQuerys as $version => $sqlQuerys) {
            if(version_compare($version, $currentVersion, '>')) {
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
    public function notepad($notepad): string
    {
        $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "notepad"', [':value' => $notepad]);
        return 'Your notepad is saved!';
    }

    /**
     * Update email alert settings
     * @method emailAlertSettings
     * @param string $email Send email to
     * @param string $emailfrom Send email from
     * @param string $mailOn Either on or empty to tell if selected
     * @return string success
     */
    public function emailAlertSettings($mailOn, $email, $emailfrom): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'This is not a correct email address.';
        }

        $alertMail = ($mailOn === 'on') ? 1 : 0;
        $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "alert-mail"', [':value' => $alertMail]);
        $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "email"', [':value' => $email]);
        $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "emailfrom"', [':value' => $emailfrom]);

        return 'E-mail alerts settings are saved.';
    }

    /**
     * Update Telegram alert settings
     * @method telegramAlertSettings
     * @param string $bottoken The API token of the used Telegram bot
     * @param string $chatid Telegram Chat ID to send reports to
     * @param string $telegramOn Either on or empty to tell if selected
     * @return string success
     */
    public function telegramAlertSettings($telegramOn, $bottoken, $chatid): string
    {
        if($bottoken !== '' && !preg_match('/^[a-zA-Z0-9:_-]+$/', $bottoken)) {
            return 'This does not look like an valid Telegram bot token';
        }

        if (!is_int((int)$chatid)) {
            return 'The chat id needs to be a int number.';
        }

        $alertTelegram = ($telegramOn === 'on') ? 1 : 0;
        $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "telegram-bottoken"', [':value' => $bottoken]);
        $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "telegram-chatid"', [':value' => $chatid]);
        $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "alert-telegram"', [':value' => $alertTelegram]);

        return 'Telegram alerts settings are saved.';
    }

    /**
     * Update callback alert settings
     * @method callbackAlertSettings
     * @param string $url Callback url to redirect report to
     * @param string $callbackOn Either on or empty to tell if selected
     * @return string success
     */
    public function callbackAlertSettings($callbackOn, $url): string
    {
        $alertCallback = ($callbackOn === 'on') ? 1 : 0;
        $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "callback-url"', [':value' => $url]);
        $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "alert-callback"', [':value' => $alertCallback]);

        return 'Callback alerts settings are saved.';
    }

    /**
     * Update main settings
     * @method settings
     * @param string $timezone Timezone for reports
     * @param string $theme Theme name
     * @param string $adminurl Admin URL
     * @param string $filterId The id of the used filter
     * @param string $dompart DOM Length for mail
     * @return array|string
     */
    public function settings($timezone, $theme, $adminurl, $filterId, $dompart)
    {
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            return 'The timezone is not a valid timezone.';
        }

        $theme = preg_replace('/[^a-zA-Z0-9]/', '', $theme);
        if(!file_exists(__DIR__ . "/../assets/css/{$theme}.css")) {
            return 'This theme is not installed.';
        }

        if(!preg_match('/^[a-zA-Z0-9-]+$/', $adminurl)) {
            return 'The admin URL contains invalid characters';
        }

        if (!is_int((int)$dompart)) {
            return 'The dom length needs to be a int number.';
        }

        $currentTheme = $this->database->fetchSetting('theme');

        $filterSave = ($filterId == 1 || $filterId == 2) ? 1 : 0;
        $filterAlert = ($filterId == 1 || $filterId == 3) ? 1 : 0;

        $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "dompart"', [':value' => (int)$dompart]);
        $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "filter-save"', [':value' => $filterSave]);
        $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "filter-alert"', [':value' => $filterAlert]);
        $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "timezone"', [':value' => $timezone]);
        $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "theme"', [':value' => $theme]);
        $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "adminurl"', [':value' => $adminurl]);

        if($theme !== $currentTheme || $adminurl !== adminURL) {
            return ['redirect' => '/' . $adminurl . '/settings'];
        }

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
    public function password($password, $newPassword, $newPassword2): string
    {
        $currentPassword = $this->database->fetchSetting('password');

        if (!password_verify($password, $currentPassword)) {
            return 'Current password is not correct.';
        }

        if (strlen($newPassword) < 8) {
            return 'The new password needs to be at least 8 characters long.';
        }

        if ($newPassword !== $newPassword2) {
            return 'The retyped password is not the same as the new password.';
        }

        $this->database->fetch(
            'UPDATE settings SET value = :value WHERE setting = "password"',
            [':value' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 11])]
        );
        $this->createSession();

        return 'Your new password is saved!';
    }

    /**
     * Update screenshot value
     * @method screenshot
     * @param $value
     * @return string success
     */
    public function screenshot($value): string
    {
        $this->database->fetch('UPDATE settings SET value = :value WHERE setting = "screenshot"', [':value' => $value]);
        return 'Your new screenshot options are saved!';
    }

    public function collecting($select) {
        $options = ['uri', 'ip', 'referer', 'user-agent', 'cookies', 'localstorage', 'sessionstorage', 'dom', 'origin', 'screenshot'];
        foreach($options as $option) {
            if (isset($select[$option])) {
                $this->database->fetch(
                    'UPDATE settings SET value = :value WHERE setting = :setting',
                    [
                        'setting' => "collect_{$option}",
                        ':value' => '1'
                    ]
                );
            } else {
                $this->database->fetch(
                    'UPDATE settings SET value = :value WHERE setting = :setting',
                    [
                        'setting' => "collect_{$option}",
                        ':value' => '0'
                    ]
                );
            }
        }
        return 'Collecting settings are saved.';
    }

    /**
     * Update custom payload
     * @method payload
     * @param string $customjs Custom created javascript code
     * @return string success
     */
    public function payload($customjs): string
    {
        $this->database->fetch(
            'UPDATE settings SET value = :value WHERE setting = "customjs"',
            [':value' => base64_decode($customjs)]
        );
        return 'Your new settings are saved!';
    }

    /**
     * Update two factor settings
     * @method twofactor
     * @param string $secret generated secret code
     * @param string $code 6 digit 2fa code
     * @return string success
     */
    public function twofactor($secret, $code): string
    {
        $secretCode = $this->database->fetchSetting('secret');

        if (strlen($secret) == 16) {
            if (strlen($secretCode) === 16) {
                return '2FA settings are already enabled.';
            }

            if (strlen($secret) !== 16) {
                return 'Secret length needs to be 16 characters long';
            }

            if ($this->basic->getCode($secret) != $code) {
                return 'Code is incorrect.';
            }
        } else {
            if (strlen($secretCode) !== 16) {
                return '2FA settings are already disabled.';
            }

            if ($this->basic->getCode($secretCode) != $code) {
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
     * @return string success
     */
    public function archiveReport($id): string
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
    public function deleteReport($id): string
    {
        $report = $this->database->fetch('SELECT screenshot FROM reports WHERE id = :id', [':id' => $id]);
        if(($report['screenshot'] != '') && file_exists(
                __DIR__ . '/../assets/img/report-' . $report['screenshot'] . '.png'
            )) {
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
    public function shareReport($id, $domain, $email): string
    {
        $report = $this->database->fetch('SELECT * FROM reports WHERE id = :id LIMIT 1', [':id' => $id]);

        if (!isset($report['id'])) {
            return 'This report does not exists.';
        }

        if(empty($domain) && empty($email)) {
            return 'No domain or email submitted.';
        }

        if(!empty($domain)) {
            $report['referer'] = !empty($report['referer']) ? 'Shared via ' . $_SERVER['SERVER_NAME'] . ' - ' . $report['referer'] : 'Shared via ' . $_SERVER['SERVER_NAME'];
            $report['shared'] = true;

            $cb = curl_init((parse_url($domain, PHP_URL_SCHEME) ? '' : 'https://') . $domain . '/callback');
            curl_setopt($cb, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($cb, CURLOPT_POSTFIELDS, json_encode($report));
            curl_setopt($cb, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($cb, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $result = curl_exec($cb);

            if ($result !== 'github.com/ssl/ezXSS') {
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

            $emailfrom = $this->database->fetchSetting('emailform');

            $headers[] = 'From: ' . $emailfrom;
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
    public function killSwitch($pass): string
    {
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
    public function deleteSelected($ids): string
    {
        foreach($ids as $id) {
            $report = $this->database->fetch('SELECT screenshot FROM reports WHERE id = :id', [':id' => $id]);
            if(file_exists(__DIR__ . '/../assets/img/report-' . $report['screenshot'] . '.png')) {
                unlink(__DIR__ . '/../assets/img/report-' . $report['screenshot'] . '.png');
            }

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
    public function archiveSelected($ids, $archive): string
    {
        foreach($ids as $id) {
            $this->database->fetch(
                'UPDATE reports SET archive = :archive WHERE id = :id',
                [':id' => $id, 'archive' => $archive]
            );
        }
        return 'Reports are archived.';
    }

    /**
     * Remove domain or page from list
     * @method removeDomain
     * @param string $type list type
     * @param string $domain domain or path value
     * @return array|string
     */
    public function removeDomain($type, $domain) {
        if($type === 'whitelist') {
            $whitelist = $this->database->fetchSetting('whitelist-domains');
            $whitelist = str_replace(array($domain . ',', ',' . $domain, $domain), '', $whitelist);
            $this->database->fetch(
                "UPDATE settings SET value = :list WHERE setting = 'whitelist-domains';",
                [':list' => $whitelist]
            );

            if($whitelist === '') {
                return ['redirect' => 'payload'];
            }
        }

        if($type === 'blacklist') {
            $blacklist = $this->database->fetchSetting('blocked-domains');
            $blacklist = str_replace(array($domain . ',', ',' . $domain, $domain), '', $blacklist);
            $this->database->fetch(
                "UPDATE settings SET value = :list WHERE setting = 'blocked-domains';",
                [':list' => $blacklist]
            );

            if($blacklist === '') {
                return ['redirect' => 'payload'];
            }
        }

        if($type === 'page') {
            $pages = $this->database->fetchSetting('extract-pages');
            $pages = str_replace(array($domain . '|||', '|||' . $domain, $domain), '', $pages);
            $this->database->fetch(
                "UPDATE settings SET value = :list WHERE setting = 'extract-pages';",
                [':list' => $pages]
            );

            if($pages === '') {
                return ['redirect' => 'payload'];
            }
        }

        return 'Updated list';
    }

    /**
     * Add domain or page from list
     * @method addDomain
     * @param string $type list type
     * @param string $domain domain or path value
     * @return array|string
     */
    public function addDomain($type, $domain) {
        if($type === 'whitelist') {
            $whitelist = $this->database->fetchSetting('whitelist-domains');
            $whitelist .= ',' . $domain;
            $this->database->fetch(
                "UPDATE settings SET value = :list WHERE setting = 'whitelist-domains';",
                [':list' => $whitelist]
            );
        }

        if($type === 'blacklist') {
            $blacklist = $this->database->fetchSetting('blocked-domains');
            $blacklist .= ',' . $domain;
            $this->database->fetch(
                "UPDATE settings SET value = :list WHERE setting = 'blocked-domains';",
                [':list' => $blacklist]
            );
        }

        if($type === 'page') {
            if (strpos($domain, '/') !== 0) {
                $domain = '/' . $domain;
            }
            $pages = $this->database->fetchSetting('extract-pages');
            $pages .= '|||' . $domain;
            $this->database->fetch(
                "UPDATE settings SET value = :list WHERE setting = 'extract-pages';",
                [':list' => $pages]
            );
        }

        return ['redirect' => 'payload'];
    }

    /**
     * Try to get the chatId of Telegram
     * @method getChatId
     * @param string $bottoken The API token of the used Telegram bot
     * @return string chatId or error
     */
    public function getChatId($bottoken): string
    {
        if(!preg_match('/^[a-zA-Z0-9:_-]+$/', $bottoken)) {
            return 'This does not look like an valid Telegram bot token';
        }

        $api = curl_init("https://api.telegram.org/bot{$bottoken}/getUpdates");
        curl_setopt($api, CURLOPT_RETURNTRANSFER, true);
        $results = json_decode(curl_exec($api), true);

        if($results['ok'] !== true) {
            return 'Something went wrong. Your bot token is probably invalid.';
        }

        if(isset($results['result'][0]['message']['chat']['id'])) {
            return 'chatId:' . $results['result'][0]['message']['chat']['id'];
        }

        return 'Your bot token seems valid, but I cannot find a chat. Start a chat with your bot by sending /start';
    }

    /**
     * Get statistics of amount of reports
     * @method statistics
     * @return string count
     */
    public function statistics(): string
    {
        $statistics = ['total' => 0, 'week' => 0, 'totaldomains' => 0, 'weekdomains' => 0, 'collected' => 0, 'last' => 'never'];

        $allReports = $this->database->fetchAll('SELECT origin,time,payload FROM reports ORDER BY id ASC', []);

        $statistics['total'] = count($allReports);

        $uniqueDomains = [];
        $uniqueDomainsWeek = [];
        foreach($allReports as $report) {

            // Counts report from last week
            if($report['time'] > time() - 604800) {
                $statistics['week']++;

                // Counts unique domains from last week
                if(!in_array($report['origin'], $uniqueDomainsWeek, true)) {
                    $uniqueDomainsWeek[] = $report['origin'];
                    $statistics['weekdomains']++;
                }
            }

            // Counts unique domains
            if(!in_array($report['origin'], $uniqueDomains, true)) {
                $uniqueDomains[] = $report['origin'];
                $statistics['totaldomains']++;
            }

            // Counts amount of shared reports
            if(strpos($report['payload'], 'Collected page via ') === 0) {
                $statistics['collected']++;
            }
        }

        $lastReport = end($allReports);
        if(isset($lastReport['time'])) {
            $time = time() - $lastReport['time'];
            $syntaxText = 's';
            if ($time > 60) {
                $time /= 60;
                $syntaxText = 'm';
            }
            if ($time > 60) {
                $time /= 60;
                $syntaxText = 'h';
            }
            if ($time > 24) {
                $time /= 24;
                $syntaxText = 'd';
            }
            $statistics['last'] = floor($time) . $syntaxText;
        }

        return json_encode($statistics);
    }
}
