<?php
class CallbackController extends Controller
{
    /**
     * Web-reachable actions (the public routes resolve through PublicRouter).
     */
    public static $routable = array('handle', 'index', 'attach');

    const PLACEHOLDER = 'github.com/ssl/ezXSS';

    const LIMIT_IP        = 50;
    const LIMIT_URI       = 1000;
    const LIMIT_REFERER   = 1000;
    const LIMIT_ORIGIN    = 500;
    const LIMIT_PAYLOAD   = 255;
    const LIMIT_USERAGENT = 500;
    const LIMIT_CSP       = 8000;
    const LIMIT_SCREENSHOT = 8388608;

    const ATTACH_TTL    = 600;

    /**
     * Raw body echoed for the persist path: the next queued console command.
     */
    private $persistResponse = null;

    /**
     * HTTP entry point. Returns the placeholder body for a normal callback.
     */
    public function handle()
    {
        Security::corsOpen();
        $this->sendHeaders();

        if (!isPOST()) {
            echo self::PLACEHOLDER;
            exit;
        }

        try {
            $data = $this->readBody();
            if ($data !== null) {
                $this->process($data);
            }
        } catch (Throwable $e) {
            error_log('ezXSS callback: ingest failed: ' . $e->getMessage());
        }

        if ($this->persistResponse !== null) {
            echo $this->persistResponse;
            exit;
        }

        echo self::PLACEHOLDER;
        exit;
    }

    /**
     * Alias for the /api/callback route.
     */
    public function index()
    {
        $this->handle();
    }

    /**
     * /callback/attach - fill a heavy field (dom|screenshot) the payload withheld
     */
    public function attach()
    {
        Security::corsOpen();
        $this->sendHeaders();

        if (!isPOST()) {
            echo self::PLACEHOLDER;
            exit;
        }

        try {
            $data = $this->readBody();
            if ($data !== null) {
                $this->processAttach($data);
            }
        } catch (Throwable $e) {
            error_log('ezXSS callback/attach: failed: ' . $e->getMessage());
        }

        echo self::PLACEHOLDER;
        exit;
    }

    /**
     * Validate an attach request against its token and write the field.
     *
     * @param array $data
     */
    protected function processAttach($data)
    {
        $token = isset($data->at) ? (string) $data->at : '';
        $field = isset($data->field) ? (string) $data->field : '';
        if (!preg_match('/^[a-z0-9]{16,64}$/', $token) || ($field !== 'dom' && $field !== 'screenshot')) {
            return;
        }

        $stored = Cache::get(self::attachKey($token));
        if (!is_array($stored) || !isset($stored['report_id'], $stored['exp'])) {
            return;
        }
        if ((int) $stored['exp'] <= time()) {
            Cache::forget(self::attachKey($token));
            return;
        }

        $value = isset($data->value) ? (string) $data->value : '';
        if ($field === 'screenshot') {
            $tmp = (object) array('screenshot' => $value, 'uri' => '');
            $this->prepareScreenshot($tmp);
            $value = isset($tmp->screenshotData) ? $tmp->screenshotData : '';
        }

        Report::attachData((int) $stored['report_id'], $field, $value);
    }

    /**
     * Read and normalize the request body into an object.
     *
     * @return array|null
     */
    public function readBody()
    {
        $input = self::decodeBodyEncoding((string) file_get_contents('php://input'));
        $input = (string) preg_replace('/^\xEF\xBB\xBF/', '', $input);
        $data = json_decode($input, false);
        if (!is_object($data)) {
            $data = json_decode(self::fixLoneSurrogates($input), false);
        }

        if (empty($data) || !is_object($data)) {
            if (!empty($input) && strpos($input, '=') !== false && strpos($input, '&') !== false) {
                parse_str($input, $formData);
                $data = (object) $formData;
            } else {
                $data = (object) $_POST;
            }
            if (empty($data) || !is_object($data)) {
                return null;
            }
        }

        return $data;
    }

    /**
     * Undo a Content-Encoding on the request body. Only gzip is handled; anything else is passed through.
     *
     * @param string $input
     * @return string
     */
    private static function decodeBodyEncoding($input)
    {
        $encoding = isset($_SERVER['HTTP_CONTENT_ENCODING']) ? strtolower(trim($_SERVER['HTTP_CONTENT_ENCODING'])) : '';
        if ($encoding === 'gzip' && function_exists('gzdecode')) {
            $decoded = @gzdecode($input, 67108864);
            if ($decoded !== false) {
                return $decoded;
            }
        }
        return $input;
    }

