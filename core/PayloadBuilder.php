<?php
class PayloadBuilder
{
    /**
     * noCollect row order. 'useragent' is emitted as 'user-agent'; 'screenshot'
     */
    const NO_COLLECT_ROWS = array(
        'uri', 'ip', 'referer', 'useragent', 'cookies',
        'localstorage', 'sessionstorage', 'dom', 'csp',
    );

    /**
     * On-the-wire name overrides for the noCollect tokens.
     */
    private static $wireNames = array('useragent' => 'user-agent');

    /**
     * The module template directory.
     */
    private static function dir()
    {
        return FRAMEWORK_ROOT . '/payload';
    }

    /**
     * Load a payload template file by base name (no extension, safe charset).
     *
     * @param string $name
     * @throws HttpException
     */
    public static function load($name)
    {
        if (preg_match('/[^A-Za-z0-9._-]/', $name)) {
            throw new HttpException(403, 'Invalid payload name');
        }
        $file = self::dir() . '/' . $name . '.js';
        if (!is_file($file)) {
            throw new HttpException(404, 'Payload not found');
        }
        return file_get_contents($file);
    }

    /**
     * Assemble the full default/assembled payload JS from a config array.
     *
     * @param array $config
     * @return string
     */
    public static function assemble(array $config)
    {
        $content = self::load('index');

        $identifier = isset($config['identifier']) ? (string) $config['identifier'] : '';
        $version = isset($config['version']) ? (string) $config['version'] : '';
        $domain = isset($config['domain']) ? (string) $config['domain'] : '';
        $protocol = isset($config['protocol']) ? (string) $config['protocol'] : 'https';
        $collect = isset($config['collect']) && is_array($config['collect']) ? $config['collect'] : array();
        $pages = isset($config['pages']) && is_array($config['pages']) ? $config['pages'] : array();
        $spider = isset($config['spider']) ? (string) $config['spider'] : '0';
        if ($spider !== '1' && $spider !== '2') {
            $spider = '0';
        }
        $customjs = isset($config['customjs']) ? (string) $config['customjs'] : '';
        $customjs2 = isset($config['customjs2']) ? (string) $config['customjs2'] : '';
        $globaljs = isset($config['globaljs']) ? (string) $config['globaljs'] : '';
        $globaljs2 = isset($config['globaljs2']) ? (string) $config['globaljs2'] : '';
        $persistent = !empty($config['persistent']);
        $beacon = !empty($config['beacon']);
        $extensions = isset($config['extensions']) ? (string) $config['extensions'] : '';

        $noCollect = array();
        foreach (self::NO_COLLECT_ROWS as $row) {
            $enabled = isset($collect[$row]) ? (bool) $collect[$row] : false;
            if (!$enabled) {
                $wire = isset(self::$wireNames[$row]) ? self::$wireNames[$row] : $row;
                $noCollect[] = "'" . $wire . "'";
            }
        }

        $pageTokens = array();
        foreach ($pages as $page) {
            $page = (string) $page;
            if ($page === '') {
                continue;
            }
            $pageTokens[] = "'" . self::jsEscape($page) . "'";
        }

        $persistentCode = $persistent ? self::load('persist') : '';
        $screenshotCode = (isset($collect['screenshot']) && $collect['screenshot'] && self::uaSupportsScreenshot()) ? self::load('screenshot') : '';

        $beaconCode = $beacon ? self::load('beacon') : '';

        $content = str_replace('{{version}}', self::jsEscape($version), $content);
        $content = str_replace('{{domain}}', self::jsEscape($domain), $content);
        $content = str_replace('{{protocol}}', self::jsEscape($protocol), $content);

        $maxBodyKb = isset($config['max_body_kb']) ? (int) $config['max_body_kb'] : 0;
        $maxBody = $maxBodyKb > 0 ? $maxBodyKb * 1024 : 0;
        $content = self::renderData($content, 'maxBody', (string) $maxBody, true);

        $content = self::renderData($content, 'payload', $identifier, false);
        $content = self::renderData($content, 'spider', $spider, false);
        $content = self::renderData($content, 'noCollect', implode(',', $noCollect), true);
        $content = self::renderData($content, 'pages', implode(',', $pageTokens), true);

        $lineSlots = array(
            'customjs'   => $customjs,
            'customjs2'  => $customjs2,
            'globaljs'   => $globaljs,
            'globaljs2'  => $globaljs2,
            'persistent' => $persistentCode,
            'screenshot' => $screenshotCode,
            'beacon'     => $beaconCode,
            'extensions' => $extensions,
        );
        foreach ($lineSlots as $param => $code) {
            $content = self::renderDataWithLines($content, $param, $code, true);
        }

        $content = str_replace('{{clientKey}}', self::jsEscape(Setting::clientKey()), $content);

        return $content;
    }

