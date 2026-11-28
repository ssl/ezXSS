<?php
class AlertsController extends Controller
{
    /**
     * Web-reachable actions.
     */
    public static $routable = array('index', 'settings', 'test', 'chatid');

    /**
     * Slack webhook URL pattern.
     */
    const SLACK_RE = '/^https:\/\/hooks\.slack\.com\/services\/([a-zA-Z0-9]+)\/([a-zA-Z0-9_-]+)\/([a-zA-Z0-9_-]+)$/';
    /**
     * Discord webhook URL pattern.
     */
    const DISCORD_RE = '/^https:\/\/(discord|discordapp)\.com\/api\/webhooks\/([\d]+)\/([a-zA-Z0-9_-]+)$/';
    /**
     * Telegram bot token pattern.
     */
    const TELEGRAM_TOKEN_RE = '/^[a-zA-Z0-9:_-]+$/';
    /**
     * Telegram chat id pattern.
     */
    const TELEGRAM_CHATID_RE = '/^[0-9-]*$/';

    /**
     * Telegram getUpdates transport seam: function(string $token): string.
     */
    public static $telegramTransport = null;

    /**
     * GET read own destinations; POST update own destinations.
     */
    public function index()
    {
        $this->begin(array('GET', 'POST'));

        if (isPOST()) {
            return $this->respond($this->doSaveOwn($this->input()));
        }
        return $this->respond($this->doReadOwn());
    }

    /**
     * GET/POST /api/alerts/settings - global config + master switches (settings.manage).
     */
    public function settings()
    {
        $this->begin();

        if (isPOST()) {
            $data = $this->doSaveGlobal($this->input());
            Log::event('settings', Auth::id(), 'Updated global alert settings', USER_IP);
            return $this->respond($data);
        }
        return $this->respond($this->doReadGlobal());
    }

    /**
     * POST /api/alerts/test - send a sample alert.
     */
    public function test()
    {
        $this->begin('POST');

        return $this->respond($this->doTest());
    }

    /**
     * POST /api/alerts/chatid - discover the Telegram chat id for a bot token.
     */
    public function chatid()
    {
        $this->begin('POST');

        return $this->respond($this->doChatId($this->input()));
    }

    /**
     * The actor's own destinations.
     */
    public function doReadOwn()
    {
        $own = $this->readFor(Auth::id());

        $own['mail']['method_enabled'] = Setting::enabled('alert-mail');
        $own['telegram']['method_enabled'] = Setting::enabled('alert-telegram');
        $own['slack']['method_enabled'] = Setting::enabled('alert-slack');
        $own['discord']['method_enabled'] = Setting::enabled('alert-discord');
        return $own;
    }

    /**
     * Validate and persist the actor's own destinations; echo the new state.
     *
     * @param array $body
     */
    public function doSaveOwn(array $body)
    {
        $this->validateAndStore(Auth::id(), $body);
        return $this->readFor(Auth::id());
    }

    /**
     * Global destinations + master switches (settings.manage).
     */
    public function doReadGlobal()
    {
        $this->cap('settings.manage');
        return $this->readGlobal();
    }

    /**
     * Validate and persist global destinations, method master switches.
     *
     * @param array $body
     */
    public function doSaveGlobal(array $body)
    {
        $this->cap('settings.manage');

        $this->validateAndStore(0, $body);

        $switches = array(
            'alert-mail'     => 'mail',
            'alert-telegram' => 'telegram',
            'alert-slack'    => 'slack',
            'alert-discord'  => 'discord',
            'alert-callback' => 'callback',
        );
        foreach ($switches as $setting => $key) {
            if (isset($body[$key]) && is_array($body[$key]) && array_key_exists('method_enabled', $body[$key])) {
                Setting::put($setting, $this->asBool($body[$key]['method_enabled']) ? '1' : '0');
            }
        }

        if (isset($body['callback']) && is_array($body['callback']) && array_key_exists('url', $body['callback'])) {
            $url = trim((string) $body['callback']['url']);
            if ($url !== '' && !preg_match('#^[a-z][a-z0-9+.\-]*://#i', $url)) {
                $url = 'https://' . $url;
            }
            Setting::put('callback-url', $url);
        }

        return $this->readGlobal();
    }

