<?php

  class Basic {

    public function __construct() {

    }

    public function htmlBlocks($name) {
      switch($name) {
        case 'reportIdTable' : return '<th>Key</th><th>Value</th>'; break;
        case 'reportListTable' : return '<th>ID</th><th>View</th><th>Domain</th><th>URL</th><th>IP</th>'; break;
        case 'displayNone' : return 'display:none'; break;
      }
    }

    public function info($name) {
      switch($name) {
        case 'domain' : return $_SERVER['SERVER_NAME']; break;
      }
    }

    public function getCode($secret) {
      $secretKey = $this->baseDecode($secret);

      $hash = hash_hmac('SHA1', chr(0) . chr(0) . chr(0) . chr(0) . pack('N*', floor(time() / 30)), $secretKey, true);
      $value = unpack('N', substr($hash, ord(substr($hash, -1)) & 0x0F, 4));
      $value = $value[1] & 0x7FFFFFFF;

      return str_pad($value % pow(10, 6), 6, '0', STR_PAD_LEFT);
    }

    private function baseDecode($data) {
      $characters = $this->base32Characters;
      $buffer = 0;
      $bufferSize = 0;
      $result = '';

      for ($i = 0; $i < strlen($data); $i++) {
        $position = strpos($characters, $data[$i]);
        $buffer = ($buffer << 5) | $position;
        $bufferSize += 5;

        if ($bufferSize > 7) {
          $bufferSize -= 8;
          $position = ($buffer & (0xff << $bufferSize)) >> $bufferSize;
          $result .= chr($position);
        }
      }

      return $result;
    }

  }
