<?php

  echo 'github.com/ssl/ezXSS';

  if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    die();
  }

  #) Decode the JSON from the post
  $phpInput = file_get_contents('php://input');
  $json = json_decode($phpInput);

  #) Require Database for querys
  require_once __DIR__ . '/manage/src/Database.php';
  $database = new Database();

  #) Get settings
  $setting = [];
  foreach($database->query('SELECT * FROM settings') as $settings) {
    $setting[$settings['setting']] = htmlspecialchars($settings['value']);
  }

  #) Some information and settings
  $userIP = isset($json->shared) ? $json->ip : (isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : $_SERVER['REMOTE_ADDR']);
  $domain = htmlspecialchars($_SERVER['SERVER_NAME']);
  $doubleReport = false;
  $json->origin = str_replace(['https://', 'http://'], '', $json->origin);
  date_default_timezone_set($setting['timezone']);

  if($setting['dompart'] > 0 && strlen($json->dom) > $setting['dompart']) {
    $domExtra = '&#13;&#10;&#13;&#10;View full dom on the report page or change this setting on /settings';
  } else {
    $domExtra = '';
  }

  if($json->origin == $setting['blocked-domains'] || in_array($json->origin, explode(',', $setting['blocked-domains']))) {
    exit();
  }

  if($setting['filter-save'] == 0 || $setting['filter-alert'] == 0) {
    $check = $database->fetch('SELECT * FROM reports WHERE cookies = :cookies AND dom = :dom AND origin = :origin AND referer = :referer AND uri = :uri AND `user-agent` = :userAgent AND ip = :ip LIMIT 1',
    [':cookies' => $json->cookies, ':dom' => $json->dom, ':origin' => $json->origin, ':referer' => $json->referrer, ':uri' => $json->uri, ':userAgent' => $json->{'user-agent'}, ':ip' => $userIP]);

    if(isset($check['id'])) {
      if($setting['filter-save'] == 0 && $setting['filter-alert'] == 0) {
        die();
      }
      $doubleReport = true;
    }
  }

  #) Insert into DB
  if( ($doubleReport && $setting['filter-save'] == 1) || (!$doubleReport) ) {
    $id = $database->lastInsertId('INSERT INTO reports (`cookies`, `dom`, `origin`, `referer`, `uri`, `user-agent`, `ip`, `time`) VALUES (:cookies, :dom, :origin, :referer, :uri, :userAgent, :ip, :time)',
    [':cookies' => $json->cookies, ':dom' => $json->dom, ':origin' => $json->origin, ':referer' => $json->referrer, ':uri' => $json->uri, ':userAgent' => $json->{'user-agent'}, ':ip' => $userIP, ':time' => time()]);
  }

  #) Send email
  if( ($doubleReport && $setting['filter-alert'] == 1) || (!$doubleReport) ) {
    $htmlTemplate = str_replace(
      ['{{id}}', '{{domain}}', '{{url}}', '{{ip}}', '{{referer}}', '{{user-agent}}', '{{cookies}}', '{{dom}}', '{{origin}}', '{{time}}'],
      [$id, $domain, htmlspecialchars($json->uri), htmlspecialchars($userIP), htmlspecialchars($json->referrer), htmlspecialchars($json->{'user-agent'}), htmlspecialchars($json->cookies), htmlspecialchars(substr($json->dom, 0, $setting['dompart'])) . $domExtra, htmlspecialchars($json->origin), date('F j Y, g:i a')],
      file_get_contents('manage/src/templates/site/mail.htm')
    );

    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=iso-8859-1';

    mail($setting['email'], '[ezXSS] XSS on ' . htmlspecialchars($json->uri), $htmlTemplate, implode("\r\n", $headers));
  }
