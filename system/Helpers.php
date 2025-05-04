<?php

/**
 * Simple shorter function to escape strings
 *
 * @param string $value
 * @return string
 */
function e($value)
{
    return htmlspecialchars($value ?? '', ENT_QUOTES);
}

/**
 * Gets current auth code of provided secret
 * 
 * @param mixed $secret The MFA secret
 * @return string
 */
function getAuthCode($secret): string
{
    $secretKey = baseDecode($secret);
    $hash = hash_hmac('SHA1', chr(0) . chr(0) . chr(0) . chr(0) . pack('N*', floor(time() / 30)), $secretKey, true);
    $value = unpack('N', substr($hash, ord(substr($hash, -1)) & 0x0F, 4));
    $value = $value[1] & 0x7FFFFFFF;
    return str_pad($value % (10 ** 6), 6, '0', STR_PAD_LEFT);
}

/**
 * base32 decodes a string
 * 
 * @param mixed $data The string
 * @return string
 */
function baseDecode($data): string
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $buffer = 0;
    $bufferSize = 0;
    $result = '';
    for ($i = 0, $iMax = strlen($data); $i < $iMax; $i++) {
        $position = strpos($characters, $data[$i]);
        $buffer = ($buffer << 5) | $position;
        $bufferSize += 5;
        if ($bufferSize > 7) {
            $bufferSize -= 8;
            $position = ($buffer & (0xff << $bufferSize)) >> $bufferSize;
            $result .= chr($position);
        }
    }
    return $result;
}

/**
 * Redirects to location
 * 
 * @param string $location The location
 * @return null
 */
function redirect($location)
{
    header('Location: ' . $location);
    exit();
}

/**
 * Returns JSON response
 * 
 * @param string $type The type
 * @param mixed $data The data
 * @param int $status The status code
 * @return null
 */
function jsonResponse($type, $data, $status = 200)
{
    $response = '';

    switch($type) {
        case 'array':
            $response = json_encode($data);
            break;
        case 'data':
            $response = json_encode(['data' => array_values($data)]);
            break;
        default:
            $data = is_int($data) ? $data : e($data);
            $response = json_encode([e($type) => $data]);
            break;
    }

    echo $response;
    http_response_code($status);
    exit();
}

/**
 * Returns POST value
 *
 * @param string $param The param
 * @return string|null
 */
function _POST($param)
{
    return isset($_POST[$param]) && is_string($_POST[$param]) ? $_POST[$param] : null;
}

/**
 * Returns GET value
 *
 * @param string $param The param
 * @return string|null
 */
function _GET($param)
{
    return isset($_GET[$param]) ? $_GET[$param] : null;
}

/**
 * Returns JSON value
 *
 * @param string $param The param
 * @return string|int|null
 */
function _JSON($param)
{
    static $jsonBody = null;
    
    if ($jsonBody === null) {
        $jsonBody = json_decode(file_get_contents('php://input'), true);
        if ($jsonBody === null && json_last_error() !== JSON_ERROR_NONE) {
            $jsonBody = [];
        }
    }

    if(!isset($jsonBody[$param])) {
        return null;
    }

    return is_string($jsonBody[$param]) || is_int($jsonBody[$param]) ? $jsonBody[$param] : null;
}

/**
 * Checks if request method is POST
 *
 * @return boolean
 */
function isPOST()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return true;
    }
    return false;
}

/**
 * Parses user agent and returns string with browser and OS
 * 
 * @param string $userAgent The user agent string
 * @return string
 */
function parseUserAgent($userAgent)
{
    $browser = 'Unknown';
    $os = 'Unknown';

    if ($userAgent === 'Not collected') {
        return $userAgent;
    }

    $browsers = [
        '/MSIE/i' => 'IE',
        '/Trident/i' => 'IE',
        '/Edge/i' => 'Edge',
        '/Edg/i' => 'Edge',
        '/Firefox/i' => 'Firefox',
        '/OPR/i' => 'Opera',
        '/Chrome/i' => 'Chrome',
        '/Opera/i' => 'Opera',
        '/UCBrowser/i' => 'UC Browser',
        '/SamsungBrowser/i' => 'SamsungBrowser',
        '/YaBrowser/i' => 'Yandex',
        '/Vivaldi/i' => 'Vivaldi',
        '/Brave/i' => 'Brave',
        '/Safari/i' => 'Safari',
        '/PlayStation/i' => 'PlayStation'
    ];

    $oses = [
        '/Googlebot/i' => 'Googlebot',
        '/bingbot/i' => 'Bingbot',
        '/MicrosoftPreview/i' => 'Bingbot',
        '/YandexBot/i' => 'YandexBot',
        '/Windows/i' => 'Windows',
        '/iPhone/i' => 'iPhone',
        '/Mac/i' => 'macOS',
        '/Linux/i' => 'Linux',
        '/Unix/i' => 'Unix',
        '/Android/i' => 'Android',
        '/iOS/i' => 'iOS',
        '/BlackBerry/i' => 'BlackBerry',
        '/FirefoxOS/i' => 'Firefox OS',
        '/Windows Phone/i' => 'Windows Phone',
        '/CrOS/i' => 'ChromeOS',
        '/YandexBot/i' => 'YandexBot',
        '/PlayStation/i' => 'PlayStation',
    ];

    // Get the browser
    foreach ($browsers as $regex => $name) {
        if (preg_match($regex, $userAgent)) {
            $browser = $name;
            break;
        }
    }

    // Get the operating system
    foreach ($oses as $regex => $name) {
        if (preg_match($regex, $userAgent)) {
            $os = $name;
            break;
        }
    }

    $browser = $os === 'Unknown' && $browser === 'Unknown' ? 'Unknown' : "{$os} with {$browser}";

    return $browser;
}

/**
 * Parses timestamp and returns string with last x
 * 
 * @param string $timestamp The timestamp
 * @param string $syntax Syntax type
 * @return string
 */
function parseTimestamp($timestamp, $syntax = 'short')
{
    if ($timestamp === 0) {
        return 'never';
    }

    $elapsed = time() - $timestamp;

    if ($elapsed < 60) {
        $unit = ($elapsed == 1) ? 'second' : 'seconds';
        return ($syntax == 'short') ? $elapsed . ' sec' : "$elapsed {$unit} ago";
    } elseif ($elapsed < 3600) {
        $minutes = floor($elapsed / 60);
        $unit = ($minutes == 1) ? 'minute' : 'minutes';
        return ($syntax == 'short') ? $minutes . ' min' : "$minutes {$unit} ago";
    } elseif ($elapsed < 86400) {
        $hours = floor($elapsed / 3600);
        $unit = ($hours == 1) ? 'hour' : 'hours';
        return ($syntax == 'short') ? $hours . ' hr' : "$hours {$unit} ago";
    } elseif ($elapsed < 2592000) {
        $days = floor($elapsed / 86400);
        $unit = ($days == 1) ? 'day' : 'days';
        return ($syntax == 'short') ? $days . ' ' . $unit : "$days {$unit} ago";
    } else {
        $months = floor($elapsed / 2592000);
        $unit = ($months == 1) ? 'month' : 'months';
        return ($syntax == 'short') ? $months . ' mon' : "$months {$unit} ago";
    }
}