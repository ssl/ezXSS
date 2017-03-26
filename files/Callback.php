<?php

  echo 'github.com/ssl/ezXSS';

  if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    die();
  }

  //> Decode the JSON from the post
  $phpInput = file_get_contents('php://input');
  $json = json_decode($phpInput);

  //> Require Database for querys
  require_once __DIR__ . '/manage/src/Database.php';
  $database = new Database();

  //> Get settings
  $setting = [];
  foreach($database->newQuery('SELECT * FROM settings') as $settings) {
    $setting[$settings['setting']] = htmlspecialchars($settings['value']);
  }

  //> Some information and settings
  $userIP = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? htmlspecialchars($_SERVER['HTTP_CF_CONNECTING_IP']) : $_SERVER['REMOTE_ADDR'];
  $domain = htmlspecialchars($_SERVER['SERVER_NAME']);
  $doubleReport = false;
  $json->origin = str_replace(['https://', 'http://'], '', $json->origin);
  date_default_timezone_set($setting['timezone']);

  if($setting['dompart'] > 0 && strlen($json->dom) > $setting['dompart']) {
    $domExtra = '\n\nView full dom on the report page or change this setting on /settings';
  } else {
    $domExtra = '';
  }

  if(isset($json->shared)) {
    if($setting['secretkey'] != $json->shared) {
      echo 0;
      die();
    }
    $userIP = $json->ip;
  }

  if($setting['filter-save'] == 0 || $setting['filter-alert'] == 0) {
    $check = $database->newQueryArray('SELECT * FROM reports WHERE cookies = :cookies AND dom = :dom AND origin = :origin AND referer = :referer AND uri = :uri AND `user-agent` = :userAgent AND ip = :ip LIMIT 1',
    [':cookies' => $json->cookies, ':dom' => $json->dom, ':origin' => $json->origin, ':referer' => $json->referrer, ':uri' => $json->uri, ':userAgent' => $json->{'user-agent'}, ':ip' => $userIP]);

    if(isset($check['id'])) {
      if($setting['filter-save'] == 0 && $setting['filter-alert'] == 0) {
        die();
      }
      $doubleReport = true;
    }
  }

  //> Create image
  $image = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $json->screenshot));
  $imageName = time() . md5($json->uri . time() . bin2hex(openssl_random_pseudo_bytes(16))) . bin2hex(openssl_random_pseudo_bytes(5));
  $imagePath = "manage/assets/images/reports/{$imageName}.png";
  $saveImage = fopen($imagePath,'w');
  fwrite($saveImage, $image);
  fclose($saveImage);

  //> Insert into DB
  if( ($doubleReport && $setting['filter-save'] == 1) || (!$doubleReport) ) {
    $id = $database->lastInsertId('INSERT INTO reports (`cookies`, `dom`, `origin`, `referer`, `screenshot`, `uri`, `user-agent`, `ip`, `time`) VALUES (:cookies, :dom, :origin, :referer, :screenshot, :uri, :userAgent, :ip, :time)',
    [':cookies' => $json->cookies, ':dom' => $json->dom, ':origin' => $json->origin, ':referer' => $json->referrer, ':screenshot' => $imageName, ':uri' => $json->uri, ':userAgent' => $json->{'user-agent'}, ':ip' => $userIP, ':time' => time()]);
  }

  //> Send email
  if( ($doubleReport && $setting['filter-alert'] == 1) || (!$doubleReport) ) {
    $htmlTemplate = str_replace(
      ['{{id}}', '{{domain}}', '{{url}}', '{{ip}}', '{{referer}}', '{{user-agent}}', '{{cookies}}', '{{dom}}', '{{screenshot}}', '{{origin}}', '{{time}}'],
      [$id, $domain, htmlspecialchars($json->uri), $userIP, htmlspecialchars($json->referrer), htmlspecialchars($json->{'user-agent'}), htmlspecialchars($json->cookies), htmlspecialchars(substr($json->dom, 0, $setting['dompart'])) . $domExtra, $domain . $imagePath, htmlspecialchars($json->origin), date('F j Y, g:i a')],
      file_get_contents('manage/src/templates/site/mail.htm')
    );

    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=iso-8859-1';

    mail($setting['email'], '[ezXSS] XSS on ' . htmlspecialchars($json->uri), $htmlTemplate, implode("\r\n", $headers));
  }