    /**
     * Send a sample alert to the actor's own enabled destinations only, skipping methods whose master switch is off.
     *
     * @return array
     */
    public function doTest()
    {
        $sent = array();
        $failed = array();
        $report = Alert::sampleReport();
        $methods = array(
            Alert::METHOD_MAIL     => 'alert-mail',
            Alert::METHOD_TELEGRAM => 'alert-telegram',
            Alert::METHOD_SLACK    => 'alert-slack',
            Alert::METHOD_DISCORD  => 'alert-discord',
        );

        foreach ($methods as $methodId => $masterSetting) {
            if (!Setting::enabled($masterSetting)) {
                continue;
            }
            foreach (AlertConfig::ownRecipientsFor($methodId, Auth::id()) as $row) {
                if (Alert::sendOne($methodId, $row, $report)) {
                    $sent[] = AlertConfig::methodName($methodId);
                } else {
                    $failed[] = AlertConfig::methodName($methodId);
                }
            }
        }

        $sent = array_values(array_unique($sent));
        $failed = array_values(array_unique($failed));
        if (empty($sent) && empty($failed)) {
            $message = 'No enabled destinations to test. Enable a method and configure a destination first.';
        } elseif (!empty($failed)) {
            $message = 'Test alert failed for: ' . implode(', ', $failed) . '.';
        } else {
            $message = 'Test alert dispatched to your enabled destinations.';
        }

        return array(
            'sent'    => $sent,
            'failed'  => $failed,
            'message' => $message,
        );
    }

    /**
     * Discover the Telegram chat id for a bot token via getUpdates.
     *
     * @param array $body
     * @throws HttpException
     * @return array
     */
    public function doChatId(array $body)
    {
        $token = isset($body['token']) ? trim((string) $body['token']) : '';
        if ($token === '' || !preg_match(self::TELEGRAM_TOKEN_RE, $token)) {
            throw new HttpException(422, 'This does not look like a valid Telegram bot token');
        }

        $raw = $this->fetchTelegramUpdates($token);
        $results = json_decode((string) $raw, true);

        if (!is_array($results) || !isset($results['ok']) || $results['ok'] !== true) {
            throw new HttpException(502, 'Something went wrong, your bot token is probably invalid');
        }

        $chatId = $this->extractChatId($results);
        if ($chatId === null) {
            throw new HttpException(404, 'The bot token seems valid, but no chat can be found. Start a chat with your bot by sending /start');
        }

        return array('chatid' => (string) $chatId);
    }

    /**
     * Pull the chat id out of the most recent getUpdates result.
     *
     * @param array $results
     * @return string|null
     */
    private function extractChatId(array $results)
    {
        if (empty($results['result']) || !is_array($results['result'])) {
            return null;
        }
        $last = end($results['result']);
        if (is_array($last) && isset($last['message']['chat']['id'])) {
            return $last['message']['chat']['id'];
        }
        return null;
    }

    /**
     * Fetch the Telegram getUpdates response for a token over HTTPS.
     *
     * @param string $token
     * @return bool|string
     */
    private function fetchTelegramUpdates($token)
    {
        if (self::$telegramTransport !== null) {
            return (string) call_user_func(self::$telegramTransport, $token);
        }

        $ch = curl_init('https://api.telegram.org/bot' . $token . '/getUpdates');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'ezXSS');
        $raw = curl_exec($ch);
        curl_close($ch);

