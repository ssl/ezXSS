<?php

class Payload extends Controller
{
    /**
     * Either redirects to first payload or renders the payload index and returns the content.
     *
     * @return string
     */
    public function index()
    {
        $this->isLoggedInOrExit();
        $this->view->setTitle('Payload');
        $this->view->renderTemplate('payload/index');

        $payloadList = $this->payloadList(2);
        if (!empty($this->payloadList(2))) {
            redirect('/manage/payload/edit/' . $payloadList[0]);
        }

        return $this->showContent();
    }

    /**
     * Renders or edits the payload edit and returns the content. 
     * 
     * @param string $id The payload id
     * @throws Exception
     * @return string
     */
    public function edit($id)
    {
        $this->isLoggedInOrExit();
        $this->view->setTitle('Payload');
        $this->view->renderTemplate('payload/edit');

        // Check payload permissions
        $payloadList = $this->payloadList(2);
        if (!is_numeric($id) || !in_array(+$id, $payloadList, true)) {
            throw new Exception('You dont have permissions to this payload');
        }

        if (isPOST()) {
            try {
                $this->validateCsrfToken();

                // Check if posted data is editing collecting
                if (_POST('settings') !== null) {
                    $this->setCollecting($id);

                    $this->model('Payload')->setSingleValue($id, 'customjs', _POST('customjs'));
                    $this->model('Payload')->setSingleValue($id, 'customjs2', _POST('customjs2'));

                    $persistent = '2' === _POST('method') ? 1 : 0;
                    if($this->model('Setting')->get('persistent') !== '1' && $persistent === 1) {
                        throw new Exception('Persistent mode is globally disabled by the ezXSS admin');
                    }
                    $this->model('Payload')->setSingleValue($id, 'persistent', $persistent);
                }

                // Check if posted data is editing extracting pages
                if (_POST('extract-pages') !== null) {
                    $this->setPages($id, _POST('path'));
                }

                // Check if posted data is editing denylisted domains
                if (_POST('blacklist-domains') !== null) {
                    $this->setList($id, _POST('domain'), 'deny');
                }

                // Check if posted data is editing allowlisted domains
                if (_POST('whitelist-domains') !== null) {
                    $this->setList($id, _POST('domain'), 'allow');
                }

                $this->log("Updated payload #{$id} settings");
            } catch (Exception $e) {
                $this->view->renderMessage($e->getMessage());
            }
        }

        // Retrieve and render all payloads of user for listing
        $payloads = [];
        foreach ($payloadList as $val) {
            $payload = $this->model('Payload')->getById($val);
            $payloads[] = ['id' => $val, 'name' => ucfirst($payload['payload']), 'selected' => $val == $id ? 'selected' : ''];
        }
        $this->view->renderDataset('payload', $payloads);

        // Get current payload
        $payload = $this->model('Payload')->getById($id);
        $payload['payload'] = +$id === 1 ? host : $payload['payload'];

        // Render all data and checkboxes
        $this->view->renderData('domain', $payload['payload']);
        $this->view->renderChecked('cUri', $payload['collect_uri'] == 1);
        $this->view->renderChecked('cIP', $payload['collect_ip'] == 1);
        $this->view->renderChecked('cReferer', $payload['collect_referer'] == 1);
        $this->view->renderChecked('cUserAgent', $payload['collect_user-agent'] == 1);
        $this->view->renderChecked('cCookies', $payload['collect_cookies'] == 1);
        $this->view->renderChecked('cLocalStorage', $payload['collect_localstorage'] == 1);
        $this->view->renderChecked('cSessionStorage', $payload['collect_sessionstorage'] == 1);
        $this->view->renderChecked('cDOM', $payload['collect_dom'] == 1);
        $this->view->renderChecked('cOrigin', $payload['collect_origin'] == 1);
        $this->view->renderChecked('cScreenshot', $payload['collect_screenshot'] == 1);
        $this->view->renderChecked('cPersistent', $payload['persistent'] == 1);
        $this->view->renderData('customjs', $payload['customjs']);
        $this->view->renderData('customjs2', $payload['customjs2']);
        $this->view->renderData('selectedMethod1', $payload['persistent'] == 0 ? 'selected' : '');
        $this->view->renderData('selectedMethod2', $payload['persistent'] == 1 ? 'selected' : '');
        $this->view->renderData('selectedSpider0', $payload['spider'] == 0 ? 'selected' : '');
        $this->view->renderData('selectedSpider1', $payload['spider'] == 1 ? 'selected' : '');
        $this->view->renderData('selectedSpider2', $payload['spider'] == 2 ? 'selected' : '');

        $i = 0;

        // Render data set of all pages of payload
        $pages = [];
        if($payload['spider'] == 1) {
            $pages[] = ['id' => 'spider', 'value' => '/* (first-level spidering w/ JS Web API)'];
        } else if($payload['spider'] == 2) {
            $pages[] = ['id' => 'spider', 'value' => '/* (recursive spidering w/ iFrame)'];
        }

        foreach (explode('~', $payload['pages'] ?? '') as $val) {
            if (!empty($val)) {
                $pages[] = ['id' => $i++, 'value' => $val];
            }
        }
        $this->view->renderDataset('pages', $pages);
        $this->view->renderCondition('hasPages', count($pages) > 0);

        // Render data set of all blacklisted domains of payload
        $blacklist = [];
        foreach (explode('~', $payload['blacklist'] ?? '') as $val) {
            if (!empty($val)) {
                $blacklist[] = ['id' => $i++, 'value' => $val];
            }
        }
        $this->view->renderDataset('blacklist', $blacklist);
        $this->view->renderCondition('hasBlacklist', count($blacklist) > 0);

        // Render data set of all whitelisted domains of payload
        $whitelist = [];
        foreach (explode('~', $payload['whitelist'] ?? '') as $val) {
            if (!empty($val)) {
                $whitelist[] = ['id' => $i++, 'value' => $val];
            }
        }
        $this->view->renderDataset('whitelist', $whitelist);
        $this->view->renderCondition('hasWhitelist', count($whitelist) > 0);

        return $this->showContent();
    }

