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
     * @param string $link The client link
     * @throws Exception
     * @return string
     */
    public function session($link)
    {
        $this->isLoggedInOrExit();
        $this->view->setTitle('Online');
        $this->view->renderTemplate('persistent/session');

        [$clientId, $origin] = $this->decodelink($link);

        if (!$this->hasSessionPermissions($clientId, $origin)) {
            throw new Exception('You dont have permissions to this session');
        }

        $session = $this->model('Session')->getByClientId($clientId, $origin);

        if (isPOST()) {
            try {
                if (_POST('proxy') !== null) {
                    $this->validateCsrfToken();
                    $ipport = _POST('ipport');

                    if (!preg_match('/^([\w.-]+):\d+$/', $ipport)) {
                        throw new Exception('This does not look like a valid domain/IP with port');
                    }

                    $passOrigin = _POST('passorigin') !== null ? '1' : '0';
                    $this->model('Console')->add($clientId, $origin, "ez_soc('$ipport', $passOrigin)");
                    throw new Exception("Proxy started on $ipport is accessible on http://$clientId.ezxss" . ($passOrigin === '1' ? " and http://$origin" : ''));
                } elseif (_POST('delete') !== null) {
                    $this->validateCsrfToken();
                    $this->model('Session')->deleteAll($clientId, $origin);
                    redirect('/manage/persistent/all');
                } elseif (_POST('kill') !== null) {
                    $this->validateCsrfToken();
                    $this->model('Console')->add($clientId, $origin, 'ez_stop()');
                    throw new Exception('Persistent killed');
                } elseif (_POST('archive') !== null) {
                    $this->validateCsrfToken();
                    $this->model('Session')->archiveByClientId($clientId, $origin);
                    throw new Exception('Session archived');
                } else {
                    $this->isAPIRequest();

                    if (_JSON('delete') !== null) {
                        $this->model('Session')->deleteAll($clientId, $origin);
                        return jsonResponse('success', 1);
                    }

                    elseif (_JSON('kill') !== null) {
                        $this->model('Console')->add($clientId, $origin, 'ez_stop()');
                        return jsonResponse('success', 1);
                    }

                    elseif (_JSON('execute') !== null) {
                        $command = _JSON('command');
                        $this->model('Console')->add($clientId, $origin, $command);
                        return jsonResponse('success', 1);
                    }

                    elseif (_JSON('archive') !== null) {
                        $this->model('Session')->archiveByClientId($clientId, $origin);
                        return jsonResponse('success', 1);
                    }

                    elseif (_JSON('getconsole') !== null) {
                        $console = $this->model('Session')->getAllConsole($clientId, $origin);
                        return jsonResponse('console', $console);
                    }

                    return jsonResponse('error', 'Invalid request');
                }
            } catch (Exception $e) {
                $this->view->setContentType('text/html');
                $this->view->renderMessage($e->getMessage());
            }
        }

        // Render all rows
        $this->view->renderData('link', base64url_encode($clientId . '~' . $origin));
        $this->view->renderData('time', date('F j, Y, g:i a', $session['time']));
        $this->view->renderData('requests', $this->model('Session')->getRequestCount($clientId));

        $session['browser'] = parseUserAgent($session['user-agent']);
        $session['last'] = parseTimestamp($session['time'], 'long');

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
     * @param string $link The client link
     * @throws Exception
     * @return string
     */
    public function requests($link)
    {
        $this->isLoggedInOrExit();
        $this->view->setTitle('Online');
        $this->view->renderTemplate('persistent/requests');

        [$clientId, $origin] = $this->decodelink($link);

        if (!$this->hasSessionPermissions($clientId, $origin)) {
            throw new Exception('You dont have permissions to this session');
        }

        $requests = $this->model('Session')->getAllByClientId($clientId, $origin);

        foreach ($requests as $key => $value) {
            $requests[$key]['browser'] = parseUserAgent($requests[$key]['user-agent']);
            $requests[$key]['last'] = parseTimestamp($requests[$key]['time'], 'long');
            $requests[$key]['shorturi'] = substr($requests[$key]['uri'], 0, 50);
            $requests[$key]['link'] = base64url_encode($requests[$key]['clientid'] . '~' . $requests[$key]['origin']);
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
        $clientId = $request['clientid'] ?? '';
        $origin = $request['origin'] ?? '';

        if (!$this->hasSessionPermissions($clientId, $origin)) {
            throw new Exception('You dont have permissions to this session');
        }

        $this->view->renderData('time', date('F j, Y, g:i a', $request['time']));

        foreach (array_slice($this->rows, 0, -2) as $value) {
            $this->view->renderData($value, $request[$value]);
        }

        $this->view->renderData('link', base64url_encode($clientId . '~' . $origin));
        
        return $this->showContent();
    }

    /**
     * Renders the list of all sessions within payload and returns the content.
     * 
     * @return string
     */
    public function sessions()
    {
        $this->isAPIRequest();

        $id = _JSON('id');
        $archive = _JSON('archive') === 1 ? 1 : 0;

        // Check payload permissions
        $payloadList = $this->payloadList();
        if (!is_numeric($id) || !in_array(+$id, $payloadList, true)) {
            return jsonResponse('error', 'You dont have permissions to this payload');
        }

        // Checks if requested id is 'all'
        if (+$id === 0) {
            if ($this->isAdmin()) {
                // Show all sessions
                $sessions = $this->model('Session')->getAllByArchive($archive);
            } else {
                // Show all sessions of allowed payloads
                $sessions = [];
                foreach ($payloadList as $payloadId) {
                    if ($payloadId !== 0) {
                        $payload = $this->model('Payload')->getById($payloadId);
                        $payloadUri = '//' . $payload['payload'];
                        if (strpos($payload['payload'], '/') === false) {
                            $payloadUri .= '/%';
                        }
                        $sessions = array_merge($sessions, $this->model('Session')->getAllByPayload($payloadUri, $archive));
                    }
                }
            }
        } else {
            // Show sessions of payload
            $payload = $this->model('Payload')->getById($id);

            $payloadUri = '//' . $payload['payload'];
            if (strpos($payload['payload'], '/') === false) {
                $payloadUri .= '/%';
            }
            $sessions = $this->model('Session')->getAllByPayload($payloadUri, $archive);
        }

        foreach ($sessions as $key => $value) {
            $sessions[$key]['browser'] = parseUserAgent($sessions[$key]['user-agent']);
            $sessions[$key]['last'] = parseTimestamp($sessions[$key]['time'], 'long');
            $sessions[$key]['shorturi'] = substr($sessions[$key]['uri'], 0, 50);
            $sessions[$key]['link'] = base64url_encode($sessions[$key]['clientid'] . '~' . $sessions[$key]['origin']);
        }

        return jsonResponse('data', $sessions);
    }

    /**
     * Archives a session.
     * 
     * @param string $link The client link
     * @throws Exception
     * @return string
     */
    public function archive($link)
    {
        $this->isAPIRequest();

        [$clientId, $origin] = $this->decodelink($link);

        if (!$this->hasSessionPermissions($clientId, $origin)) {
            return jsonResponse('error', 'You dont have permissions to this session');
        }

        $this->model('Session')->archiveByClientId($clientId, $origin);

        return jsonResponse('success', 1);
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
        $user = $this->user();
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

    /**
     * Decodes the client path
     * 
     * @param string $link The client path
     * @throws Exception
     * @return array
     */
    private function decodelink($link)
    {
        try {
            $link = base64url_decode($link);
            $link = explode('~', $link);

            if (count($link) !== 2) {
                throw new Exception('Not found');
            }

            return [$link[0], $link[1]];
        } catch (Exception $e) {
            throw new Exception('Not found');
        }
    }
}
