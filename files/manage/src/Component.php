<?php

  class Component {

    public function __construct() {
      $this->user = new User();
      $this->basic = new Basic();
      $this->database = new Database();
      $this->releases = [];
      $this->secret = '';
      $this->base32Characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

      date_default_timezone_set($this->settings('timezone'));
    }

    public function settings($name) {
      $setting = $this->database->fetch('SELECT * FROM settings WHERE setting = :name LIMIT 1', [':name' => $name]);
      return htmlspecialchars($setting['value']);
    }

    public function csrf($plain = false) {
      $csrf = $this->user->getCsrf();
      if($plain == true) {
        return $csrf;
      } else {
        return "<input type=hidden hidden id=csrf value={$csrf}>";
      }
    }

    public function stats($type) {
      if($type == 'total') return $this->database->rowCount('SELECT * FROM reports', []);
      if($type == 'week') return $this->database->rowCount('SELECT * FROM reports WHERE time > :time', [':time' => time()-604800]);
      if($type == 'totaldomains') { $query = $this->database->fetch('SELECT COUNT(DISTINCT origin) FROM reports', []); return $query[0]; }
      if($type == 'weekdomains') { $query = $this->database->fetch('SELECT COUNT(DISTINCT origin) FROM reports WHERE time > :time', [':time' => time()-604800]); return $query[0]; };
      if($type == 'totalshared') return $this->database->rowCount('SELECT * FROM reports WHERE referer LIKE "Shared via %"', []);

      if($type == 'last') {
        $last = $this->database->fetch('SELECT * FROM reports ORDER BY id DESC LIMIT 1');

        if(isset($last['id'])) {
          $time = time() - $last['time'];
          $syntaxText = 's';

          if($time > 60) { $time /= 60; $syntaxText = 'm'; }
          if($time > 60) { $time /= 60; $syntaxText = 'h'; }
          if($time > 24) { $time /= 24; $syntaxText = 'd'; }
          return floor($time) . $syntaxText;
        } else {
          return 'never';
        }

      }
    }

    public function get($key) {
      return htmlspecialchars((isset($_GET[$key]) ? $_GET[$key] : ''));
    }

    public function updateStatus($key) {
      if($this->releases == []) {
        $ch = curl_init('https://api.github.com/repos/ssl/ezXSS/releases');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: ezXSS']);
        $this->releases = json_decode(curl_exec($ch), true);
      }

      return htmlspecialchars($this->releases[0][$key]);
    }

    public function twofactorSettings() {
      $secretCheck = $this->database->fetch('SELECT * FROM settings WHERE setting = "secret"');

      if(strlen($secretCheck['value']) != 16) {
        $html = str_replace('{{secret}}', $this->generateSecret(), file_get_contents(__DIR__ . '/templates/site/twofactor-enable.htm'));
      } else {
        $html = file_get_contents(__DIR__ . '/templates/site/twofactor-disable.htm');
      }

      return $html;
    }

    public function twofactorLogin() {
      $secretCheck = $this->database->fetch('SELECT * FROM settings WHERE setting = "secret"');

      if(strlen($secretCheck['value']) == 16) {
        return file_get_contents(__DIR__ . '/templates/site/login-2fa.htm');
      }
    }

    private function generateSecret() {
      if($this->secret == '') {
        for ($i = 0; $i < 16; $i++) {
          $this->secret .= $this->base32Characters[rand(0, 31)];
        }
      }

      return $this->secret;
    }

    public function page($navigation = false) {
      $page = (isset($_GET['page'])) ? intval(trim(htmlspecialchars($_GET['page']))) : 0;

      if($navigation == '+') $page += 1;
      if($navigation == '-') $page -= 1;
      if($page < 0) $page = 0;

      return $page;
    }

    public function searchQuery() {
      if(isset($_GET['search'])) {
        return '&search=' . $_GET['search'];
      }
    }

    public function subString($string, $lenght) {
      return strlen($string) > $lenght ? substr($string, 0, $lenght) . '..' : $string;
    }

    public function reportPage($type) {
      if($type == 'tableHead') {
        if(isset($_GET['id'])) {
          return $this->basic->htmlBlocks('reportIdTable');
        } else {
          return $this->basic->htmlBlocks('reportListTable');
        }
      }

      if($type == 'showPaginate') {
        if(isset($_GET['id'])) {
          return $this->basic->htmlBlocks('displayNone');
        }
      }
    }

    public function report() {
      if(isset($_GET['id'])) {
        $report = $this->database->fetch('SELECT * FROM reports WHERE id = :id LIMIT 1', [':id' => $_GET['id']]);

        if(isset($report['id'])) {
          $html = file_get_contents(__DIR__ . '/templates/site/report-id.htm');
          preg_match_all('/{{(.*?)\[(.*?)\]}}/', $html, $matches);
          foreach($matches[1] as $key => $value) {
            $html = str_replace($matches[0][$key], htmlspecialchars($report["{$matches[2][$key]}"]), $html);
          }
        } else {
          header('Location: /manage/reports');
        }

      } else {
        if(isset($_GET['search'])) {
          $query = 'SELECT * FROM reports WHERE id = :search OR ip = :search OR origin = :search LIMIT ' . ($this->page() * 50) . ',50';
          $array = [':search' => $_GET['search']];
        } else {
          $query = 'SELECT * FROM reports ORDER BY id DESC LIMIT ' . ($this->page() * 50) . ',50';
          $array = [];
        }

        $htmlTemplate = file_get_contents(__DIR__ . '/templates/site/report-list.htm');
        $html = '';

        foreach($this->database->fetchAll($query, $array) as $report) {
          $report['uri'] = $this->subString($report['uri'], 80);
          $report['ip'] = $this->subString($report['ip'], 15);
          $report['origin'] = $this->subString($report['origin'], 20);

          $tempHtml = $htmlTemplate;
          preg_match_all('/{{(.*?)\[(.*?)\]}}/', $tempHtml, $matches);
          foreach($matches[1] as $key => $value) {
            $tempHtml = str_replace($matches[0][$key], htmlspecialchars($report["{$matches[2][$key]}"]), $tempHtml);
          }

          $html .= $tempHtml;
        }

      }

      return $html;
    }

  }
