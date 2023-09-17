<?php

// Debug modus will show and report any kind of errors, do not enable this unless you are debugging something
define('debug', true);

// Defines whenever httpmode is enabled, this allows ezXSS panel to be used without SSL
define('httpmode', false);

// Defines whenever sign up is enabled, do not enable this unless you are serving a public ezXSS installation - this allows anyone to register!
define('signupEnabled', false);

// Current ezXSS version. Do not edit this
define('version', '4.1');

// Defines the current host
define('host', e($_SERVER['HTTP_HOST']));

// Defines the current url
define('url', e("//{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}"));

// Defines the current path
define('path', e($_SERVER['REQUEST_URI']));

// Defines the IP of the user
define('userip', $_SERVER['REMOTE_ADDR']);