    /**
     * Replace unpaired UTF-16 surrogate escapes (a lone \ud83d) with U+FFFD, so the JSON can be decoded.
     *
     * @param string $json
     * @return string
     */
    private static function fixLoneSurrogates($json)
    {
        return (string) preg_replace_callback(
            '/\\\\u[dD][89abAB][0-9a-fA-F]{2}(?:\\\\u[dD][c-fC-F][0-9a-fA-F]{2})?|\\\\u[dD][c-fC-F][0-9a-fA-F]{2}/',
            function ($m) {
                return strlen($m[0]) === 12 ? $m[0] : '\\uFFFD';
            },
            $json
        );
    }

    /**
     * Process a decoded callback object: derive fields, apply filters, dedupe.
     *
     * @param array $data
     */
    public function process($data)
    {
        $this->deriveFields($data);

        if ($data->payload === '') {
            return;
        }

        $payload = Payload::resolveByIdentifier($data->payload);
        $deny = array();
        $allow = array();
        $filterOwner = ($payload && isset($payload['user_id'])) ? User::find((int) $payload['user_id']) : null;
        if ($payload && (string) Setting::get('allow_filters', '1') !== '0'
            && $filterOwner && Permissions::canBehaviour($filterOwner, 'filters')) {
            foreach (Payload::filters((int) $payload['id'], 'deny') as $f) {
                $deny[] = $f['domain'];
            }
            foreach (Payload::filters((int) $payload['id'], 'allow') as $f) {
                $allow[] = $f['domain'];
            }
        }

        if ($this->matchesList($data->origin, $deny)) {
            return;
        }

        if (!empty($allow) && !$this->matchesList($data->origin, $allow)) {
            return;
        }

        if (isset($data->method) && $data->method === 'persist') {
            $this->persistResponse = '';
            try {
                $this->persistResponse = (string) Session::handleCallback($data);
            } catch (Throwable $e) {
                error_log('ezXSS callback/persist: failed: ' . $e->getMessage());
            }
            return;
        }

        $data = Hook::filter('report.before_store', $data);
        if (!is_object($data)) {
            return;
        }

        $this->storeAndAlert($data);

        Hook::fire('report.stored', $data);
    }

    /**
     * Duplicate-aware store + alert, applying the filter-save / filter-alert.
     *
     * @param array $data
     */
    private function storeAndAlert($data)
    {
        $filterSave = (string) Setting::get('filter-save', '1');
        $filterAlert = (string) Setting::get('filter-alert', '1');

        $doubleReport = false;
        if ($filterSave === '0' || $filterAlert === '0') {
            $searchId = Report::searchForDuplicates(
                array(
                    'cookies'   => isset($data->cookies) ? $data->cookies : '',
                    'origin'    => $data->origin,
                    'referer'   => $data->referer,
                    'uri'       => $data->uri,
                    'useragent' => $data->useragent,
                    'ip'        => $data->ip,
                    'payload'   => $data->payload,
                    'dom'       => isset($data->dom) ? $data->dom : '',
                ),
                Report::duplicateColumns()
            );
            if ($searchId !== false) {
                if ($filterSave === '0' && $filterAlert === '0') {
                    return;
                }
                $doubleReport = $searchId;
            }
        }

        $this->prepareScreenshot($data);

        $reportArray = null;

        if (($doubleReport !== false && $filterSave === '1') || $doubleReport === false) {
            $reportId = Report::add(array(
                'payload'        => $data->payload,
                'uri'            => $data->uri,
                'ip'             => $data->ip,
                'referer'        => $data->referer,
                'useragent'      => $data->useragent,
                'cookies'        => isset($data->cookies) ? $data->cookies : '',
                'origin'         => $data->origin,
                'dom'            => isset($data->dom) ? $data->dom : '',
                'screenshot'     => isset($data->screenshotData) ? $data->screenshotData : '',
                'localstorage'   => json_encode(isset($data->localstorage) ? $data->localstorage : ''),
                'sessionstorage' => json_encode(isset($data->sessionstorage) ? $data->sessionstorage : ''),
                'extra'          => $data->extra,
                'meta'           => $this->buildMeta($data),
                'created_at'     => time(),
            ));
            $data->id = $reportId;
            $data->domain = defined('HOST') ? HOST : '';

            $nonce = $this->beaconNonce($data);
            if ($nonce !== '') {
                try {
                    Report::linkBeaconNonce($nonce, $reportId);
                } catch (Throwable $e) {
                }
            }

            $reportArray = Report::getFull($reportId);

            $this->mintAttachToken($data, $reportId);
        } elseif ($doubleReport !== false && $filterAlert === '1') {

            $reportArray = Report::getFull($doubleReport);
            $data->id = $doubleReport;
        }

        $data->time = time();

        $isCollected = strpos((string) $data->referer, 'Collected page via ') !== false;

        if ((($doubleReport !== false && $filterAlert === '1') || $doubleReport === false) && !$isCollected) {
            try {
                Alert::dispatch($this->alertPayload($data, $reportArray));
            } catch (Throwable $e) {
            }
        }
    }

