<?php

// Require the configs and helpers by default
require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';

// Autoload register
spl_autoload_register(function ($class) {
	$file = __DIR__ . '/' . $class . '.php';
	if (file_exists($file)) {
		require_once $file;
	}
});
