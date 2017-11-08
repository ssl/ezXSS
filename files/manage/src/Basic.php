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

  }
