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

        if ($this->isPOST()) {
            try {
                $this->validateCsrfToken();

                // Check if posted data is editing collecting
                if ($this->getPostValue('collecting') !== null) {
                    $this->setCollecting($id);
                }

                // Check if posted data is editing persistent mode
                if ($this->getPostValue('persistent') !== null) {
                    if ($this->model('Setting')->get('persistent') !== '1' && $this->getPostValue('persistent-mode') !== null) {
                        throw new Exception('Persistent mode is globally disabled by the ezXSS admin');
                    }
                    $this->model('Payload')->setSingleValue($id, 'persistent', ($this->getPostValue('persistent-mode') !== null) ? 1 : 0);
                }

                // Check if posted data is editing custom js
                if ($this->getPostValue('secondary-payload') !== null) {
                    $this->model('Payload')->setSingleValue($id, 'customjs', $this->getPostValue('customjs'));
                }

                // Check if posted data is editing extracting pages
                if ($this->getPostValue('extract-pages') !== null) {
                    $this->setPages($id, $this->getPostValue('path'));
                }

                // Check if posted data is editing blacklisted domains
                if ($this->getPostValue('blacklist-domains') !== null) {
                    $this->setBlacklist($id, $this->getPostValue('domain'));
                }

                // Check if posted data is editing whitelisted domains
                if ($this->getPostValue('whitelist-domains') !== null) {
                    $this->setWhitelist($id, $this->getPostValue('domain'));
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

        $i = 0;

        // Render data set of all pages of payload
        $pages = [];
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
        // Set json content type
        $this->view->setContentType('application/json');

        try {
            $this->isLoggedInOrExit();
            $this->validateCsrfToken();

            // Check payload permissions
            $payloadList = $this->payloadList(2);
            if (!is_numeric($id) || !in_array(+$id, $payloadList, true)) {
                throw new Exception('You dont have permissions to this payload');
            }

            $payload = $this->model('Payload')->getById($id);
            $data = $this->getPostValue('data');
            $type = $this->getPostValue('type');

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

            return json_encode([1]);
        } catch (Exception $e) {
            return json_encode(['error' => e($e)]);
        }
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
            if ($this->getPostValue($option) !== null) {
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
     * Add blacklisted domain to payload list
     * 
     * @param string $id The payload id
     * @param string $domain The domain to add
     * @throws Exception
     * @return void
     */
    private function setBlacklist($id, $domain)
    {
        $payload = $this->model('Payload')->getById($id);

        // Validate domain string
        if (!preg_match('/^(?:(?:(?!\*)[a-zA-Z\d][a-zA-Z\d\-*]{0,61})?[a-zA-Z\d]\.){0,1}(?!\d+)(?!.*\*\*)[a-zA-Z\d*]{1,63}(?:\.(?:(?:(?!\*)[a-zA-Z\d][a-zA-Z\d\-*]{0,61})?[a-zA-Z\d]\.){0,1}(?!\d+)(?!.*\*\*)[a-zA-Z\d*]{1,63})*$/', $domain)) {
            throw new Exception('This does not look like a valid domain');
        }

        $newString = $payload['blacklist'] . '~' . $domain;
        $this->model('Payload')->setSingleValue($id, 'blacklist', $newString);
    }

    /**
     * Add blacklisted domain to payload list
     * 
     * @param string $id The payload id
     * @param string $domain The domain to add
     * @throws Exception
     * @return void
     */
    private function setWhitelist($id, $domain)
    {
        $payload = $this->model('Payload')->getById($id);

        // Validate domain string
        if (!preg_match('/^(?:(?:(?!\*)[a-zA-Z\d][a-zA-Z\d\-*]{0,61})?[a-zA-Z\d]\.){0,1}(?!\d+)(?!.*\*\*)[a-zA-Z\d*]{1,63}(?:\.(?:(?:(?!\*)[a-zA-Z\d][a-zA-Z\d\-*]{0,61})?[a-zA-Z\d]\.){0,1}(?!\d+)(?!.*\*\*)[a-zA-Z\d*]{1,63})*$/', $domain)) {
            throw new Exception('This does not look like a valid domain');
        }

        $newString = $payload['whitelist'] . '~' . $domain;
        $this->model('Payload')->setSingleValue($id, 'whitelist', $newString);
    }
}
