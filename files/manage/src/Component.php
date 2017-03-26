<?php

  class Component {

    public function __construct() {
      $this->user = new User();
      $this->database = new Database();
    }

    public function settings($info) {
      $setting = $this->database->newQueryArray('SELECT * FROM settings WHERE setting = :setting LIMIT 1', [':setting' => $info]);
      return htmlspecialchars($setting['value']);
    }

    public function csrf($i) {
      $csrf = $this->user->getCsrf();
      return "<input type=hidden hidden id=csrf value={$csrf}>";
    }

    public function stats($type) {
      //> Return stats for the dashboard
      if($type == 'total') return $this->database->rowCount('SELECT * FROM reports', []);
      if($type == 'week') return $this->database->rowCount('SELECT * FROM reports WHERE time > :time', [':time' => time()-604800]);
      if($type == 'totaldomains') { $query = $this->database->newQueryArray('SELECT COUNT(DISTINCT origin) FROM reports', []); return $query[0]; }
      if($type == 'weekdomains') { $query = $this->database->newQueryArray('SELECT COUNT(DISTINCT origin) FROM reports WHERE time > :time', [':time' => time()-604800]); return $query[0]; };

      if($type == 'last') {
        $last = $this->database->newQueryArray('SELECT * FROM reports ORDER BY id DESC LIMIT 1', []);

        $time = time() - $last['time'];
        $syntaxText = 's';

        if($time > 60) { $time /= 60; $syntaxText = 'm'; }
        if($time > 60) { $time /= 60; $syntaxText = 'h'; }
        if($time > 24) { $time /= 24; $syntaxText = 'd'; }
        return floor($time) . $syntaxText;

      }
    }

    public function get($key) {
      return htmlspecialchars($_GET[$key]);
    }

    /*

    Dirty code incoming
    Will fix it in next version

    */

    public function report($null) {
      if(isset($_GET['id'])) {
        $report = $this->database->newQueryArray('SELECT * FROM reports WHERE id = :id LIMIT 1', [':id' => $_GET['id']]);

        if(isset($report['id'])) {
          //> Delete if trying to delete
          if(isset($_GET['delete'])) {
            if($this->user->sessionInfo('csrfToken') != $_GET['csrf']) {
              return header("Location: /manage/reports");
            }

            $this->database->newQueryArray('DELETE FROM reports WHERE id = :id', [':id' => $_GET['id']]);
            return header("Location: /manage/reports");
          }

          //> Update timezone and change unix time to date
          date_default_timezone_set($this->settings('timezone'));
          $report['time'] = date('F j Y, g:i a', $report['time']);

          //> Replace values in template
          $html = file_get_contents(__DIR__ . '/templates/site/report-id.htm');
          preg_match_all('/{{(.*?)\[(.*?)\]}}/', $html, $matches);
          foreach($matches[1] as $key => $value) {
            $html = str_replace($matches[0][$key], htmlspecialchars($report["{$matches[2][$key]}"]), $html);
          }

        } else {
          header("Location: /manage/reports");
        }

      } else {
        $html = '<div class=col-lg-12><div class="panel panel-filled"><div class=panel-heading>All reports</div><div class=panel-body>
        <div class=table-responsive><table class=table><thead><tr><th>#</th><th>Domain</th><th>URL</th><th>IP</th><th>View</th></tr></thead><tbody>';

        $page =  (isset($_GET['page'])) ? intval(trim(htmlspecialchars($_GET['page']))) : 0;
        foreach($this->database->newQuery('SELECT * FROM reports ORDER BY id DESC LIMIT ' . ($page * 50) . ',50') as $report) {
          $report['uri'] = strlen($report['uri']) > 80 ? substr($report['uri'], 0, 80) . '...' : $report['uri'];
          $html .= '<tr><th scope=row>' . $report['id'] . '</th><td>' . htmlspecialchars($report['origin']) . '</td><td>' . htmlspecialchars($report['uri']) . '</td><td>' . htmlspecialchars($report['ip']) . '</td><td>
          <div class=btn-group role=group><a href="?id=' . $report['id'] . '" class=btn>View</a><div class=btn-group role=group><button type=button class="btn btn-default dropdown-toggle" data-toggle=dropdown aria-haspopup=true aria-expanded=true><span class=caret></span></button><div class=dropdown-backdrop></div><ul class=dropdown-menu>
          <li><a href="share?id=' . $report['id'] . '">Share</a></li>
          <li><a href="?id=' . $report['id'] . '&csrf=' . $this->user->sessionInfo('csrfToken') . '&delete=true">Delete</a></li></ul></div></div></td></tr>';
        }

        $html .= '</tbody></table><div class="col-sm-6"><div class="dataTables_paginate paging_simple_numbers"><ul class=pagination>
        <li class="paginate_button previous"><a href="?page=' . ($page - 1) . '">Previous</a></li><li class="paginate_button active"><a href="?page=' . $page . '">' . $page . '</a></li>
        <li class="paginate_button next"><a href="?page=' . ($page + 1) . '">Next</a></li></ul></div></div></div></div></div></div>';
      }
      return $html;
    }

    public function searchResults($i) {
      if(!isset($_GET['type'])) return '';
      if(!in_array($_GET['type'], ['ip', 'id', 'origin'])) return '';

      $html = '<table class=table><thead><tr><th>#</th><th>Domain</th><th>URL</th><th>IP</th><th>View</th></tr></thead><tbody>';
      foreach($this->database->allQueryArray('SELECT * FROM reports WHERE ' . $_GET['type'] . ' = :search', [':search' => $_GET['search']]) as $report) {
        $html .= '<tr><th scope=row>' . $report['id'] . '</th><td>' . htmlspecialchars($report['origin']) . '</td><td>' . htmlspecialchars($report['uri']) . '</td><td>' . htmlspecialchars($report['ip']) . '</td><td><a href="reports?id=' . $report['id'] . '" class=btn>View</a></td></tr>';
      }

      return '<div class=row><div class=col-lg-12><div id=alert></div></div><div class=col-lg-12><div class="panel panel-filled">
      <div class=panel-heading>Search Results</div><div class=panel-body><div class=table-responsive>' . $html . '</tbody></table></div></div></div></div></div>';
    }

  }
