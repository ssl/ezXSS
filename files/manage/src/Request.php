<?php

  class Request {

    public function __construct() {
      $this->user = new User();
    }

    public function json() {
      //> Make sure the action is valid
      $action = $this->post('action');
      if(!in_array($action, ['login', 'main-settings', 'pwd-settings', 'filter-settings', 'share-new', 'share-others'])) {
        return $this->toJson(['echo' => 'Could not found this action, what did you do?']);
      }

      if($this->user->sessionInfo('csrfToken') != $this->post('csrf')) {
        return $this->toJson(['echo' => 'CSRF token is not valid']);
      }

      //> Call the correct function based on the action
      switch($action) {
        case 'login' : return $this->toJson($this->user->login($this->post('username'), $this->post('password'))); break;
        case 'main-settings' : return $this->toJson($this->user->updateMain($this->post('email'), $this->post('dompart'), $this->post('timezone'))); break;
        case 'pwd-settings' : return $this->toJson($this->user->updatePassword($this->post('password'), $this->post('newpassword'), $this->post('newpassword2'))); break;
        case 'filter-settings' : return $this->toJson($this->user->updateFilters($this->post('save'), $this->post('alert'))); break;
        case 'share-new' : return $this->toJson($this->user->generateKey()); break;
        case 'share-others' : return $this->toJson($this->user->shareReport($this->post('reportid'), $this->post('domain'), $this->post('key'))); break;
        default : return $this->toJson(['echo' => 'Could not found this action, what did you do?']); break;
      }

    }

    private function post($key) {
      return $_POST[$key];
    }

    private function toJson($array) {
      //> Return json
      $array['echo'] = (isset($array['echo'])) ? $array['echo'] : false;
      $array['redirect'] = (isset($array['redirect'])) ? $array['redirect'] : false;
      return json_encode($array);
    }
  }