    /**
     * Load and render a named custom payload (payload/custom/{name}.js)
     *
     * @param string $name
     * @param array $vars
     * @throws HttpException
     * @return string
     */
    public static function custom($name, array $vars)
    {
        if (preg_match('/[^A-Za-z0-9._-]/', $name)) {
            throw new HttpException(403, 'Invalid payload name');
        }
        $file = self::dir() . '/custom/' . $name . '.js';
        if (!is_file($file)) {
            throw new HttpException(404, 'Payload not found');
        }
        $content = file_get_contents($file);

        $map = array(
            'version'  => isset($vars['version']) ? (string) $vars['version'] : '',
            'domain'   => isset($vars['domain']) ? (string) $vars['domain'] : '',
            'protocol' => isset($vars['protocol']) ? (string) $vars['protocol'] : 'https',
            'fileName' => $name,
        );
        foreach ($map as $key => $value) {
            $content = str_replace('{{' . $key . '}}', self::jsEscape($value), $content);
        }
        return $content;
    }

    /**
     * Whether the browser can run the html2canvas screenshot bundle (false for IE 8 and older).
     *
     * @return bool
     */
    private static function uaSupportsScreenshot()
    {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
        if ($ua === '') {
            return true;
        }
        if (preg_match('/MSIE [1-8]\.0/', $ua) && !preg_match('#Trident/[5-9]#', $ua)) {
            return false;
        }
        return true;
    }

    /**
     * Escape a value to sit inside a JS string literal in the templates.
     *
     * @param string $value
     * @return string
     */
    private static function jsEscape($value)
    {
        $json = json_encode(
            (string) $value,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES
        );
        return is_string($json) ? substr($json, 1, -1) : '';
    }

    /**
     * The /*ez:NAME*\/ block-comment slot for a param name.
     *
     * @param string $param
     * @return string
     */
    private static function slot($param)
    {
        return '/*ez:' . $param . '*/';
    }

    /**
     * Replace the /*ez:$param*\/ slot with the value (plain) or the escaped value.
     *
     * @param string $content
     * @param string $param
     * @param string $value
     * @param bool $plain
     * @return string
     */
    public static function renderData($content, $param, $value, $plain = false)
    {
        $value = (string) $value;
        $replacement = $plain ? $value : self::jsEscape($value);
        return str_replace(self::slot($param), $replacement, $content);
    }

    /**
     * When the value is empty, delete the entire slot line; otherwise inject.
     *
     * @param string $content
     * @param string $param
     * @param string $value
     * @param bool $plain
     * @return string
     */
    public static function renderDataWithLines($content, $param, $value, $plain = false)
    {
        $value = $plain ? (string) $value : e((string) $value);
        $slot = self::slot($param);

        if ($value === '') {
            return preg_replace('/' . preg_quote($slot, '/') . '.*?\n/', '', $content);
        }
        return str_replace($slot, "\n" . $value, $content);
    }

    /**
     * Normalize a raw payload string/URL: lowercase, strip scheme, query, slashes.
     *
     * @param string $raw
     * @return string
     */
    public static function normalizeIdentifier($raw)
    {
        $raw = strtolower(trim((string) $raw));
        $raw = preg_replace('#^(?:[a-z][a-z0-9+.\-]*:)?//#', '', $raw);
        $q = strpos($raw, '?');
        if ($q !== false) {
            $raw = substr($raw, 0, $q);
        }
        return rtrim($raw, '/');
    }
}
