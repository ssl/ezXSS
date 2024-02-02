<?php

// Parse config data
$config = parse_ini_file(__DIR__ . '/../../.env');

// Defines all database data
define('DB_HOST', $config['dbHost'] ?? '');
define('DB_USER', $config['dbUser'] ?? '');
define('DB_PASS', $config['dbPassword'] ?? '');
define('DB_NAME', $config['dbName'] ?? '');
define('DB_PORT', $config['dbPort'] ?? 3306);
