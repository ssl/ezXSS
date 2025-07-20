<?php

// Current ezXSS version. Do not edit this
define('version', '4.3');

// Defines the current host
define('host', e($_SERVER['HTTP_HOST']));

// Defines the current url
define('url', e("//{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}"));

// Defines the current path
define('path', e($_SERVER['REQUEST_URI'] ?? '/'));

// Defines the IP of the user
define('userip', $_SERVER['REMOTE_ADDR']);

// Defines the current protocol
define('ishttps', (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1') || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'));

// Checks if .env file is created
if (!file_exists(__DIR__ . '/../../.env')) {
    throw new Exception('No config file (.env) found. Go read the documentation first');
}

// Parse config data
$config = parse_ini_file(__DIR__ . '/../../.env');

// Checks if config file is valid
if ($config === false) {
    throw new Exception('There is something wrong with your config file');
}

// Debug modus will show and report any kind of errors, do not enable this unless you are debugging something
$debug = isset($config['debug']) ? $config['debug'] : '';
define('debug', $debug === 'true' || $debug === '1' ? true : false);

// Defines whenever httpmode is enabled, this allows ezXSS panel to be used without SSL
$httpmode = isset($config['httpmode']) ? $config['httpmode'] : '';
define('httpmode', $httpmode === 'true' || $httpmode === '1' ? true : false);

// Defines whenever sign up is enabled, do not enable this unless you are serving a public ezXSS installation - this allows anyone to register!
$signupEnabled = isset($config['signupEnabled']) ? $config['signupEnabled'] : '';
define('signupEnabled', $signupEnabled === 'true' || $signupEnabled === '1' ? true : false);

// Defines the limit of reports
define('reportsLimit', intval(isset($config['reportsLimit']) ? $config['reportsLimit'] : 100000));