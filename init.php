<?php
try {
    // Check PHP version
    if (PHP_VERSION_ID < 70100) {
        throw new Exception('PHP 7.1 or up is required to use ezXSS');
    }

    // Load all required files
    require __DIR__ . '/system/Autoload.php';

    // Display all errors if in debug mode
    if (debug) {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
    } else {
        ini_set('display_errors', 0);
    }

    // Start routing
    $router = new Router();
    echo $router->proccess($_SERVER['REQUEST_URI']);
} catch (Exception $message) {
    // Any unexpected uncatched exception will show an error page
    if (!class_exists('View')) {
        require_once __DIR__ . '/system/View.php';
    }
    $view = new View();
    $view->setContentType('text/html');
    echo $view->renderErrorPage($message->getMessage());
    exit();
}