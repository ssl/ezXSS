<?php
class UpdateController extends Controller
{
    /**
     * Web-reachable actions.
     */
    public static $routable = array('index');

    /**
     * Legacy-upgrade POST rate limit (attempts per window seconds).
     */
    const UPGRADE_MAX = 5;
    const UPGRADE_WINDOW = 300;

    /**
     * GET -> status; POST -> run the update. Routed as /api/update.
     *
     * @throws HttpException
     */
    public function index()
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

        if ($method === 'OPTIONS') {

            Security::corsAdmin();
            return;
        }

        if ($method === 'GET') {
            return $this->respond($this->statusPayload());
        }

        if (!isPOST()) {
            throw new HttpException(405, 'Method not allowed. Use POST.');
        }

        if (Upgrader::isLegacy()) {
            return $this->runLegacyUpgrade();
        }

        return $this->runStandardUpdate();
    }

    /**
     * Build the GET status payload.
     *
     * @return array
     */
    private function statusPayload()
    {
        $legacy = false;
        $pending = array();
        $current = null;
        try {
            $legacy = Upgrader::isLegacy();
            $pending = Upgrader::pendingMigrations();
            $current = Upgrader::storedVersion();
        } catch (Throwable $e) {

        }
        $code = (string) Config::get('VERSION', '5.0');
        $needs = $legacy || (count($pending) > 0) || ($current !== null && version_compare((string) $current, $code, '!='));

        return array(
            'current_version' => $current,
            'code_version'    => $code,
            'legacy'          => (bool) $legacy,
            'pending'         => $pending,
            'needs_update'    => (bool) $needs,
        );
    }

    /**
     * Legacy -> 5.0 upgrade (no 5.0 auth exists yet). Rate-limited per IP and advises enabling IP_LOCK.
     *
     * @throws HttpException
     */
    private function runLegacyUpgrade()
    {

        try {
            $allowed = Security::rateLimit('update:ip:' . USER_IP, self::UPGRADE_MAX, self::UPGRADE_WINDOW);
        } catch (Throwable $e) {
            $allowed = true;
        }
        if (!$allowed) {
            throw new HttpException(429, 'Too many update attempts. Try again in a few minutes.');
        }

        try {
            $summary = Upgrader::run();
        } catch (Exception $e) {
            Log::event('update', 0, 'Upgrade failed: ' . $e->getMessage(), USER_IP);
            throw new HttpException(500, 'Upgrade failed.');
        }

        AppState::clearCache();

        $summary['ip_lock_advice'] = Config::get('IP_LOCK', false)
            ? 'IP_LOCK is enabled for this upgrade window.'
            : 'Recommended: enable IP_LOCK in .env while upgrading.';

        return $this->respond($summary, array(), 200);
    }

    /**
     * Standard 5.0 update: apply pending migrations + bump the stored version.
     *
     * @throws HttpException
     */
    private function runStandardUpdate()
    {
        $status = $this->statusPayload();
        if (!$status['needs_update']) {
            return $this->respond(array(
                'upgraded' => false,
                'migrated' => array(),
                'version'  => $status['code_version'],
                'message'  => 'Already up to date.',
            ), array(), 200);
        }

        try {
            $allowed = Security::rateLimit('update:ip:' . USER_IP, self::UPGRADE_MAX, self::UPGRADE_WINDOW);
        } catch (Throwable $e) {
            $allowed = true;
        }
        if (!$allowed) {
            throw new HttpException(429, 'Too many update attempts. Try again in a few minutes.');
        }

        try {
            $ran = Migrator::run();
            $this->seedSessionCaps();
            $version = (string) Config::get('VERSION', '5.0');
            Setting::put('version', $version);
        } catch (Exception $e) {
            Log::event('update', 0, 'Update failed: ' . $e->getMessage(), USER_IP);
            throw new HttpException(500, 'Update failed.');
        }

        AppState::clearCache();

        return $this->respond(array(
            'upgraded' => false,
            'migrated' => $ran,
            'version'  => $version,
            'message'  => empty($ran) ? 'Already up to date.' : 'Applied pending migrations.',
        ), array(), 200);
    }

    /**
     * One-time backfill: grant the new session caps to existing stored roles.
     */
    private function seedSessionCaps()
    {
        if ((string) Setting::get('seeded_session_caps', '') === '1') {
            return;
        }
        foreach (Role::allRoles() as $name => $granted) {
            $granted = is_array($granted) ? $granted : array();
            $changed = false;
            foreach (array('session.view', 'session.interact') as $cap) {
                if (!in_array($cap, $granted, true)) {
                    $granted[] = $cap;
                    $changed = true;
                }
            }
            if ($changed) {
                Role::upsert($name, Permissions::sanitizeGranted($granted));
            }
        }
        Setting::put('seeded_session_caps', '1');
    }
}
