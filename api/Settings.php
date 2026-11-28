<?php
class SettingsController extends Controller
{
    /**
     * Web-reachable actions.
     */
    public static $routable = array('index', 'update', 'killswitch');

    /**
     * Max stored length for the custom-JS blobs.
     */
    const CUSTOMJS_MAX = 50000;

    /**
     * GET /api/settings - read.
     */
    public function index()
    {
        $this->begin('GET');
        return $this->respond($this->doRead());
    }

    /**
     * POST /api/settings/update - validate + persist posted fields.
     */
    public function update()
    {
        $this->begin('POST');

        $result = $this->doUpdate($this->input());
        Log::event('settings', Auth::id(), 'Updated settings', USER_IP);
        return $this->respond($result);
    }

    /**
     * POST /api/settings/killswitch - arm the platform kill switch.
     */
    public function killswitch()
    {
        $this->begin('POST');
        return $this->respond($this->doKillswitch($this->input()));
    }

    /**
     * All owned settings + an `available` block (timezones + installed themes).
     *
     * @return array
     */
    public function doRead()
    {
        $this->cap('settings.manage');

        $collect = array();
        foreach (Payload::COLLECT_OPTIONS as $opt) {
            $collect[$opt] = Setting::enabled('collect_' . $opt);
        }

        return array(
            'settings' => array(
                'timezone'          => (string) Setting::get('timezone', 'UTC'),
                'theme'             => (string) Setting::get('theme', 'classic'),
                'filter'            => $this->currentFilter(),

                'log_categories'    => $this->logCategories(),
                'storescreenshot'   => (string) Setting::get('storescreenshot', '0') === '1' ? 'base64' : 'file',
                'compress'          => Setting::enabled('compress'),
                'collect'           => $collect,
                'duplicate_columns' => Report::duplicateColumns(),
                'dom_threshold'     => (int) Report::domThreshold(),
                'callback_max_body_kb' => (int) Setting::get('callback_max_body_kb', '51200'),
                'dompart'           => (int) Setting::get('dompart', '0'),
                'persistent'        => Setting::enabled('persistent'),
                'spider'            => Setting::enabled('spider'),
                'beacon_fallback'   => Setting::enabled('beacon_fallback'),

                'allow_customjs'    => (string) Setting::get('allow_customjs', '1') !== '0',
                'allow_pages'       => (string) Setting::get('allow_pages', '1') !== '0',
                'allow_filters'     => (string) Setting::get('allow_filters', '1') !== '0',
                'allow_extensions'  => (string) Setting::get('allow_extensions', '1') !== '0',
                'customjs'          => (string) Setting::get('customjs', ''),
                'customjs2'         => (string) Setting::get('customjs2', ''),
                'extensions'        => $this->currentExtensionIds(),
                'killswitch_active' => (string) Setting::get('killswitch', '') !== '',

                'signup_enabled'    => Setting::enabled('signup_enabled'),
                'signup_mode'       => (string) Setting::get('signup_mode', 'public'),
                'signup_code_set'   => (string) Setting::get('signup_code_hash', '') !== '',

                'smtp'              => array(
                    'enabled'      => Setting::enabled('smtp_enabled'),
                    'host'         => (string) Setting::get('smtp_host', ''),
                    'port'         => (int) Setting::get('smtp_port', '587'),
                    'security'     => (string) Setting::get('smtp_security', 'tls'),
                    'username'     => (string) Setting::get('smtp_username', ''),
                    'from'         => (string) Setting::get('smtp_from', ''),
                    'password_set' => (string) Setting::get('smtp_password', '') !== '',
                ),
            ),
            'available' => array(
                'timezones' => timezone_identifiers_list(),
                'themes'    => $this->availableThemes(),
            ),
        );
    }

