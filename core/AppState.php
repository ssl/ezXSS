<?php
class AppState
{
    const DB_ERROR      = 'db_error';
    const NOT_INSTALLED = 'not_installed';
    const UPGRADE       = 'upgrade';
    const READY         = 'ready';

    /**
     * API controllers always allowed through, whatever the state.
     */
    private static $allowedControllers = array('install', 'update', 'status');

    /**
     * Cross-request cache of a resolved READY verdict (see classify()).
     */
    const READY_CACHE_KEY = 'appstate:ready';
    const READY_TTL = 15;

    private static $status = null;

    /**
     * Classify the instance once per request (memoised).
     */
    public static function status()
    {
        if (self::$status !== null) {
            return self::$status;
        }
        self::$status = self::classify();
        return self::$status;
    }

    /**
     * Compute the raw state.
     */
    private static function classify()
    {

        try {
            Database::getInstance();
        } catch (Throwable $e) {
            return self::DB_ERROR;
        }

        try {
            $cachedAt = Cache::get(self::READY_CACHE_KEY);
            if (is_numeric($cachedAt) && (time() - (int) $cachedAt) < self::READY_TTL) {
                return self::READY;
            }
        } catch (Throwable $e) {
        }

        $legacy = false;
        try {
            $legacy = Upgrader::isLegacy();
            if ($legacy) {
                return self::UPGRADE;
            }
        } catch (Throwable $e) {

        }

        try {
            $installer = new InstallController();
            if (!$installer->isAlreadyInstalled()) {
                return self::NOT_INSTALLED;
            }
        } catch (Throwable $e) {

            return self::DB_ERROR;
        }

        try {
            if (Upgrader::needsUpgrade($legacy)) {
                return self::UPGRADE;
            }
        } catch (Throwable $e) {

        }

        try {
            Cache::set(self::READY_CACHE_KEY, time());
        } catch (Throwable $e) {
        }

        return self::READY;
    }

    /**
     * Enforce the gate for the current request.
     *
     * @param string $firstSegment
     */
    public static function gate($firstSegment)
    {
        $decision = self::decide(self::status(), $firstSegment, self::apiController());

        switch ($decision['action']) {
            case 'json':
                self::emitJson($decision['status'], $decision['error']);
                exit;
            case 'screen':
                self::renderScreen($decision['screen'], $decision['status']);
                exit;
            case 'pass':
            default:
                return;
        }
    }

    /**
     * Pure disposition resolver. Returns one of:
     *
     * @param string $state
     * @param string $firstSegment
     * @param string $controller
     * @return array
     */
    public static function decide($state, $firstSegment, $controller)
    {
        if ($state === self::READY) {
            return array('action' => 'pass');
        }

        $isApi = ($firstSegment === 'api');

        if ($isApi && self::isAllowedController($controller)) {
            return array('action' => 'pass');
        }

        if ($state === self::DB_ERROR) {
            if ($isApi) {
                return array(
                    'action' => 'json',
                    'status' => 503,
                    'error'  => 'Database not configured. Edit .env with valid database credentials.',
                );
            }

            return array('action' => 'screen', 'status' => 503, 'screen' => 'configure.html');
        }

        $isUpgrade = ($state === self::UPGRADE);
        $screen = $isUpgrade ? 'update.html' : 'install.html';
        $error  = $isUpgrade ? 'Update required' : 'Not installed';

        if ($isApi && $isUpgrade && $controller === 'auth') {
            return array('action' => 'pass');
        }

        if ($isApi) {
            return array('action' => 'json', 'status' => 409, 'error' => $error);
        }
        if ($firstSegment === '' || $firstSegment === 'manage') {
            return array('action' => 'screen', 'status' => 200, 'screen' => $screen);
        }

        return array('action' => 'pass');
    }

    /**
     * Whether an /api/{controller} segment is always allowed through.
     *
     * @param string $controller
     */
    public static function isAllowedController($controller)
    {
        return in_array($controller, self::$allowedControllers, true);
    }

    /**
     * Reset the memoised status (test hook only).
     */
    public static function reset()
    {
        self::$status = null;
        self::clearCache();
    }

    /** Drop the cross-request READY cache so the next request re-classifies.
     * Called after an install/update so a state change takes effect at once. */
    public static function clearCache()
    {
        try {
            Cache::forget(self::READY_CACHE_KEY);
        } catch (Throwable $e) {
        }
    }

    /**
     * The /api/{controller} segment of the current URI, or '' when absent.
     *
     * @return string
     */
    private static function apiController()
    {
        if (!defined('URI') || URI === '') {
            return '';
        }
        $parts = explode('/', URI);

        return isset($parts[1]) ? $parts[1] : '';
    }

    /**
     * Emit a JSON error envelope.
     *
     * @param int $status
     * @param string $error
     */
    private static function emitJson($status, $error)
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(array(
            'success' => false,
            'data'    => null,
            'error'   => $error,
            'meta'    => (object) array('status' => $status, 'state' => self::status()),
        ));
    }

    /**
     * Render a first-run screen (views/panel/<name>) with the panel-scoped.
     *
     * @param string $file
     * @param int $status
     */
    private static function renderScreen($file, $status)
    {
        $name = basename($file, '.html');
        $titles = array(
            'install'   => 'Install &middot; ezXSS 5.0',
            'update'    => 'Update &middot; ezXSS 5.0',
            'configure' => 'Configure &middot; ezXSS 5.0',
        );
        $title = isset($titles[$name]) ? $titles[$name] : 'ezXSS 5.0';

        $html = View::page($name, array('title' => $title, 'scripts' => array('firstrun.js')));
        if ($html === null) {
            if (!headers_sent()) {
                http_response_code(500);
            }
            return;
        }
        PublicRouter::panelShellHeaders($status);
        echo $html;
    }
}
