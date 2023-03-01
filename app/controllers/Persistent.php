<?php

class Persistent extends Controller 
{
    /**
     * Summary of rows
     * 
     * @var array
     */
    private $rows = ['id', 'uri', 'ip', 'referer', 'payload', 'user-agent', 'cookies', 'localstorage', 'sessionstorage', 'dom', 'origin', 'clientid', 'console'];

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

    public function session($clientId) 
    {
        $this->isLoggedInOrExit();
        $this->view->setTitle('Online');
        $this->view->renderTemplate('persistent/session');

        $clientId = explode('-', $clientId ?? '');
        $session = $this->model('Persistent')->getByClientId($clientId[0] ?? '', $clientId[1] ?? '');

        // Check report permissions
        // todo

        // Render all rows
        $screenshot = !empty($session['screenshot']) ? '<img src="/assets/img/report-' . e($session['screenshot']) . '.png" style="max-width:100%">' : '';
        $this->view->renderData('screenshot', $screenshot, true);
        $this->view->renderData('time', date('F j, Y, g:i a', $session['time']));

        foreach ($this->rows as $value) {
            $this->view->renderData($value, $session[$value]);
        }

        return $this->showContent();

    }

    public function view($id)
    {
        //
    }

}