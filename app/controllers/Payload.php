<?php

class Payload extends Controller
{

    /**
     * Account index.
     *
     * @return string
     */
    public function index()
    {
        $this->isLoggedInOrExit();
        $this->view->setTitle('Payload');
        $this->view->renderTemplate('payload/index');

        $payloadList = $this->payloadList();
        if (!empty($this->payloadList())) {
            header('Location: /manage/payload/edit/' . $payloadList[0]);
            exit();
        }

        return $this->showContent();
    }

    public function edit($id)
    {
        $this->isLoggedInOrExit();
        $this->view->setTitle('Payload');
        $this->view->renderTemplate('payload/edit');

        $payloadList = $this->payloadList();
        if (!is_numeric($id) || !in_array(+$id, $payloadList, true)) {
            throw new Exception('You dont have permissions to this payload');
        }

        if ($this->isPOST()) {
            try {
                $this->validateCsrfToken();

                if ($this->getPostValue('collecting') !== null) {
                    $this->setCollecting($id);
                }

                if ($this->getPostValue('secondary-payload') !== null) {
                    $this->model('Payload')->setSingleValue($id, "customjs", $this->getPostValue('customjs'));
                }

                if ($this->getPostValue('extract-pages') !== null) {
                    $this->setPages($id, $this->getPostValue('path'));
                }

                if ($this->getPostValue('blacklist-domains') !== null) {
                    $this->setBlacklist($id, $this->getPostValue('domain'));
                }

                if ($this->getPostValue('whitelist-domains') !== null) {
                    $this->setWhitelist($id, $this->getPostValue('domain'));
                }
            } catch (Exception $e) {
                $this->view->renderMessage($e->getMessage());
            }
        }

        $payloads = [];
        foreach ($payloadList as $val) {
            $payload = $this->model('Payload')->getById($val);
            $payloads[] = ['id' => $val, 'name' => ucfirst($payload['payload']), 'selected' => $val == $id ? 'selected' : ''];
        }
        $this->view->renderDataset('payload', $payloads);

        $payload = $this->model('Payload')->getById($id);
        $payload['payload'] = !+$id ? e($_SERVER['HTTP_HOST']) : $payload['payload'];

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
        $this->view->renderData('customjs', $payload['customjs']);

        $i = 0;
        $pages = [];
        foreach (explode('~', $payload['pages']) as $val) {
            if (!empty($val)) {
                $pages[] = ['id' => $i++, 'value' => $val];
            }
        }
        $this->view->renderDataset('pages', $pages);

        $blacklist = [];
        foreach (explode('~', $payload['blacklist']) as $val) {
            if (!empty($val)) {
                $blacklist[] = ['id' => $i++, 'value' => $val];
            }
        }
        $this->view->renderDataset('blacklist', $blacklist);

        $whitelist = [];
        foreach (explode('~', $payload['whitelist']) as $val) {
            if (!empty($val)) {
                $whitelist[] = ['id' => $i++, 'value' => $val];
            }
        }
        $this->view->renderDataset('whitelist', $whitelist);

        return $this->showContent();
    }

    public function removeItem($id)
    {
        $this->view->setContentType('application/json');

        try {
            $this->isLoggedInOrExit();
            $this->validateCsrfToken();

            $payloadList = $this->payloadList();
            if (!is_numeric($id) || !in_array(+$id, $payloadList, true)) {
                throw new Exception('You dont have permissions to this payload');
            }

            $payload = $this->model('Payload')->getById($id);
            $data = $this->getPostValue('data');
            $type = $this->getPostValue('type');

            if (!in_array($type, ['pages', 'blacklist', 'whitelist'])) {
                throw new Exception('You cant remove that here');
            }

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

    private function payloadList()
    {
        $payloadList = [];
        if ($this->isAdmin()) {
            array_push($payloadList, 0);
        }

        $user = $this->model('User')->getById($this->session->data('id'));
        $payloads = $this->model('Payload')->getAllByUserId($user['id']);
        foreach ($payloads as $payload) {
            array_push($payloadList, $payload['id']);
        }

        return $payloadList;
    }

    private function setCollecting($id)
    {
        $options = ['uri', 'ip', 'referer', 'user-agent', 'cookies', 'localstorage', 'sessionstorage', 'dom', 'origin', 'screenshot'];

        foreach ($options as $option) {

            if ($this->getPostValue($option) !== null) {
                $enable = ($this->model('Setting')->get("collect_{$option}") == 1) ? 1 : 0;
                $this->model('Payload')->setSingleValue($id, "collect_{$option}", $enable);
            } else {
                $this->model('Payload')->setSingleValue($id, "collect_{$option}", 0);
            }
        }
    }

    private function setPages($id, $path)
    {
        $payload = $this->model('Payload')->getById($id);

        if (substr($path, 0, 1) !== '/') {
            throw new Exception('Path needs to start with a "/"');
        }

        if (strpos($path, '~') !== false) {
            throw new Exception('This does not look like an valid path');
        }

        $newString = $payload['pages'] . '~' . $path;
        $this->model('Payload')->setSingleValue($id, "pages", $newString);
    }

    private function setBlacklist($id, $domain)
    {
        $payload = $this->model('Payload')->getById($id);

        if (!preg_match('/^(?!\-)(?:(?:[a-zA-Z\d][a-zA-Z\d\-]{0,61})?[a-zA-Z\d]\.){1,126}(?!\d+)[a-zA-Z\d]{1,63}$/', $domain)) {
            throw new Exception('This does not look like an valid domain');
        }

        $newString = $payload['blacklist'] . '~' . $domain;
        $this->model('Payload')->setSingleValue($id, "blacklist", $newString);
    }

    private function setWhitelist($id, $domain)
    {
        $payload = $this->model('Payload')->getById($id);

        if (!preg_match('/^(?!\-)(?:(?:[a-zA-Z\d][a-zA-Z\d\-]{0,61})?[a-zA-Z\d]\.){1,126}(?!\d+)[a-zA-Z\d]{1,63}$/', $domain)) {
            throw new Exception('This does not look like an valid domain');
        }

        $newString = $payload['whitelist'] . '~' . $domain;
        $this->model('Payload')->setSingleValue($id, "whitelist", $newString);
    }
}
