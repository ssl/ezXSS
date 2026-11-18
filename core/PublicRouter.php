<?php
class PublicRouter
{
    /**
     * Dispatch the current public request.
     */
    public static function dispatch()
    {
        $first = self::firstSegment();

        $reserved = self::reserved();
        if ($first !== '' && isset($reserved[$first])) {
            call_user_func($reserved[$first]);
            return;
        }

        require_once API_PATH . '/Payload.php';
        $controller = new PayloadController();
        $controller->serve();
    }

    /**
     * Reserved first-segment handlers.
     *
     * @return array
     */
    private static function reserved()
    {
        return array(
            'callback' => array(__CLASS__, 'callback'),

            's'        => array(__CLASS__, 'share'),

            'beacon'   => array(__CLASS__, 'beacon'),

            'manage'   => array(__CLASS__, 'manage'),
        );
    }

    /**
     * The deferred panel scripts, in load order. boot.js is NOT here: it.
     */
    private static $bundleJs = array(
        'core.js', 'api.js', 'ui.js', 'csp.js', 'autosave.js', 'router.js',
        'screens/alerts.js', 'screens/auth.js', 'screens/account.js',
        'screens/dashboard.js', 'screens/reports.js', 'screens/payloads.js',
        'screens/sessions.js', 'screens/users.js', 'screens/search.js',
        'screens/settings.js', 'screens/docs.js', 'notepad.js', 'app.js',
    );

