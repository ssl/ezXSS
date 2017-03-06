<?php

  $requestUrl = str_replace("/manage/", "", explode("?", $_SERVER["REQUEST_URI"])[0]);

  require_once __DIR__ . '/Autoload.php';

  //> Check if it is a post or normal request
  if($requestUrl == "request") {
    $request = new Request();
    echo $request->json();
  } else {
    $route = new Route();
    echo $route->template($requestUrl);
  }