    /**
     * Build the array handed to the Alert seam: the stored report merged.
     *
     * @param array $data
     * @param array $reportArray
     */
    private function alertPayload($data, $reportArray)
    {
        $base = is_array($reportArray) ? $reportArray : array();
        $base['id'] = isset($data->id) ? $data->id : (isset($base['id']) ? $base['id'] : 0);
        $base['payload'] = $data->payload;
        $base['time'] = isset($data->time) ? $data->time : time();
        if (isset($data->screenshotBase)) {
            $base['screenshotBase'] = $data->screenshotBase;
        }
        if (isset($data->domain)) {
            $base['domain'] = $data->domain;
        }
        return $base;
    }

    /**
     * Register the payload-chosen attach token -> report id (short TTL)
     *
     * @param array $data
     * @param int $reportId
     */
    private function mintAttachToken($data, $reportId)
    {
        $token = isset($data->ez_at) ? (string) $data->ez_at : '';
        if (!preg_match('/^[a-z0-9]{16,64}$/', $token)) {
            return;
        }
        try {
            Cache::set(self::attachKey($token), array(
                'report_id' => (int) $reportId,
                'exp'       => time() + self::ATTACH_TTL,
            ));
        } catch (Throwable $e) {
        }
    }

    /**
     * Cache key for an attach token.
     *
     * @param string $token
     * @return string
     */
    private static function attachKey($token)
    {
        return 'attach:' . $token;
    }

    /**
     * Derive + truncate the report fields from the posted data and request.
     *
     * @param array $data
     */
    public function deriveFields($data)
    {
        $server = $_SERVER;

        $data->screenshot = isset($data->screenshot) ? $data->screenshot : '';

        $ip = isset($data->ip) && $data->ip !== ''
            ? $data->ip
            : (isset($server['REMOTE_ADDR']) ? $server['REMOTE_ADDR'] : '');
        $data->ip = utf8CleanCut($ip, self::LIMIT_IP);

        if (!isset($data->uri) || $data->uri === '') {
            $data->uri = isset($server['HTTP_REFERER']) ? $server['HTTP_REFERER']
                : (isset($server['HTTP_ORIGIN']) ? $server['HTTP_ORIGIN'] : '');
        }

        $origin = isset($data->origin) ? $data->origin : (isset($server['HTTP_ORIGIN']) ? $server['HTTP_ORIGIN'] : '');
        $origin = str_replace(array('https://', 'http://'), '', (string) $origin);
        if ($origin === '' && !empty($data->uri)) {
            $parsed = parse_url((string) $data->uri);
            $origin = isset($parsed['host']) ? $parsed['host'] : '';
        }

        $data->uri = utf8CleanCut($data->uri, self::LIMIT_URI);
        $data->referer = utf8CleanCut($data->referer ?? '', self::LIMIT_REFERER);
        $data->origin = utf8CleanCut($origin, self::LIMIT_ORIGIN);
        $data->payload = utf8CleanCut($data->payload ?? '', self::LIMIT_PAYLOAD);

        $ua = isset($data->{'user-agent'}) ? $data->{'user-agent'}
            : (isset($server['HTTP_USER_AGENT']) ? $server['HTTP_USER_AGENT'] : '');
        $data->useragent = utf8CleanCut($ua, self::LIMIT_USERAGENT);

        $extra = isset($data->extra) ? $data->extra : '';
        if (is_array($extra) || is_object($extra)) {
            $extra = json_encode($extra);
        }
        $data->extra = (string) $extra;
    }

