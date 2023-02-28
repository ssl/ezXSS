<?php

class Persistent extends Controller {

    public function all()
    {
        $this->isLoggedInOrExit();
        $this->view->setTitle('Online');
        $this->view->renderTemplate('persistent/index');

        $this->view->renderCondition('hasReports', true);

        $sessions = $this->model('Persistent')->getAll();

        foreach ($sessions as $key => $value) {
            $sessions[$key]['browser'] = $this->parseUserAgent($sessions[$key]['user-agent']);
            $sessions[$key]['time'] = $this->parseTimestamp($sessions[$key]['time'], 'long');
        }

        $this->view->renderDataset('session', $sessions);

        return $this->showContent();
    }

    public function session($clientid) 
    {
        $this->isLoggedInOrExit();
        $this->view->setTitle('Online');
        $this->view->renderTemplate('persistent/session');

        //

    }

    public function view($id)
    {
        //
    }

}