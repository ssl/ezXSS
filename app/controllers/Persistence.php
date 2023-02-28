<?php

class Persistence extends Controller {

    public function online()
    {
        $this->isLoggedInOrExit();
        $this->view->setTitle('Online');
        $this->view->renderTemplate('persistence/online');

        $this->view->renderCondition('hasReports', true);

        $sessions = $this->model('Persistence')->getAll();

        foreach ($sessions as $key => $value) {
            $sessions[$key]['browser'] = $this->parseUserAgent($sessions[$key]['user-agent']);
            $sessions[$key]['time'] = $this->parseTimestamp($sessions[$key]['time'], 'long');
        }

        $this->view->renderDataset('session', $sessions);

        return $this->showContent();
    }

}