    /**
     * Validate + persist only the fields present in the body (partial update).
     *
     * @param array $body
     * @throws HttpException
     */
    public function doUpdate(array $body)
    {
        $this->cap('settings.manage');

        $put = array();

        if (array_key_exists('timezone', $body)) {
            $tz = (string) $body['timezone'];
            if (!in_array($tz, timezone_identifiers_list(), true)) {
                throw new HttpException(422, 'The timezone is not a valid timezone');
            }
            $put['timezone'] = $tz;
        }

        if (array_key_exists('theme', $body)) {
            $theme = preg_replace('/[^a-zA-Z0-9]/', '', (string) $body['theme']);
            if ($theme === '' || !in_array($theme, $this->availableThemes(), true)) {
                throw new HttpException(422, 'This theme is not installed');
            }
            $put['theme'] = $theme;
        }

        if (array_key_exists('filter', $body)) {
            $filter = (string) $body['filter'];
            if (!in_array($filter, array('1', '2', '3', '4'), true)) {
                throw new HttpException(422, 'Invalid filter option');
            }
            $put['filter-save'] = ($filter === '1' || $filter === '2') ? '1' : '0';
            $put['filter-alert'] = ($filter === '1' || $filter === '3') ? '1' : '0';
        }

        $booleans = array('compress', 'persistent', 'spider', 'beacon_fallback', 'smtp_enabled',
            'allow_customjs', 'allow_pages', 'allow_filters', 'allow_extensions');
        foreach ($booleans as $key) {
            if (array_key_exists($key, $body)) {
                $put[$key] = $this->asBool($body[$key]) ? '1' : '0';
            }
        }

        foreach (Log::CATEGORIES as $cat) {
            $key = 'log_' . $cat;
            if (array_key_exists($key, $body)) {
                $put[$key] = $this->asBool($body[$key]) ? '1' : '0';
            }
        }

        if (array_key_exists('storescreenshot', $body)) {
            $put['storescreenshot'] = $this->coerceStorescreenshot($body['storescreenshot']);
        }

        if (array_key_exists('collect', $body) && is_array($body['collect'])) {
            foreach ($body['collect'] as $opt => $val) {
                if (!in_array($opt, Payload::COLLECT_OPTIONS, true)) {
                    throw new HttpException(422, 'Unknown collect option: ' . $opt);
                }
                $put['collect_' . $opt] = $this->asBool($val) ? '1' : '0';
            }
        }

        if (array_key_exists('duplicate_columns', $body)) {
            $put['duplicate_columns'] = $this->validateDuplicateColumns($body['duplicate_columns']);
        }

        if (array_key_exists('dom_threshold', $body)) {
            $put['dom_threshold'] = (string) $this->validateNonNegativeInt($body['dom_threshold'], 'dom_threshold');
        }

        if (array_key_exists('callback_max_body_kb', $body)) {
            $put['callback_max_body_kb'] = (string) $this->validateNonNegativeInt($body['callback_max_body_kb'], 'callback_max_body_kb');
        }

        if (array_key_exists('dompart', $body)) {
            $put['dompart'] = (string) $this->validateNonNegativeInt($body['dompart'], 'dompart');
        }

        if (array_key_exists('extensions', $body)) {
            $put['extensions'] = $this->validateExtensions($body['extensions']);
        }

        foreach (array('customjs', 'customjs2') as $key) {
            if (array_key_exists($key, $body)) {
                $js = (string) $body[$key];
                if (strlen($js) > self::CUSTOMJS_MAX) {
                    throw new HttpException(422, $key . ' is too long (max ' . self::CUSTOMJS_MAX . ' chars)');
                }
                $put[$key] = $js;
            }
        }

        if (array_key_exists('signup_enabled', $body)) {
            $put['signup_enabled'] = $this->asBool($body['signup_enabled']) ? '1' : '0';
        }

        if (array_key_exists('signup_mode', $body)) {
            $mode = (string) $body['signup_mode'];
            if ($mode !== 'public' && $mode !== 'code') {
                throw new HttpException(422, 'Invalid signup mode');
            }
            $put['signup_mode'] = $mode;
        }

        if (array_key_exists('signup_code', $body)) {
            $code = (string) $body['signup_code'];
            if ($code !== '') {
                $put['signup_code_hash'] = password_hash($code, PASSWORD_BCRYPT);
            }
        }

        if (array_key_exists('smtp_host', $body)) {
            $put['smtp_host'] = trim((string) $body['smtp_host']);
        }
        if (array_key_exists('smtp_port', $body)) {
            $port = (int) $body['smtp_port'];
            if ($port < 1 || $port > 65535) {
                throw new HttpException(422, 'SMTP port must be between 1 and 65535');
            }
            $put['smtp_port'] = (string) $port;
        }
        if (array_key_exists('smtp_security', $body)) {
            $sec = (string) $body['smtp_security'];
            if (!in_array($sec, array('none', 'tls', 'ssl'), true)) {
                throw new HttpException(422, 'SMTP security must be none, tls or ssl');
            }
            $put['smtp_security'] = $sec;
        }
        if (array_key_exists('smtp_username', $body)) {
            $put['smtp_username'] = trim((string) $body['smtp_username']);
        }
        if (array_key_exists('smtp_from', $body)) {
            $from = trim((string) $body['smtp_from']);
            if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) {
                throw new HttpException(422, 'SMTP From is not a valid email address');
            }
            $put['smtp_from'] = $from;
        }

        if (array_key_exists('smtp_password', $body) && (string) $body['smtp_password'] !== '') {
            $put['smtp_password'] = (string) $body['smtp_password'];
        }

        if (!empty($put)) {
            Setting::putMany($put);
        }

