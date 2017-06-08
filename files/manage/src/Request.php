<?php

  class Request {

    public function __construct() {
      $this->user = new User();
    }

    public function json() {
      if($this->user->sessionInfo('csrfToken') != $this->post('csrf')) {
        return $this->toJson(['echo' => 'CSRF token is not valid']);
      }

      #) Call the correct function based on the action
      switch($this->post('action')) {
        case 'login' : return $this->toJson($this->user->login($this->post('password'), $this->post('code'))); break;
        case 'main-settings' : return $this->toJson($this->user->updateMain($this->post('email'), $this->post('dompart'), $this->post('timezone'))); break;
        case 'pwd-settings' : return $this->toJson($this->user->updatePassword($this->post('password'), $this->post('newpassword'), $this->post('newpassword2'))); break;
        case 'filter-settings' : return $this->toJson($this->user->updateFilters($this->post('save'), $this->post('emailalert'))); break;
        case 'twofactor-settings' : return $this->toJson($this->user->updateTwofactor($this->post('secret'), $this->post('code'))); break;
        case 'share-others' : return $this->toJson($this->user->shareReport($this->post('reportid'), $this->post('domain'))); break;
        case 'payload-customjs' : return $this->toJson($this->user->updatePayload($this->post('customjs'))); break;
        case 'update' : return $this->toJson($this->user->updateSystem($this->post('version'))); break;
        case 'delete-report' : return $this->toJson($this->user->deleteReport($this->post('id'))); break;
        default : return $this->toJson(['echo' => 'Could not found this action, what did you do?']); break;
      }

    }

    private function post($key) {
      return (isset($_POST[$key])) ? $_POST[$key] : '';
    }

    private function toJson($array) {
      #) Return echo json if just an string
      if(!is_array($array)) {
        return json_encode(['echo' => $array]);
      }

      #) Return json
      $array['echo'] = (isset($array['echo'])) ? $array['echo'] : false;
      $array['redirect'] = (isset($array['redirect'])) ? $array['redirect'] : false;
      return json_encode($array);
    }
  }
