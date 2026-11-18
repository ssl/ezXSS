<?php
class Security
{
    /**
     * Methods allowed on the admin/management API surface.
     */
    const CORS_ADMIN_METHODS = 'GET, POST, PUT, PATCH, DELETE, OPTIONS';

    /**
     * Apply baseline security headers and request guards.
     *
     * @throws HttpException
     */
    public static function bootstrap()
    {

        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: fullscreen=()');

        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");

        if (isset($_SERVER['REQUEST_METHOD']) &&
            !in_array($_SERVER['REQUEST_METHOD'], array('GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'), true)) {
            throw new HttpException(405, 'Method Not Allowed');
        }

        if (Config::get('IP_LOCK', false)) {
            $allowedIps = Config::get('ALLOWED_IPS', array());
            if (!empty($allowedIps) && !self::isIpAllowed(USER_IP, $allowedIps)) {
                throw new HttpException(503, 'Service Unavailable');
            }
        }
    }

    /**
     * Check whether an IP matches the allow list (exact or prefix match).
     *
     * @param string $clientIp
     * @param string $allowedIps
     * @return bool
     */
    private static function isIpAllowed($clientIp, $allowedIps)
    {
        foreach ($allowedIps as $allowedIp) {
            $allowedIp = trim($allowedIp);
            if ($allowedIp === '') {
                continue;
            }
            if ($clientIp === $allowedIp) {
                return true;
            }
            if (substr($allowedIp, -1) === '.' && strpos($clientIp, $allowedIp) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * DB-backed rate limiter using the `cache` table. Returns true when allowed.
     *
     * @param string $key
     * @param int $max
     * @param int $window
     */
    public static function rateLimit($key, $max = 60, $window = 60)
    {
        $bucket = 'ratelimit:' . $key . ':' . (int) floor(time() / $window);

        $count = Cache::increment($bucket);
        return $count <= $max;
    }

    /**
     * Clear the current window's counter for a rate-limit key.
     *
     * @param string $key
     * @param int $window
     */
    public static function clearRateLimit($key, $window = 60)
    {
        $bucket = 'ratelimit:' . $key . ':' . (int) floor(time() / $window);
        Cache::forget($bucket);
    }

    /**
     * Apply the admin-panel CORS policy and handle preflight.
     */
    public static function corsAdmin()
    {
        $origin = self::requestOrigin();
        $allowed = self::adminOrigins();

        if ($origin !== '' && in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            header('Access-Control-Allow-Headers: Authorization, Content-Type');
            header('Access-Control-Allow-Methods: ' . self::CORS_ADMIN_METHODS);
            header('Access-Control-Max-Age: 600');
        }

        self::corsPreflight();
    }

    /**
     * Apply the public (wildcard) CORS policy and handle preflight.
     */
    public static function corsOpen()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Max-Age: 600');

        self::corsPreflight();
    }

    /**
     * Short-circuit an OPTIONS preflight with 204 No Content.
     */
    private static function corsPreflight()
    {
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            if (!headers_sent()) {
                http_response_code(204);
            }
            exit;
        }
    }

    /**
     * The request Origin header (empty string when absent).
     *
     * @return string
     */
    private static function requestOrigin()
    {
        return isset($_SERVER['HTTP_ORIGIN']) ? trim($_SERVER['HTTP_ORIGIN']) : '';
    }

    /**
     * Loopback hosts that never count as a configured panel domain.
     *
     * @return array
     */
    private static function devHosts()
    {
        return array('localhost', '127.0.0.1', '::1', '[::1]');
    }

    /**
     * Every host configured to serve the panel: DOMAIN plus PANEL_DOMAINS.
     *
     * @return array
     */
    public static function panelHosts()
    {
        $hosts = array();

        $domain = strtolower(trim((string) Config::get('DOMAIN', 'localhost')));
        if ($domain !== '') {
            $hosts[] = $domain;
        }

        $extra = Config::get('PANEL_DOMAINS', '');
        $list = is_array($extra) ? $extra : explode(',', (string) $extra);
        foreach ($list as $h) {
            $h = strtolower(trim((string) $h));
            if ($h !== '') {
                $hosts[] = $h;
            }
        }

        return array_values(array_unique($hosts));
    }

    /**
     * The configured panel hosts minus loopback.
     *
     * @return array
     */
    public static function realPanelHosts()
    {
        $dev = self::devHosts();
        return array_values(array_filter(self::panelHosts(), function ($h) use ($dev) {
            return !in_array($h, $dev, true);
        }));
    }

    /**
     * Build the allow list of admin-panel origins from configuration.
     *
     * @return array
     */
    public static function adminOrigins()
    {
        $origins = array();

        foreach (self::panelHosts() as $host) {
            $origins[] = 'https://' . $host;
            $origins[] = 'http://' . $host;
        }

        return array_values(array_unique($origins));
    }

    /**
     * Enforce the platform-wide kill switch: unlock on a matching ?pass=, else 404.
     */
    public static function enforceKillswitch()
    {
        $password = '';
        try {
            $password = (string) Setting::get('killswitch', '');
        } catch (Throwable $e) {
            return;
        }

        if ($password === '') {
            return;
        }

        $pass = queryParam('pass');
        if (is_string($pass) && $pass !== ''
            && self::rateLimit('killswitch:' . (defined('USER_IP') ? USER_IP : 'cli'), 5, 300)
            && password_verify($pass, $password)) {
            try {
                Setting::put('killswitch', '');
            } catch (Throwable $e) {
            }
            self::respondKillswitchUnlocked();
            exit;
        }

        if (!headers_sent()) {
            http_response_code(404);
        }
        exit;
    }

    /**
     * Emit the kill-switch unlock confirmation.
     */
    private static function respondKillswitchUnlocked()
    {
        $isApi = defined('URI') && URI !== '' && explode('/', URI)[0] === 'api';
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: ' . ($isApi ? 'application/json; charset=utf-8' : 'text/plain; charset=utf-8'));
            header('Cache-Control: no-cache, no-store, must-revalidate');
        }
        if ($isApi) {
            echo json_encode(array(
                'success' => true,
                'data'    => array('killed' => false, 'message' => 'ezXSS kill switch cleared. Normal serving resumed.'),
                'error'   => null,
                'meta'    => (object) array(),
            ));
        } else {
            echo 'ezXSS kill switch cleared. Normal serving resumed.';
        }
    }
}
