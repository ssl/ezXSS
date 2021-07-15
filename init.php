<?php

/*
|---------------------------------------------------------------
| DEFINE APPLICATION CONSTANTS
|---------------------------------------------------------------
|
| VERSION	- The current ezXSS version
| DEBUG 	- Switch to display errors
|
*/

define('version', '3.11');
define('debug', false);

if (debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

/*
|---------------------------------------------------------------
| CHECK PHP VERSION
|---------------------------------------------------------------
|
| ezXSS needs PHP 7.1 or up to do its magic
|
*/

if (PHP_VERSION_ID < 70100) {
    error('PHP 7.1 or up is required to use ezXSS');
}

/*
|---------------------------------------------------------------
| LOAD IN REQUIRED FILES AND CHECK CONFIG
|---------------------------------------------------------------
|
| This loads in the Autoload and config file, checks if
| the config file is valid and defines the config constant.
|
| CONFIG 	- Holds all the config values
|
*/

require_once __DIR__ . '/src/Autoload.php';

if(!file_exists('.env')) {
    error('You did not setup your config file yet.', true);
}

$config = parse_ini_file('.env');

if ($config === false) {
    error('There is something wrong with your config file.', true);
}
define('config', $config);

$adminUrl = htmlspecialchars((new Database())->fetchSetting('adminurl') ?? 'manage');
define('adminURL', $adminUrl);

/*
|---------------------------------------------------------------
| PRE-ROUTE
|---------------------------------------------------------------
|
| This checks the requested url and determines what kind of page
| needs to be served.
|
*/

$requestUrl = explode('?', $_SERVER['REQUEST_URI'])[0];

if (strpos($requestUrl, '/'.$adminUrl.'/') === 0 || strpos($requestUrl, '/'.$adminUrl) === 0) {
    $path = str_replace('/'.$adminUrl.'/', '', explode('?', $_SERVER['REQUEST_URI'])[0]);

    if (explode('/', $path)[0] === 'report') {
        $path = explode('/', $path)[0];
    }

    if ($path === 'request') {
        $request = new Request();
        echo $request->json();
    } else {
        $route = new Route();
        echo $route->template($path);
    }
} else {
    $route = new Route();

    if ($requestUrl === '/callback') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            echo $route->callback(file_get_contents('php://input'));
        }
    } else {
        header('Content-Type: application/x-javascript');
        echo $route->jsPayload();
    }
}

/*
|---------------------------------------------------------------
| FATAL ERROR FUNCTION
|---------------------------------------------------------------
|
| This shows the error and closes the application
|
*/

function error($message, $wiki = false) {
    $message .= ($wiki === true ? ' Visit the <a href="https://github.com/ssl/ezXSS/wiki">wiki</a> for more information.' : '');
    echo "<h1>Error</h1><p>$message</p>";
    exit();
}