    /**
     * GET /manage/bundle.js - concatenate the deferred panel scripts into one.
     */
    public static function bundle()
    {
        $dir = FRAMEWORK_ROOT . '/assets/js/';
        $files = array();
        $sig = array();
        foreach (self::$bundleJs as $rel) {
            $path = $dir . $rel;
            if (is_file($path)) {
                $files[] = $path;
                $sig[] = $rel . ':' . filemtime($path) . ':' . filesize($path);
            }
        }

        $etag = '"' . md5(implode('|', $sig)) . '"';
        $lastMod = $files ? max(array_map('filemtime', $files)) : time();

        $noneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '';
        $modSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : false;
        if ($noneMatch === $etag || ($modSince !== false && $modSince >= $lastMod)) {
            if (!headers_sent()) {
                http_response_code(304);
                header('ETag: ' . $etag);
                header('Cache-Control: no-cache');
            }
            return;
        }

        $out = '';
        foreach ($files as $path) {
            $out .= file_get_contents($path) . "\n";
        }

        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: application/javascript; charset=utf-8');
            header('Cache-Control: no-cache');
            header('ETag: ' . $etag);
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastMod) . ' GMT');
            header('X-Content-Type-Options: nosniff');
        }
        echo $out;
    }

    /**
     * The /callback endpoint - the report-ingest handler (public, no auth, open.
     */
    public static function callback()
    {
        require_once API_PATH . '/Callback.php';
        $controller = new CallbackController();
        if (self::secondSegment() === 'attach') {
            $controller->attach();
            return;
        }
        $controller->handle();
    }

    /**
     * The /s/{slug} endpoint - the public on-request report share (no auth, open
     */
    public static function share()
    {
        $second = self::secondSegment();
        $isJson = ($second !== '' && substr($second, -5) === '.json');
        $isPng  = ($second !== '' && substr($second, -4) === '.png');
        $slug   = $isJson ? substr($second, 0, -5) : ($isPng ? substr($second, 0, -4) : $second);

        if (!ReportShare::isValidSlug($slug)) {
            require_once API_PATH . '/Payload.php';
            $controller = new PayloadController();
            $controller->serve();
            return;
        }

        if ($isPng) {
            self::shareScreenshot($slug);
            return;
        }

        if ($isJson) {
            require_once API_PATH . '/Shared.php';
            $controller = new SharedController();
            $controller->view();
            return;
        }

        $html = View::page('shared', array(
            'title'   => 'Shared report &middot; ezXSS',
            'scripts' => array('shared.js'),
        ));
        if ($html === null) {
            http_response_code(404);
            return;
        }
        self::panelShellHeaders(200);
        echo $html;
    }

    /**
     * Proxy a report screenshot through the share token.
     *
     * @param string $slug
     */
    private static function shareScreenshot($slug)
    {
        $share = ReportShare::findBySlug($slug);
        if (!$share || ReportShare::isExpired($share)) {
            http_response_code(404);
            return;
        }

        $allowed = ReportShare::columnsOf($share);
        if (!in_array('screenshot', $allowed, true)) {
            http_response_code(403);
            return;
        }

        $data = Report::query()
            ->from(Report::TABLE_DATA)
            ->select('screenshot')
            ->where('report_id', (int) $share['report_id'])
            ->first();

        if (!$data || (string) $data['screenshot'] === '') {
            http_response_code(404);
            return;
        }

        $value = (string) $data['screenshot'];

        if (Compression::isStoredScreenshotFile($value)) {
            $file = FRAMEWORK_ROOT . '/assets/img/report-' . $value . '.png';
            if (!is_file($file)) {
                http_response_code(404);
                return;
            }
            header('Content-Type: image/png');
            header('Cache-Control: private, max-age=3600');
            header('X-Content-Type-Options: nosniff');
            readfile($file);
            return;
        }

        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            http_response_code(404);
            return;
        }
        header('Content-Type: image/png');
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        echo $decoded;
    }

    /**
     * The /beacon/{type}/{nonce} endpoints - public beacon sinks that return
     */
    public static function beacon()
    {
        require_once API_PATH . '/Beacon.php';
        $controller = new BeaconController();
        $controller->handle();
    }

    /**
     * The /manage[/...] endpoint - the admin panel SPA shell (no auth). The sa
     */
    public static function manage()
    {
        if (!self::panelHostAllowed()) {

            if (self::secondSegment() === 'bundle.js') {
                if (!headers_sent()) {
                    http_response_code(403);
                    header('Content-Type: application/javascript; charset=utf-8');
                    header('Cache-Control: no-store');
                }
                echo '/* ezXSS: the admin panel is not served on this domain. */';
                return;
            }
            self::blockedPage();
            return;
        }

        $second = self::secondSegment();
        if ($second === 'register') {
            self::register();
            return;
        }
        if ($second === 'bundle.js') {
            self::bundle();
            return;
        }

        $signup = false;
        try { $signup = Setting::enabled('signup_enabled'); } catch (Throwable $e) {}

        $html = View::page('index', array(
            'title'   => 'ezXSS 5.0',
            'scripts' => array('bundle'),
            'tokens'  => array('{{authSignupHidden}}' => $signup ? '' : 'hidden'),
        ));
        if ($html === null) {
            http_response_code(404);
            return;
        }

        self::panelShellHeaders(200);
        echo $html;
    }

    /**
     * The /manage/register screen - the public self-registration page, ser
     */
    public static function register()
    {
        if (!Setting::enabled('signup_enabled')) {
            http_response_code(404);
            return;
        }

        $codeRequired = false;
        try { $codeRequired = ((string) Setting::get('signup_mode', 'public') === 'code'); } catch (Throwable $e) {}

        $html = View::page('register', array(
            'title'   => 'Sign up &middot; ezXSS 5.0',
            'scripts' => array('register.js'),
            'tokens'  => array('{{codeFieldHidden}}' => $codeRequired ? '' : 'hidden'),
        ));
        if ($html === null) {
            http_response_code(404);
            return;
        }

        self::panelShellHeaders(200);
        echo $html;
    }

    /**
     * Emit the panel-scoped response headers (content type + cache + CSP) shared.
     *
     * @param int $
     */
    public static function panelShellHeaders($status = 200)
    {
        if (headers_sent()) {
            return;
        }
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');

        header('Cache-Control: no-cache');
        header(
            "Content-Security-Policy: default-src 'none'; "
            . "script-src 'self'; "
            . "style-src 'self' 'unsafe-inline'; "
            . "img-src 'self' data: https://avatars.githubusercontent.com; "
            . "font-src 'self'; "
            . "connect-src 'self'; "
            . "base-uri 'none'; "
            . "form-action 'self'; "
            . "frame-ancestors 'none'"
        );
    }

    /**
     * Whether the current HOST may serve the panel. Loopback always may.
     *
     * @param string $host
     * @return bool
     */
    public static function panelHostAllowed($host = null)
    {
        if ($host === null) {
            $host = defined('HOST') ? HOST : '';
        }
        $host = strtolower((string) $host);
        $bare = preg_replace('/:\d+$/', '', $host);

        if (in_array($bare, array('localhost', '127.0.0.1', '::1', '[::1]'), true)) {
            return true;
        }

        $hosts = Security::realPanelHosts();
        if (empty($hosts)) {
            return true;
        }

        return in_array($host, $hosts, true) || in_array($bare, $hosts, true);
    }

    /**
     * The 403 shown when the panel is requested on a host that i
     */
    public static function blockedPage()
    {
        $html = View::page('blocked', array(
            'title' => 'Panel unavailable &middot; ezXSS',
        ));
        if ($html === null) {
            http_response_code(403);
            return;
        }
        self::panelShellHeaders(403);
        echo $html;
    }

    /**
     * The first URI path segment (lowercased by init.php), or '' at the root.
     *
     * @return string
     */
    private static function firstSegment()
    {
        if (!defined('URI') || URI === '') {
            return '';
        }
        $parts = explode('/', URI);
        return $parts[0];
    }

    /**
     * The second URI path segment (lowercased by init.php), or '' when absent.
     *
     * @return string
     */
    private static function secondSegment()
    {
        if (!defined('URI') || URI === '') {
            return '';
        }
        $parts = explode('/', URI);
        return isset($parts[1]) ? $parts[1] : '';
    }
}