    /**
     * Removes page, blacklist or whitelist from payload
     * 
     * @param mixed $id The payload id
     * @throws Exception
     * @return bool|string
     */
    public function removeItem($id)
    {
        $this->isAPIRequest();

        try {
            // Check payload permissions
            $payloadList = $this->payloadList(2);
            if (!is_numeric($id) || !in_array(+$id, $payloadList, true)) {
                throw new Exception('You dont have permissions to this payload');
            }

            $payload = $this->model('Payload')->getById($id);
            $data = _JSON('data');
            $type = _JSON('type');

            // Prevent changing anything else then the allowed items
            if (!in_array($type, ['pages', 'blacklist', 'whitelist'])) {
                throw new Exception('You cant remove that here');
            }

            // Removes item from current data field
            $newString = $payload[$type];
            if (substr($payload[$type], -strlen($data) - 1) === '~' . $data || $payload[$type] === '~' . $data) {
                $newString = substr($payload[$type], 0, -strlen($data) - 1);
            }
            if (strpos($payload[$type], '~' . $data . '~') !== false) {
                $newString = str_replace('~' . $data . '~', '~', $payload[$type]);
            }
            $this->model('Payload')->setSingleValue($id, $type, $newString);

            $this->log("Updated payload #{$id} settings");
            return jsonResponse('success', 1);
        } catch (Exception $e) {
            return jsonResponse('error', $e);
        }
    }

    /**
     * Spiders a payload
     * 
     * @param string $id The payload id
     * @return void
     */
    public function spider($id)
    {
        $this->isAPIRequest();

        $method = _JSON('method');
        $methods = ['0','1','2'];
        
        if (!isset($methods[$method])) {
            jsonResponse('error', 'Invalid spidering method');
        }

        if($this->model('Setting')->get('spider') !== '1' && $method !== '0') {
            jsonResponse('error', 'Spidering is globally disabled by the ezXSS admin');
        }

        $this->model('Payload')->setSingleValue($id, 'spider', $method);
        $this->log("Updated payload #{$id} settings");
        jsonResponse('success', 1);
    }

    /**
     * Saves collecting data
     * 
     * @param string $id The payload id
     * @return void
     */
    private function setCollecting($id)
    {
        $options = ['uri', 'ip', 'referer', 'user-agent', 'cookies', 'localstorage', 'sessionstorage', 'dom', 'origin', 'screenshot'];

        foreach ($options as $option) {
            if (_POST($option) !== null) {
                // Enable collecting item for payload if allowed by admin settings
                $enable = ($this->model('Setting')->get("collect_{$option}") == 1) ? 1 : 0;
                $this->model('Payload')->setSingleValue($id, "collect_{$option}", $enable);
            } else {
                // Disable item
                $this->model('Payload')->setSingleValue($id, "collect_{$option}", 0);
            }
        }
    }

    /**
     * Add page to payload collecting pages
     * 
     * @param string $id The payload id
     * @param string $path The path to add
     * @throws Exception
     * @return void
     */
    private function setPages($id, $path)
    {
        $payload = $this->model('Payload')->getById($id);

        if (substr($path, 0, 1) !== '/') {
            throw new Exception('Path needs to start with a "/"');
        }

        if (strpos($path, '~') !== false) {
            throw new Exception('This does not look like a valid path');
        }

        $newString = $payload['pages'] . '~' . $path;
        $this->model('Payload')->setSingleValue($id, 'pages', $newString);
    }

    /**
     * Add allow/deny listed domain to payload list
     * 
     * @param string $id The payload id
     * @param string $domain The domain to add
     * @param string $type The type of domain (allow/deny)
     * @throws Exception
     * @return void
     */
    private function setList($id, $domain, $type)
    {
        $payload = $this->model('Payload')->getById($id);

        // Validate domain string
        if (!preg_match('/^(?:(?:(?!\*)[a-zA-Z\d][a-zA-Z\d\-*]{0,61})?[a-zA-Z\d]\.){0,1}(?!\d+)(?!.*\*\*)[a-zA-Z\d*]{1,63}(?:\.(?:(?:(?!\*)[a-zA-Z\d][a-zA-Z\d\-*]{0,61})?[a-zA-Z\d]\.){0,1}(?!\d+)(?!.*\*\*)[a-zA-Z\d*]{1,63})*$/', $domain)) {
            throw new Exception('This does not look like a valid domain');
        }

        $type = ($type === 'deny') ? 'blacklist' : 'whitelist';

        $newString = $payload[$type] . '~' . $domain;
        $this->model('Payload')->setSingleValue($id, $type, $newString);
    }
}
