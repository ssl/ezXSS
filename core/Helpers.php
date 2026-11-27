<?php
/**
 * Helpers - small global functions shared across the framework.
 */

/**
 * Escape a string for safe output (HTML contexts / log echoing).
 *
 * @param string $string
 * @return string
 */
function e($string)
{
    $string = htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
    return str_replace(array('@', '$'), array('&#64;', '&#36;'), $string);
}

/**
 * Is the current request a POST?
 */
function isPOST()
{
    return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Decode the JSON request body into an associative array.
 */
function jsonBody()
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return array();
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : array();
}

/**
 * Read a string query parameter safely.
 *
 * @param string $key
 * @param string $default
 */
function queryParam($key, $default = null)
{
    if (isset($_GET[$key]) && is_string($_GET[$key])) {
        return $_GET[$key];
    }
    return $default;
}

/**
 * The request's Sec-Fetch-* headers as a compact map (values capped at 64 bytes).
 */
function secFetchHeaders()
{
    $map = array(
        'site' => 'HTTP_SEC_FETCH_SITE',
        'mode' => 'HTTP_SEC_FETCH_MODE',
        'dest' => 'HTTP_SEC_FETCH_DEST',
        'user' => 'HTTP_SEC_FETCH_USER',
    );
    $out = array();
    foreach ($map as $key => $header) {
        if (isset($_SERVER[$header]) && $_SERVER[$header] !== '') {
            $out[$key] = substr((string) $_SERVER[$header], 0, 64);
        }
    }
    return $out;
}

/**
 * Parse a user-agent into its os and browser labels (display only).
 *
 * @param string $userAgent
 * @return array
 */
function userAgentParts($userAgent)
{
    $userAgent = (string) $userAgent;

    $browsers = array(
        '/MSIE/i'           => 'IE',
        '/Trident/i'        => 'IE',
        '/Edg/i'            => 'Edge',
        '/Firefox/i'        => 'Firefox',
        '/OPR/i'            => 'Opera',
        '/Chrome|CriOS/i'   => 'Chrome',
        '/Opera/i'          => 'Opera',
        '/UCBrowser/i'      => 'UC Browser',
        '/SamsungBrowser/i' => 'SamsungBrowser',
        '/YaBrowser/i'      => 'Yandex',
        '/Vivaldi/i'        => 'Vivaldi',
        '/Brave/i'          => 'Brave',
        '/Version\/[\d.]+.*Safari/i' => 'Safari',
        '/PlayStation/i'    => 'PlayStation',
    );

    $oses = array(
        '/Googlebot/i'        => 'Googlebot',
        '/bingbot/i'          => 'Bingbot',
        '/MicrosoftPreview/i' => 'Bingbot',
        '/YandexBot/i'        => 'YandexBot',
        '/Windows Phone/i'    => 'Windows Phone',
        '/iPhone|iPod/i'      => 'iPhone',
        '/iPad/i'             => 'iPad',
        '/iOS/i'              => 'iOS',
        '/Android/i'          => 'Android',
        '/CrOS/i'             => 'ChromeOS',
        '/Windows/i'          => 'Windows',
        '/Mac/i'              => 'macOS',
        '/Linux/i'            => 'Linux',
        '/Unix/i'             => 'Unix',
        '/BlackBerry/i'       => 'BlackBerry',
        '/FirefoxOS/i'        => 'Firefox OS',
        '/PlayStation/i'      => 'PlayStation',
    );

    $browser = 'Unknown';
    foreach ($browsers as $regex => $name) {
        if (preg_match($regex, $userAgent)) {
            $browser = $name;
            break;
        }
    }
    $os = 'Unknown';
    foreach ($oses as $regex => $name) {
        if (preg_match($regex, $userAgent)) {
            $os = $name;
            break;
        }
    }

    return array('os' => $os, 'browser' => $browser);
}

/**
 * Conservative user-agent -> "{os} with {browser}" label (display only).
 *
 * @param string $userAgent
 * @return string
 */
function userAgentLabel($userAgent)
{
    $parts = userAgentParts($userAgent);
    if ($parts['os'] === 'Unknown' && $parts['browser'] === 'Unknown') {
        return 'Unknown';
    }
    return $parts['os'] . ' with ' . $parts['browser'];
}

/**
 * Make a string valid UTF-8 by replacing bad byte sequences. Headers and raw fields can carry anything.
 *
 * @param mixed $value
 * @return string
 */
function utf8Clean($value)
{
    $value = (string) $value;
    if ($value === '' || preg_match('//u', $value) === 1) {
        return $value;
    }
    if (function_exists('mb_scrub')) {
        return mb_scrub($value, 'UTF-8');
    }
    return (string) iconv('UTF-8', 'UTF-8//IGNORE', $value);
}

/**
 * Cut a string to at most $bytes bytes without splitting a multibyte character.
 *
 * @param string $value
 * @param int $bytes
 * @return string
 */
function utf8Cut($value, $bytes)
{
    $value = (string) $value;
    if (strlen($value) <= $bytes) {
        return $value;
    }
    while ($bytes > 0 && (ord($value[$bytes]) & 0xC0) === 0x80) {
        $bytes--;
    }
    return substr($value, 0, $bytes);
}

/**
 * Make a string valid UTF-8 and cut it to at most $bytes bytes.
 *
 * @param mixed $value
 * @param int $bytes
 * @return string
 */
function utf8CleanCut($value, $bytes)
{
    return utf8Cut(utf8Clean($value), $bytes);
}