        return ($raw === false) ? '' : (string) $raw;
    }

    /**
     * Read a user's destinations (also used for the global rows).
     *
     * @param int $userId
     */
    private function readFor($userId)
    {
        return array(
            'mail' => array(
                'enabled' => (bool) AlertConfig::getField($userId, Alert::METHOD_MAIL, 'enabled'),
                'email'   => (string) AlertConfig::getField($userId, Alert::METHOD_MAIL, 'value1'),
            ),
            'telegram' => array(
                'enabled' => (bool) AlertConfig::getField($userId, Alert::METHOD_TELEGRAM, 'enabled'),
                'token'   => (string) AlertConfig::getField($userId, Alert::METHOD_TELEGRAM, 'value1'),
                'chatid'  => (string) AlertConfig::getField($userId, Alert::METHOD_TELEGRAM, 'value2'),
            ),
            'slack' => array(
                'enabled' => (bool) AlertConfig::getField($userId, Alert::METHOD_SLACK, 'enabled'),
                'webhook' => (string) AlertConfig::getField($userId, Alert::METHOD_SLACK, 'value1'),
            ),
            'discord' => array(
                'enabled' => (bool) AlertConfig::getField($userId, Alert::METHOD_DISCORD, 'enabled'),
                'webhook' => (string) AlertConfig::getField($userId, Alert::METHOD_DISCORD, 'value1'),
            ),
        );
    }

    /**
     * The global destinations, method master switches and callback config.
     */
    private function readGlobal()
    {
        $global = $this->readFor(0);
        $global['mail']['method_enabled'] = Setting::enabled('alert-mail');
        $global['telegram']['method_enabled'] = Setting::enabled('alert-telegram');
        $global['slack']['method_enabled'] = Setting::enabled('alert-slack');
        $global['discord']['method_enabled'] = Setting::enabled('alert-discord');
        $global['callback'] = array(
            'method_enabled' => Setting::enabled('alert-callback'),
            'url'            => (string) Setting::get('callback-url', ''),
        );
        return $global;
    }

    /**
     * Validate and upsert alert rows for a user (0 = global). Only sections present in the body are written.
     *
     * @param int $userId
     * @param array $body
     * @throws HttpException
     */
    private function validateAndStore($userId, array $body)
    {

        if (isset($body['mail']) && is_array($body['mail'])) {
            $m = $body['mail'];
            $email = isset($m['email']) ? trim((string) $m['email']) : '';
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new HttpException(422, 'This does not look like a valid email address');
            }
            AlertConfig::setRow($userId, Alert::METHOD_MAIL, !empty($m['enabled']), $email);
        }

        if (isset($body['telegram']) && is_array($body['telegram'])) {
            $t = $body['telegram'];
            $token = isset($t['token']) ? trim((string) $t['token']) : '';
            $chatid = isset($t['chatid']) ? trim((string) $t['chatid']) : '';
            if ($token !== '' || $chatid !== '') {
                if (!preg_match(self::TELEGRAM_TOKEN_RE, $token)) {
                    throw new HttpException(422, 'This does not look like a valid Telegram bot token');
                }
                if (!preg_match(self::TELEGRAM_CHATID_RE, $chatid)) {
                    throw new HttpException(422, 'This does not look like a valid Telegram chat id');
                }
            }
            AlertConfig::setRow($userId, Alert::METHOD_TELEGRAM, !empty($t['enabled']), $token, $chatid);
        }

        if (isset($body['slack']) && is_array($body['slack'])) {
            $s = $body['slack'];
            $webhook = isset($s['webhook']) ? trim((string) $s['webhook']) : '';
            if ($webhook !== '' && !preg_match(self::SLACK_RE, $webhook)) {
                throw new HttpException(422, 'This does not look like a valid Slack webhook URL');
            }
            AlertConfig::setRow($userId, Alert::METHOD_SLACK, !empty($s['enabled']), $webhook);
        }

        if (isset($body['discord']) && is_array($body['discord'])) {
            $d = $body['discord'];
            $webhook = isset($d['webhook']) ? trim((string) $d['webhook']) : '';
            if ($webhook !== '' && !preg_match(self::DISCORD_RE, $webhook)) {
                throw new HttpException(422, 'This does not look like a valid Discord webhook URL');
            }
            AlertConfig::setRow($userId, Alert::METHOD_DISCORD, !empty($d['enabled']), $webhook);
        }
    }

}
