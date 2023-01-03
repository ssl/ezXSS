<?php

// Debug modus will show and report any kind of errors, do not enable this unless you are debugging something
define('debug', true);

// Current ezXSS version. Do not edit this
define('version', '4.0');

// Defines the current host
define('host', e($_SERVER['HTTP_HOST']));

// Defines the current url
define('url', e("//{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}"));

// Defines the current path
define('path', e($_SERVER['REQUEST_URI']));
