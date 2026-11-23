<?php
class Alert
{
    /**
     * Optional observation hook invoked before any sender runs.
     */
    public static $hook = null;

    /**
     * HTTP transport seam for the curl-based senders (null = use curl directly).
     */
    public static $httpTransport = null;

    /**
     * Mail transport seam (null = use PHP mail()).
     */
    public static $mailTransport = null;

    /**
     * Alert method ids.
     */
    const METHOD_MAIL = 1;
    const METHOD_TELEGRAM = 2;
    const METHOD_SLACK = 3;
    const METHOD_DISCORD = 4;

    /**
     * Dispatch alerts for a stored report.
     *
     * @param array $report
     */
    public static function dispatch(array $report)
    {

        if (self::$hook !== null) {
            call_user_func(self::$hook, $report);
        }

        $sent = array();

        if (Setting::enabled('alert-callback')) {
            $url = (string) Setting::get('callback-url', '');
            if ($url !== '') {
                $ok = false;
                self::guard(function () use ($report, $url, &$ok) {
                    $ok = self::sendCallback($report, $url);
                });
                if ($ok) {
                    $sent['callback'] = true;
                }
            }
        }

        $dompart = (int) Setting::get('dompart', 0);
        if ($dompart > 0 && isset($report['dom']) && strlen((string) $report['dom']) > $dompart) {
            $report['dom'] = substr((string) $report['dom'], 0, $dompart)
                . "\r\nView the full DOM on the report page";
        }

        $userIdToAlert = self::resolveOwner($report);

        foreach (self::channels() as $methodId => $channel) {
            if (!Setting::enabled($channel['setting'])) {
                continue;
            }
            foreach (AlertConfig::recipientsFor($methodId, $userIdToAlert) as $row) {
                if (!self::rowValid($channel, $row)) {
                    continue;
                }
                $ok = false;
                self::guard(function () use ($channel, $report, $row, &$ok) {
                    $ok = (bool) call_user_func($channel['sender'], $report, $row);
                });
                if ($ok) {
                    $sent[$channel['name']] = true;
                }
            }
        }

        if (!empty($sent)) {
            $pid = isset($report['payload']) ? (string) $report['payload'] : '';
            Log::event(
                'alerts',
                0,
                'Alerts dispatched for ' . ($pid !== '' ? $pid : 'report') . ' (' . implode(', ', array_keys($sent)) . ')',
                defined('USER_IP') ? USER_IP : ''
            );
        }
    }

