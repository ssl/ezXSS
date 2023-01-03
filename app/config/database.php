<?php

// Checks if .env file is created
if (!file_exists(__DIR__ . '/../../.env')) {
    throw new Exception('You did not setup your config file yet.');
}

// Parse config data
$config = parse_ini_file(__DIR__ . '/../../.env');

// Checks if config file is valid
if ($config === false) {
    throw new Exception('There is something wrong with your config file.');
}

// Defines all database data
define('DB_HOST', $config['dbHost']);
define('DB_USER', $config['dbUser']);
define('DB_PASS', $config['dbPassword']);
define('DB_NAME', $config['dbName']);
define('DB_PORT', $config['dbPort'] ?? 3306);
