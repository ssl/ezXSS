<?php

  class Route {

    function __construct() {
      $this->user = new User();
      $this->component = new Component();
    }

    public function template($file) {
      //> Redirect home to login if not logged in, or to dashboard when logged in
      if(empty($file) && !$this->user->isLoggedIn()) return $this->redirect('login');
      if(empty($file) && $this->user->isLoggedIn()) return $this->redirect('dashboard');
      if($file == 'login' && $this->user->isLoggedIn()) return $this->redirect('dashboard');
      if($file != 'login' && !$this->user->isLoggedIn()) return $this->redirect('login');
      if(!in_array($file, ['login', 'dashboard', 'settings', 'reports', 'search', 'payload', 'filters', 'share'])) return $this->redirect('login');

      return $this->templateHtml($file);
    }

    public function getFile($file) {
      return file_get_contents(__DIR__ . "/templates/{$file}.htm");
    }

    private function templateHtml($file) {
      $html = str_replace(['{{template}}', '{{menu}}', '{{name}}', '{{domain}}'], [$this->getFile($file), $this->getFile('site/menu'), $file, htmlspecialchars($_SERVER['HTTP_HOST'])], $this->getFile('site/main'));

      preg_match_all('/{{(.*?)\[(.*?)\]}}/', $html, $matches);
      foreach($matches[1] as $key => $value) {
        $html = str_replace($matches[0][$key], $this->component->$value("{$matches[2][$key]}"), $html);
      }

      return $html;
    }

    private function redirect($page) {
      header("Location: /manage/{$page}");
    }

  }
