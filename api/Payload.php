<?php
class PayloadController extends Controller
{
    /**
     * Web-reachable actions (the public routes resolve through PublicRouter).
     */
    public static $routable = array('serve', 'index');

    /**
     * HTTP entry point. Resolves the payload for the current request, assembles.
     */
    public function serve()
    {
        Security::corsOpen();

        if (URI !== '' && substr(URI, -5) === '*test') {
            $this->serveTester();
            exit;
        }

        if (substr(URI, -2, 1) === '*') {
            $map = self::nojsMap();
            $njCode = substr(URI, -1);
            if (isset($map[$njCode])) {
                $this->serveNojsProbe($njCode);
                exit;
            }
        }

        $this->sendHeaders();

        $vars = $this->vars();

        $first = $this->firstSegment();
        if ($first !== '' && !preg_match('/[^A-Za-z0-9._-]/', $first)) {
            $file = FRAMEWORK_ROOT . '/payload/custom/' . $first . '.js';
            if (is_file($file)) {
                try {
                    echo PayloadBuilder::custom($first, $vars);
                    exit;
                } catch (Throwable $e) {

                }
            }
        }

        echo $this->buildForRequest($vars);
        exit;
    }

    /**
     * Alias for the /api/payload route (serves the catch-all assembled JS).
     */
    public function index()
    {
        $this->serve();
    }

