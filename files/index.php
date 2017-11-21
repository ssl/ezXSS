<?php

  #) Change content-type to javascript
  header('Content-Type: application/javascript');

  #) Require Database for querys
  require_once __DIR__ . '/manage/src/Database.php';
  $database = new Database();

  $setting = $database->fetch('SELECT * FROM settings WHERE setting = "customjs"');
  $customjs = $setting['value'];

  #) Update values from js_payload
  $js_payload = str_replace(['{{domain}}', '{{customjs}}'], [htmlspecialchars($_SERVER['SERVER_NAME']), $customjs], file_get_contents('js_payload'));

  echo $js_payload;
