<?php

define('START_TIME', microtime(true));

define('FRAMEWORK_ROOT', __DIR__);
define('CORE_PATH', FRAMEWORK_ROOT . '/core');
define('API_PATH', FRAMEWORK_ROOT . '/api');
define('MODELS_PATH', FRAMEWORK_ROOT . '/models');

$uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
$uri = strtolower(explode('&', explode('?', trim($uri, '/'))[0])[0]);
define('HOST', strtolower(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost'));
define('URI', $uri);

require_once CORE_PATH . '/Helpers.php';

spl_autoload_register(function ($class) {
    $shared = array(
        'HttpException' => CORE_PATH . '/ErrorHandler.php',
        'Compression'   => CORE_PATH . '/Model.php',
    );
    if (isset($shared[$class])) {
        require_once $shared[$class];
        return;
    }

    $apiBasename = (substr($class, -10) === 'Controller') ? substr($class, 0, -10) : null;

    $direct = array(
        CORE_PATH . '/' . $class . '.php',
        MODELS_PATH . '/' . $class . '.php',
        API_PATH . '/' . $class . '.php',
    );
    if ($apiBasename !== null) {
        $direct[] = API_PATH . '/' . $apiBasename . '.php';
    }

    foreach ($direct as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    if (!class_exists('Config', false) || !Config::get('DEBUG', false)) {
        return;
    }

    $paths = array(
        CORE_PATH . '/',
        MODELS_PATH . '/',
        API_PATH . '/',
    );
    foreach ($paths as $path) {
        if (!is_dir($path)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $found) {
            if (!$found->isFile()) {
                continue;
            }
            $base = $found->getBasename('.php');
            $apiMatch = ($apiBasename !== null && $path === API_PATH . '/' && $base === $apiBasename);
            if ($base === $class || $apiMatch) {
                require_once $found->getPathname();
                return;
            }
        }
    }
});

Config::load();

$clientIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
if (Config::get('TRUST_PROXY', false)) {
    $fwd = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? trim($_SERVER['HTTP_CF_CONNECTING_IP']) : '';
    if ($fwd === '' && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $fwd = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    if ($fwd !== '' && filter_var($fwd, FILTER_VALIDATE_IP)) {
        $clientIp = $fwd;
    }
}
define('USER_IP', $clientIp);

if (Config::get('DEBUG', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

try {
    ServerExtensions::boot();

    Security::bootstrap();

    Security::enforceKillswitch();

    $firstSegment = (URI === '') ? '' : explode('/', URI)[0];

    AppState::gate($firstSegment);

    if ($firstSegment === 'api') {
        Hook::fire('request.before');
        if (Hook::until('route.api', URI) === null) {
            $router = new Router();
            $router->route();
        }
    } else {
        PublicRouter::dispatch();
    }
} catch (Throwable $e) {
    ErrorHandler::handle($e);
}