        return $this->doRead()['settings'];
    }

    /**
     * Arm the platform kill switch. While active the platform 404s everything.
     *
     * @param array $body
     * @throws HttpException
     */
    public function doKillswitch(array $body)
    {
        $this->cap('settings.manage');

        $password = isset($body['password']) ? (string) $body['password'] : '';
        if ($password === '') {
            throw new HttpException(422, 'A kill switch password is required');
        }
        if (strlen($password) < 12) {
            throw new HttpException(422, 'The kill switch password must be at least 12 characters');
        }

        Setting::put('killswitch', password_hash($password, PASSWORD_BCRYPT));

        return array(
            'killed'      => true,
            'message'     => 'ezXSS is now killed. The platform returns 404 to every request until unlocked.',
            'unlock_hint' => 'Visit any URL with ?pass=<your password> to clear the kill switch and resume serving.',
        );
    }

    /**
     * The granular audit-log categories as a category => enabled(bool) map.
     */
    private function logCategories()
    {
        $out = array();
        foreach (Log::CATEGORIES as $cat) {
            $out[$cat] = Setting::enabled('log_' . $cat);
        }
        return $out;
    }

    /**
     * Reconstruct the 1-4 filter option from filter-save / filter-alert.
     *
     * @return int
     */
    private function currentFilter()
    {
        $save = (string) Setting::get('filter-save', '1') === '1';
        $alert = (string) Setting::get('filter-alert', '1') === '1';
        if ($save && $alert) {
            return 1;
        }
        if ($save && !$alert) {
            return 2;
        }
        if (!$save && $alert) {
            return 3;
        }
        return 4;
    }

    /**
     * The currently selected global extension ids (CSV -> int[]).
     *
     * @return array
     */
    private function currentExtensionIds()
    {
        $raw = (string) Setting::get('extensions', '');
        if ($raw === '') {
            return array();
        }
        $out = array();
        foreach (explode(',', $raw) as $part) {
            $part = trim($part);
            if ($part !== '' && ctype_digit($part)) {
                $out[] = (int) $part;
            }
        }
        return $out;
    }

    /**
     * Installed theme ids: the :root "dark" base plus each [data-theme="..."].
     *
     * @return bool
     */
    private function availableThemes()
    {
        $order = array('classic', 'light', 'dark', 'ocean');
        $present = array('dark' => true);
        $cssFile = FRAMEWORK_ROOT . '/assets/app.css';
        if (is_file($cssFile)) {
            $css = (string) file_get_contents($cssFile);
            if (preg_match_all('/\[data-theme="([a-z0-9_-]+)"\]/', $css, $m)) {
                foreach ($m[1] as $name) {
                    $present[$name] = true;
                }
            }
        }
        $out = array();
        foreach ($order as $t) {
            if (isset($present[$t])) { $out[] = $t; }
        }
        foreach (array_keys($present) as $t) {
            if (!in_array($t, $out, true)) { $out[] = $t; }
        }
        return !empty($out) ? $out : array('classic', 'dark');
    }

    /**
     * Coerce the storescreenshot value to the stored '1' (base64) / '0' (file).
     *
     * @param string $value
     * @return string
     */
    private function coerceStorescreenshot($value)
    {
        $value = strtolower(trim((string) $value));
        if ($value === 'base64' || $value === '1') {
            return '1';
        }
        return '0';
    }

    /**
     * Validate the duplicate-columns set (array or CSV), rejecting anything not in DUPLICATE_LIGHT or dom.
     *
     * @param string $value
     * @throws HttpException
     */
    private function validateDuplicateColumns($value)
    {
        $names = is_array($value) ? $value : explode(',', (string) $value);
        $allowed = array_merge(Report::DUPLICATE_LIGHT, array('dom'));
        $out = array();
        foreach ($names as $name) {
            $name = strtolower(trim((string) $name));
            if ($name === '') {
                continue;
            }
            if (!in_array($name, $allowed, true)) {
                throw new HttpException(422, 'Invalid duplicate column: ' . $name);
            }
            $out[$name] = $name;
        }
        return implode(',', array_values($out));
    }

    /**
     * Validate a non-negative integer value (accepts numeric string/int).
     *
     * @param string $value
     * @param string $field
     * @throws HttpException
     */
    private function validateNonNegativeInt($value, $field)
    {
        if (is_int($value)) {
            $n = $value;
        } elseif (is_string($value) && ctype_digit(trim($value))) {
            $n = (int) trim($value);
        } else {
            throw new HttpException(422, $field . ' must be a non-negative integer');
        }
        if ($n < 0) {
            throw new HttpException(422, $field . ' must be a non-negative integer');
        }
        return $n;
    }

    /**
     * Validate the global extension selection (array/CSV of ids); each must be an enabled extension.
     *
     * @param string $value
     * @throws HttpException
     * @return string
     */
    private function validateExtensions($value)
    {
        $ids = is_array($value) ? $value : ($value === '' || $value === null ? array() : explode(',', (string) $value));
        $clean = array();
        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }
            if (!ctype_digit($id)) {
                throw new HttpException(422, 'Invalid extension id: ' . $id);
            }
            $clean[(int) $id] = (int) $id;
        }
        if (empty($clean)) {
            return '';
        }
        $validIds = array();
        foreach (Extension::allEnabled() as $ext) {
            $validIds[(int) $ext['id']] = true;
        }
        foreach ($clean as $id) {
            if (!isset($validIds[$id])) {
                throw new HttpException(422, 'Invalid extension id: ' . $id);
            }
        }
        return implode(',', array_values($clean));
    }
}
