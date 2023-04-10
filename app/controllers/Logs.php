<?php

class Logs extends Controller
{
    /**
     * Renders the logs index and returns the content.
     *
     * @return string
     */
    public function index()
    {
        $this->isAdminOrExit();

        $this->view->setTitle('Logs');
        $this->view->renderTemplate('logs/index');

        $logs = $this->model('Log')->getAll();

        foreach ($logs as $key => $value) {
            $user = $this->model('User')->getById($logs[$key]['user_id']);
            $logs[$key]['user'] = $user['username'];
            $logs[$key]['date'] = date('F j, Y, g:i a', $logs[$key]['time']);
        }
        $this->view->renderDataset('log', $logs);
        $this->view->renderCondition('hasLogs', count($logs) > 0);
        
        return $this->showContent();
    }
}