    /**
     * Capture request metadata into a JSON-ready array: Sec-Fetch-* headers.
     *
     * @param array $data
     * @return array
     */
    public function buildMeta($data)
    {
        $server = $_SERVER;
        $secFetch = secFetchHeaders();

        $client = array();
        $clientMap = array(
            'ua'       => 'HTTP_SEC_CH_UA',
            'platform' => 'HTTP_SEC_CH_UA_PLATFORM',
            'mobile'   => 'HTTP_SEC_CH_UA_MOBILE',
            'language' => 'HTTP_ACCEPT_LANGUAGE',
        );
        foreach ($clientMap as $key => $header) {
            if (isset($server[$header]) && $server[$header] !== '') {
                $client[$key] = substr((string) $server[$header], 0, 256);
            }
        }

        $csp = array('permitted' => array());
        $nonce = $this->beaconNonce($data);
        if ($nonce !== '') {
            $csp['nonce'] = $nonce;
        }

        if (isset($data->csp) && is_string($data->csp) && trim($data->csp) !== '') {
            $csp['policy'] = substr(trim($data->csp), 0, self::LIMIT_CSP);
        }

        if (isset($data->ez_probe) && $data->ez_probe !== '') {
            $csp['probe'] = true;
            $csp['probe_directive'] = (string) $data->ez_probe;
            $csp['permitted'] = array((string) $data->ez_probe);
            if (isset($data->ez_probe_channel) && $data->ez_probe_channel !== '') {
                $csp['probe_channel'] = (string) $data->ez_probe_channel;
            }
        }

        return array(
            'sec_fetch' => (object) $secFetch,
            'dom_size'  => strlen((string) (isset($data->dom) ? $data->dom : '')),
            'client'    => (object) $client,
            'csp'       => $csp,
        );
    }

    /**
     * Read + validate the per-pageload beacon nonce (ez_bid).
     *
     * @param array $data
     * @return bool
     */
    private function beaconNonce($data)
    {
        $nonce = isset($data->ez_bid) ? (string) $data->ez_bid : '';
        return preg_match('/^[a-z0-9]{1,64}$/', $nonce) ? $nonce : '';
    }

    /**
     * Decode the posted screenshot and set $data->screenshotData to either.
     *
     * @param array $data
     * @throws Exception
     */
    public function prepareScreenshot($data)
    {
        if (empty($data->screenshot)) {
            $data->screenshotData = '';
            return;
        }

        try {
            $raw = (string) $data->screenshot;
            if (strlen($raw) > (int) (self::LIMIT_SCREENSHOT * 1.4) + 64) {
                $data->screenshotData = '';
                return;
            }
            $decoded = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $raw), true);
            if ($decoded === false || $decoded === '') {
                throw new Exception('Invalid screenshot data posted to callback');
            }
            if (strlen($decoded) > self::LIMIT_SCREENSHOT) {
                $data->screenshotData = '';
                return;
            }

            $data->screenshotBase = base64_encode($decoded);

            if ((string) Setting::get('storescreenshot', '0') === '0') {

                $name = time() . md5((string) $data->uri . time() . bin2hex(random_bytes(16))) . bin2hex(random_bytes(5));
                $dir = FRAMEWORK_ROOT . '/assets/img';
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
                $fh = @fopen($dir . '/report-' . $name . '.png', 'w');
                if (!$fh) {
                    throw new Exception('Unable to save screenshots to server, check permissions');
                }
                fwrite($fh, $decoded);
                fclose($fh);
                $data->screenshotData = $name;
            } else {
                $data->screenshotData = $data->screenshotBase;
            }
        } catch (Throwable $e) {
            $data->screenshotData = '';
        }
    }

    /**
     * Whether $origin matches any domain in $list, exact or by wildcard.
     *
     * @param string $origin
     * @param array $list
     * @return bool
     */
    private function matchesList($origin, array $list)
    {
        foreach ($list as $domain) {
            $domain = (string) $domain;
            if ($origin !== '' && $origin === $domain) {
                return true;
            }
            if (strpos($domain, '*') !== false) {

                $pattern = str_replace('\\*', '.*', preg_quote($domain, '/'));
                if (@preg_match('/^' . $pattern . '$/', $origin)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Emit the callback response headers (CORS is handled by Security::corsOpen()).
     */
    private function sendHeaders()
    {
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }
}