    /**
     * Serve the payload-tester HTML page (reached via /{payload}*test).
     */
    private function serveTester()
    {
        $file = FRAMEWORK_ROOT . '/assets/payload-tester.html';
        if (!is_file($file)) {
            http_response_code(404);
            return;
        }
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');

            header("Content-Security-Policy: default-src * 'unsafe-inline' 'unsafe-eval' data: blob:; worker-src 'none'; frame-ancestors 'none'");
        }
        readfile($file);
    }

    /**
     * No-JS CSP probe suffix code (/{payload}*<code>) => [tested directive, resource channel].
     *
     * @return array
     */
    private static function nojsMap()
    {
        return array(
            'i' => array('img-src', 'img'),
            's' => array('style-src', 'css'),
            'm' => array('media-src', 'media'),
            'f' => array('font-src', 'font'),
            'r' => array('frame-src', 'img'),
            'o' => array('object-src', 'img'),
            'n' => array('manifest-src', 'manifest'),
            'p' => array('prefetch', 'img'),
            'c' => array('connect-src', 'connect'),
            'v' => array('navigation', 'img'),
            'l' => array('preload', 'preload'),
            'k' => array('form-action', 'form'),
        );
    }

    /**
     * Serve a no-JS CSP probe (/{payload}*<code>): record the probe hit, then emit the channel response.
     *
     * @param string $code
     */
    private function serveNojsProbe($code)
    {
        $map = self::nojsMap();
        $entry = isset($map[$code]) ? $map[$code] : $map['i'];

        $identifier = HOST . (URI === '' ? '' : '/' . URI);

        try {
            require_once API_PATH . '/Callback.php';
            $data = new stdClass();
            $data->payload = $identifier;
            $data->extra = '';
            $data->ez_probe = $entry[0];
            $data->ez_probe_channel = $entry[1];
            $cb = new CallbackController();
            $cb->process($data);
        } catch (Throwable $e) {
        }

        require_once API_PATH . '/Beacon.php';
        BeaconController::emitChannel($entry[1]);
    }

    /**
     * Build the assembled payload JS for the current request's HOST/URI.
     *
     * @param array $vars
     */
    public function buildForRequest($vars = null)
    {
        if ($vars === null) {
            $vars = $this->vars();
        }
        $identifier = HOST . (URI === '' ? '' : '/' . URI);
        return $this->buildForIdentifier($identifier, $vars);
    }

    /**
     * Resolve a payload for an identifier and assemble its JS, or the fallback payload when none matches.
     *
     * @param string $identifier
     * @param array $vars
     */
    public function buildForIdentifier($identifier, array $vars)
    {
        $row = Payload::resolveByIdentifier($identifier);

        if (!$row) {

            return PayloadBuilder::assemble($this->fallbackConfig($identifier, $vars));
        }

        return PayloadBuilder::assemble($this->gatherConfig($row, $vars));
    }

    /**
     * The catch-all config: a payload built purely from the admin's "Payload.
     *
     * @param string $identifier
     * @param array $vars
     */
    public function fallbackConfig($identifier, array $vars)
    {
        $collect = array();
        foreach (Payload::COLLECT_OPTIONS as $opt) {
            $collect[$opt] = Setting::enabled('collect_' . $opt);
        }

        return array(
            'identifier' => $this->identifierFromRaw($identifier),
            'version'    => $vars['version'],
            'domain'     => $vars['domain'],
            'protocol'   => $vars['protocol'],
            'collect'    => $collect,
            'pages'      => array(),
            'spider'     => '0',
            'customjs'   => '',
            'customjs2'  => '',
            'globaljs'   => (string) Setting::get('customjs', ''),
            'globaljs2'  => (string) Setting::get('customjs2', ''),
            'persistent' => false,
            'beacon'     => Setting::enabled('beacon_fallback'),
            'extensions' => $this->mergedExtensions(array()),
            'max_body_kb' => (int) Setting::get('callback_max_body_kb', '51200'),
        );
    }

    /**
     * Gather the full assemble() config for a resolved payload row, applying.
     *
     * @param array $row
     * @param array $vars
     * @return array
     */
    public function gatherConfig(array $row, array $vars)
    {
        $id = (int) $row['id'];

        $collect = Payload::collect($id);

        $owner = isset($row['user_id']) ? User::find((int) $row['user_id']) : null;
        $ownerMay = function ($key) use ($owner) {
            return $owner ? Permissions::canBehaviour($owner, $key) : false;
        };

        $pages = array();
        if ((string) Setting::get('allow_pages', '1') !== '0' && $ownerMay('pages')) {
            foreach (Payload::pages($id) as $p) {
                if (isset($p['path']) && $p['path'] !== '') {
                    $pages[] = $p['path'];
                }
            }
        }

        $allowCustomjs = (string) Setting::get('allow_customjs', '1') !== '0' && $ownerMay('customjs');

        $spider = (string) (int) (isset($row['spider']) ? $row['spider'] : 0);
        if ($spider !== '1' && $spider !== '2') {
            $spider = '0';
        }
        if (!Setting::enabled('spider') || !$ownerMay('spider')) {
            $spider = '0';
        }

        $persistent = ((int) (isset($row['persistent']) ? $row['persistent'] : 0) === 1)
            && Setting::enabled('persistent') && $ownerMay('persistent');

        $beacon = ((int) (isset($row['beacon']) ? $row['beacon'] : 0) === 1)
            && Setting::enabled('beacon_fallback') && $ownerMay('beacon');

        return array(
            'identifier' => (string) $row['identifier'],
            'version'    => $vars['version'],
            'domain'     => $vars['domain'],
            'protocol'   => $vars['protocol'],
            'collect'    => $collect,
            'pages'      => $pages,
            'spider'     => $spider,
            'customjs'   => $allowCustomjs && isset($row['customjs']) ? (string) $row['customjs'] : '',
            'customjs2'  => $allowCustomjs && isset($row['customjs2']) ? (string) $row['customjs2'] : '',
            'globaljs'   => (string) Setting::get('customjs', ''),
            'globaljs2'  => (string) Setting::get('customjs2', ''),
            'persistent' => $persistent,
            'beacon'     => $beacon,
            'extensions' => $this->mergedExtensions($row, $owner),
            'max_body_kb' => (int) Setting::get('callback_max_body_kb', '51200'),
        );
    }

    /**
     * Merge global + per-payload extension ids, load each enabled extension.
     *
     * @param array $row
     * @param array $owner
     */
    public function mergedExtensions(array $row, $owner = false)
    {
        $global = $this->csvIds(Setting::get('extensions', ''));

        if ($owner === false) {
            $owner = isset($row['user_id']) ? User::find((int) $row['user_id']) : null;
        }
        $perPayload = ((string) Setting::get('allow_extensions', '1') !== '0'
            && $owner && Permissions::canBehaviour($owner, 'extensions'))
            ? $this->csvIds(isset($row['extensions']) ? $row['extensions'] : '')
            : array();
        $ids = array_unique(array_merge($global, $perPayload));

        $code = '';
        foreach ($ids as $id) {
            try {
                $extension = Extension::find((int) $id);
                if (!$extension || (int) $extension['enabled'] !== 1) {
                    continue;
                }
                $extCode = isset($extension['code']) ? (string) $extension['code'] : '';

                $lines = preg_split("/\r\n|\n|\r/", $extCode);
                $filtered = array();
                foreach ($lines as $line) {
                    if (strpos(ltrim($line), '//') === 0) {
                        continue;
                    }
                    $filtered[] = $line;
                }
                $code .= implode("\n", $filtered) . "\n";
            } catch (Throwable $e) {
                continue;
            }
        }
        return $code;
    }

    /**
     * version/domain/protocol for the {{...}} placeholders
     */
    public function vars()
    {
        $version = (string) Setting::get('version', '5.0');

        $domain = trim((string) Config::get('DOMAIN', ''));
        if ($domain === '' || $domain === 'localhost') {
            if (defined('HOST') && HOST !== '') {
                $domain = HOST;
            }
        }

        $protocol = $this->isHttps() ? 'https' : 'http';

        return array(
            'version'  => $version,
            'domain'   => $domain,
            'protocol' => $protocol,
        );
    }

    /**
     * Whether the current request is HTTPS.
     *
     * @return bool
     */
    private function isHttps()
    {
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            return true;
        }
        return false;
    }

    /**
     * The first URI path segment (lowercased by init.php), or '' at the root.
     *
     * @return string
     */
    private function firstSegment()
    {
        if (!defined('URI') || URI === '') {
            return '';
        }
        $parts = explode('/', URI);
        return $parts[0];
    }

    /**
     * Normalize a raw identifier/URL to the domain/path form.
     *
     * @param string $raw
     */
    private function identifierFromRaw($raw)
    {
        $raw = strtolower(trim((string) $raw));
        $raw = preg_replace('#^[a-z][a-z0-9+.\-]*://#', '', $raw);
        $raw = trim($raw, '/');
        if ($raw === '') {
            return (defined('HOST') ? HOST : '');
        }
        $parts = explode('/', $raw);
        return isset($parts[1]) && $parts[1] !== '' ? $parts[0] . '/' . $parts[1] : $parts[0];
    }

    /**
     * Parse a comma-separated list of numeric ids.
     *
     * @param string $csv
     * @return array
     */
    private function csvIds($csv)
    {
        $csv = (string) $csv;
        if ($csv === '') {
            return array();
        }
        $ids = array();
        foreach (explode(',', $csv) as $part) {
            $part = trim($part);
            if ($part !== '' && ctype_digit($part)) {
                $ids[] = (int) $part;
            }
        }
        return $ids;
    }

    /**
     * Emit the payload response headers (application/javascript, no-cache).
     */
    private function sendHeaders()
    {
        if (!headers_sent()) {
            header('Content-Type: application/javascript; charset=utf-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }
}