    /**
     * Resolve the payload owner's user id to include their own alert rows.
     *
     * @param array $report
     * @return int
     */
    private static function resolveOwner(array $report)
    {
        $identifier = isset($report['payload']) ? (string) $report['payload'] : '';
        if ($identifier === '') {
            return 0;
        }
        try {
            $payload = Payload::resolveByIdentifier($identifier);
            if (!$payload || (int) $payload['user_id'] === 0) {
                return 0;
            }
            $user = User::find((int) $payload['user_id']);
            if (!$user) {
                return 0;
            }
            return (int) $payload['user_id'];
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Render a template with {{field}} substitution.
     *
     * @param string $templateName
     * @param array $data
     * @param callable $escaper
     */
    public static function renderTemplate($templateName, array $data, $escaper = null)
    {
        $file = self::templatePath($templateName);
        if (!is_file($file)) {
            throw new Exception('Alert template not found: ' . $templateName);
        }
        $template = file_get_contents($file);

        $content = $template;
        if (preg_match_all('/{{(.*?)}}/', $template, $matches)) {
            foreach ($matches[1] as $i => $key) {
                $value = array_key_exists($key, $data) ? $data[$key] : '';
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }
                $value = substr((string) $value, 0, 1024);
                if ($escaper !== null) {
                    $value = (string) call_user_func($escaper, $value, $key);
                }
                $content = str_replace($matches[0][$i], $value, $content);
            }
        }
        return $content;
    }

    /**
     * Strip backticks so fields stay inside markdown fences.
     *
     * @param string $value
     * @return string
     */
    private static function markdownFenceSafe($value)
    {
        return str_replace('`', "'", (string) $value);
    }

    /**
     * JSON-escape a value without surrounding quotes.
     *
     * @param string $value
     * @return string
     */
    private static function jsonValueSafe($value)
    {
        $flags = defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0;
        $encoded = json_encode((string) $value, $flags);
        return ($encoded === false) ? '' : substr($encoded, 1, -1);
    }

    /**
     * Build the placeholder data map from a stored report.
     *
     * @param array $report
     * @return array
     */
    public static function templateData(array $report)
    {
        $data = $report;

        $data['user-agent'] = isset($report['useragent']) ? $report['useragent'] : '';

        $time = isset($report['time']) ? (int) $report['time']
            : (isset($report['created_at']) ? (int) $report['created_at'] : time());
        $data['timestamp'] = gmdate('c', $time);

        if (empty($data['domain'])) {
            $domain = Config::get('DOMAIN', '');
            if ($domain === '' || $domain === 'localhost') {
                $domain = defined('HOST') ? HOST : 'localhost';
            }
            $data['domain'] = $domain;
        }

        return $data;
    }

    /**
     * Send a single alert row through its method's sender (guarded).
     *
     * @param string $methodId
     * @param array $row
     * @param array $report
     * @return bool
     */
    public static function sendOne($methodId, array $row, array $report)
    {
        $channels = self::channels();
        $methodId = (int) $methodId;
        $ok = false;
        self::guard(function () use ($channels, $methodId, $row, $report, &$ok) {
            if (isset($channels[$methodId]) && self::rowValid($channels[$methodId], $row)) {
                $ok = (bool) call_user_func($channels[$methodId]['sender'], $report, $row);
            }
        });
        return $ok;
    }

    /**
     * Channel dispatch table keyed by METHOD_* id.
     *
     * @return array
     */
    private static function channels()
    {
        return array(
            self::METHOD_MAIL => array(
                'setting'  => 'alert-mail',
                'name'     => 'mail',
                'required' => array('value1'),
                'sender'   => function ($report, $row) {
                    return self::sendMail($report, $row['value1']);
                },
            ),
            self::METHOD_TELEGRAM => array(
                'setting'  => 'alert-telegram',
                'name'     => 'telegram',
                'required' => array('value1', 'value2'),
                'sender'   => function ($report, $row) {
                    return self::sendTelegram($report, $row['value1'], $row['value2']);
                },
            ),
            self::METHOD_SLACK => array(
                'setting'  => 'alert-slack',
                'name'     => 'slack',
                'required' => array('value1'),
                'sender'   => function ($report, $row) {
                    return self::sendSlack($report, $row['value1']);
                },
            ),
            self::METHOD_DISCORD => array(
                'setting'  => 'alert-discord',
                'name'     => 'discord',
                'required' => array('value1'),
                'sender'   => function ($report, $row) {
                    return self::sendDiscord($report, $row['value1']);
                },
            ),
        );
    }

    /**
     * True when every field a channel requires is a non-empty value on the row.
     *
     * @param array $channel
     * @param array $row
     * @return bool
     */
    private static function rowValid(array $channel, array $row)
    {
        foreach ($channel['required'] as $field) {
            if (!isset($row[$field]) || (string) $row[$field] === '') {
                return false;
            }
        }
        return true;
    }

    /**
     * A representative sample report for the test-alert endpoint.
     */
    public static function sampleReport()
    {
        return array(
            'id'             => 0,
            'payload'        => defined('HOST') ? HOST : 'localhost',
            'uri'            => 'https://example.com/test',
            'ip'             => '127.0.0.1',
            'referer'        => 'https://example.com/',
            'useragent'      => 'ezXSS test alert',
            'cookies'        => 'test=1',
            'origin'         => 'example.com',
            'dom'            => '<html><!-- ezXSS test alert --></html>',
            'localstorage'   => '{}',
            'sessionstorage' => '{}',
            'extra'          => '',
            'screenshot'     => '',
            'time'           => time(),
        );
    }

    /**
     * POST the normalized report as JSON to the configured callback URL.
     *
     * @param array $report
     * @param string $url
     */
    private static function sendCallback(array $report, $url)
    {
        if (!preg_match('#^[a-z][a-z0-9+.\-]*://#i', $url)) {
            $url = 'https://' . $url;
        }

        $payload = $report;
        unset($payload['screenshotBase']);

        return self::http(array(
            'channel' => 'callback',
            'method'  => 'POST',
            'url'     => $url,
            'json'    => $payload,
            'headers' => array('Content-Type: application/json'),
            'curl'    => array(
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => 20,
            ),
        ));
    }

    /**
     * Send an HTML mail alert (multipart; inline screenshot when present).
     *
     * @param array $report
     * @param string $email
     */
    private static function sendMail(array $report, $email)
    {

        $data = self::templateData($report);
        $escaped = array();
        foreach ($data as $k => $v) {
            $escaped[$k] = is_string($v) ? e($v) : $v;
        }

        $hasShot = !empty($report['screenshotBase']);
        if ($hasShot) {
            $attachment = chunk_split((string) $report['screenshotBase']);
            $escaped['screenshot'] = '<img style="max-width:100%;" src="cid:ezXSS">';
        } else {
            $escaped['screenshot'] = '';
        }

        $body = self::renderTemplate('mail.html', $escaped);

        $boundary = md5(uniqid((string) time(), true));
        $headers = array();
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'From: ezXSS <' . Mailer::fromAddress() . '>';
        $headers[] = "Content-Type: multipart/mixed; boundary=\"ez$boundary\"";

        $multipart = array();
        $multipart[] = "--ez$boundary";
        $multipart[] = 'Content-Type: text/html; charset=utf-8';
        $multipart[] = 'Content-Transfer-Encoding: 8bit';
        $multipart[] = "\n$body\n";

        if ($hasShot) {
            $multipart[] = "--ez$boundary";
            $multipart[] = 'Content-Type: image/png; file_name="screenshot.png"';
            $multipart[] = 'Content-ID: <ezXSS>';
            $multipart[] = 'Content-Transfer-Encoding: base64';
            $multipart[] = 'Content-Disposition: inline; filename="screenshot.png"';
            $multipart[] = "\n$attachment\n";
        }
        $multipart[] = "--ez$boundary--";

        $subject = '[ezXSS] XSS on ' . (isset($escaped['uri']) ? $escaped['uri'] : '');
        $fullBody = implode("\n", str_replace(chr(0), '', $multipart));
        $fullHeaders = implode("\n", $headers);

        if (self::$mailTransport !== null) {
            return call_user_func(self::$mailTransport, $email, $subject, $fullBody, $fullHeaders) !== false;
        }
        return (bool) Mailer::send($email, $subject, $fullBody, $fullHeaders);
    }

    /**
     * Send a Telegram alert via sendMessage or sendPhoto.
     *
     * @param array $report
     * @param string $bottoken
     * @param string $channel
     */
    private static function sendTelegram(array $report, $bottoken, $chatid)
    {
        $text = self::renderTemplate('telegram.md', self::templateData($report), function ($v) {
            return self::markdownFenceSafe($v);
        });

        $ok = self::http(array(
            'channel' => 'telegram',
            'method'  => 'POST',
            'url'     => "https://api.telegram.org/bot{$bottoken}/sendMessage",
            'json'    => array('chat_id' => $chatid, 'parse_mode' => 'markdown', 'text' => $text),
            'headers' => array('Content-Type: application/json'),
            'curl'    => array(CURLOPT_PROTOCOLS => CURLPROTO_HTTPS),
        ));

        if (!empty($report['screenshotBase'])) {
            $photoOk = self::http(array(
                'channel'   => 'telegram-photo',
                'method'    => 'POST',
                'url'       => "https://api.telegram.org/bot{$bottoken}/sendPhoto",
                'photo'     => $report['screenshotBase'],
                'fields'    => array(
                    'chat_id' => $chatid,
                    'caption' => 'Screenshot from XSS Report #' . (isset($report['id']) ? $report['id'] : ''),
                ),
                'headers'   => array('Content-type: multipart/form-data'),
                'curl'      => array(CURLOPT_PROTOCOLS => CURLPROTO_HTTPS),
            ));
            $ok = $ok && $photoOk;
        }

        return $ok;
    }

    /**
     * Send a Slack webhook alert (mrkdwn).
     *
     * @param array $report
     * @param string $webhookUrl
     */
    private static function sendSlack(array $report, $webhookUrl)
    {
        $text = self::renderTemplate('slack.md', self::templateData($report), function ($v) {
            return self::markdownFenceSafe($v);
        });

        return self::http(array(
            'channel' => 'slack',
            'method'  => 'POST',
            'url'     => $webhookUrl,
            'json'    => array('type' => 'mrkdwn', 'text' => $text),
            'headers' => array('Content-Type: application/json'),
            'curl'    => array(
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_FOLLOWLOCATION => false,
            ),
        ));
    }

    /**
     * Send a Discord webhook alert.
     *
     * @param array $report
     * @param string $webhookUrl
     */
    private static function sendDiscord(array $report, $webhookUrl)
    {

        $data = self::templateData($report);
        $hasShot = !empty($report['screenshotBase']);
        $data['screenshot'] = $hasShot ? 'attachment://screenshot.png' : '';

        $rendered = self::renderTemplate('discord.json', $data, function ($v) {
            return self::jsonValueSafe($v);
        });
        $decoded = json_decode($rendered);
        $message = $decoded !== null
            ? json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : $rendered;

        $request = array(
            'channel' => 'discord',
            'method'  => 'POST',
            'url'     => $webhookUrl,
            'headers' => array('Content-type: multipart/form-data'),
            'curl'    => array(
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_FOLLOWLOCATION => false,
            ),
        );
        if ($hasShot) {
            $request['fields'] = array('payload_json' => $message);
            $request['photo'] = $report['screenshotBase'];
        } else {
            $request['fields'] = array('payload_json' => $message);
        }

        return self::http($request);
    }

    /**
     * Dispatch an HTTP request through the transport seam or curl.
     *
     * @param array $request
     * @return bool
     */
    private static function http(array $request)
    {
        if (self::$httpTransport !== null) {
            return call_user_func(self::$httpTransport, $request) !== false;
        }

        $ch = curl_init($request['url']);
        if (isset($request['curl'])) {
            foreach ($request['curl'] as $opt => $val) {
                curl_setopt($ch, $opt, $val);
            }
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, isset($request['method']) ? $request['method'] : 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if (isset($request['headers'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $request['headers']);
        }

        if (isset($request['json'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request['json']));
        } elseif (isset($request['fields'])) {
            $fields = $request['fields'];
            if (isset($request['photo']) && class_exists('CURLFile')) {
                $file = 'data://application/octet-stream;base64,' . $request['photo'];
                $fields[isset($request['fileField']) ? $request['fileField'] : self::fileField($request)] =
                    new \CURLFile($file, 'image/png', 'screenshot.png');
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        }

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $ok = $body !== false && $code >= 200 && $code < 300;
        if (!$ok) {
            Log::event('alerts', 0, 'Alert delivery failed via ' . $request['channel'] . ' (HTTP ' . $code . ')', USER_IP);
        }
        return $ok;
    }

    /**
     * The multipart file field name per channel (telegram = photo, discord = file).
     *
     * @param array $request
     * @return string
     */
    private static function fileField(array $request)
    {
        $channel = isset($request['channel']) ? $request['channel'] : '';
        return strpos($channel, 'telegram') === 0 ? 'photo' : 'file';
    }

    /**
     * Resolve a template file path (overridable in tests via a defined constant).
     *
     * @param string $templateName
     */
    private static function templatePath($templateName)
    {
        $base = defined('FRAMEWORK_ROOT') ? FRAMEWORK_ROOT : dirname(__DIR__);
        return $base . '/views/alerts/' . $templateName;
    }

    /**
     * Run a sender, swallowing any failure so one method never blocks others.
     *
     * @param callable $fn
     */
    private static function guard(callable $fn)
    {
        try {
            $fn();
        } catch (Throwable $e) {
            try {
                Log::event('alerts', 0, 'Alert delivery error: ' . get_class($e), USER_IP);
            } catch (Throwable $logError) {
            }
        }
    }
}
