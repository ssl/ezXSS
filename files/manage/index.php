<?php

  if(empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')
  {
    header('Location: https://' . htmlspecialchars($_SERVER['SERVER_NAME']) . $_SERVER['REQUEST_URI']);
    exit();
  }

  require __DIR__ . '/src/Bootstrap.php';

?>
