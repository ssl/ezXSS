<?php

class Settings extends Controller
{

    public function index()
    {
        $this->isAdminOrExit();

        $this->view->setTitle('Settings');
        $this->view->renderTemplate('settings/index');

        if ($this->isPOST()) {
            try {
                $this->validateCsrfToken();

                // Application settings
                if ($this->getPostValue('application') !== null) {
                    $this->applicationSettings();
                }

                // Payload settings
                if ($this->getPostValue('global-payload') !== null) {
                    $this->PayloadSettings();
                }

                // Killswitch
                if ($this->getPostValue('killswitch') !== null) {
                    $this->killSwitch();
                }

                // Email alert
                if ($this->getPostValue('email-alert') !== null) {
                    $this->emailAlertSettings();
                }

                // Telegram alert
                if ($this->getPostValue('telegram-alert') !== null) {
                    $this->telegramAlertSettings();
                }

                // Callback alert
                if ($this->getPostValue('callback-alert') !== null) {
                    $this->callbackAlertSettings();
                }
            } catch (Exception $e) {
                $this->view->renderMessage($e->getMessage());
            }
        }

        $timezones = [];
        $timezone = $this->model('Setting')->get('timezone');
        foreach (timezone_identifiers_list() as $key => $name) {
            $selected = $timezone == $name ? 'selected' : '';
            $timezones[$key]['html'] = "<option $selected value=\"$name\">$name</option>";
        }
        $this->view->renderDataset('timezone', $timezones, true);

        $themes = [];
        $theme = $this->model('Setting')->get('theme');
        $files = array_diff(scandir(__DIR__ . '/../../assets/css'), array('.', '..'));
        foreach ($files as $file) {
            $themeName = e(str_replace('.css', '', $file));
            $selected = $theme == $themeName ? 'selected' : '';
            $themes[$themeName]['html'] = "<option $selected value=\"$themeName\">" . ucfirst($themeName) . "</option>";
        }
        $this->view->renderDataset('theme', $themes, true);

        $settings = $this->model('Setting');
        $filterSave = $settings->get('filter-save');
        $filterAlert = $settings->get('filter-alert');

        // This is just one big mess of rendering trying to make this work with original database
        $this->view->renderData('filter1', $filterSave == 1 && $filterAlert == 1 ? 'selected' : '');
        $this->view->renderData('filter2', $filterSave == 1 && $filterAlert == 0 ? 'selected' : '');
        $this->view->renderData('filter3', $filterSave == 0 && $filterAlert == 1 ? 'selected' : '');
        $this->view->renderData('filter4', $filterSave == 0 && $filterAlert == 0 ? 'selected' : '');

        $this->view->renderData('dompart', $settings->get('dompart'));
        $this->view->renderData('customjs', $settings->get('customjs'));
        $this->view->renderData('emailfrom', $settings->get('emailfrom'));
        $this->view->renderData('email', $settings->get('email'));
        $this->view->renderData('telegram-bottoken', $settings->get('telegram-bottoken'));
        $this->view->renderData('telegram-chatid', $settings->get('telegram-chatid'));
        $this->view->renderData('callback-url', $settings->get('callback-url'));

        $this->view->renderChecked('mailOn', $settings->get('alert-mail') == 1);
        $this->view->renderChecked('telegramOn', $settings->get('alert-telegram') == 1);
        $this->view->renderChecked('callbackOn', $settings->get('alert-callback') == 1);

        $this->view->renderChecked('mailUsers', $settings->get('alert-mailUsers') == 1);
        $this->view->renderChecked('mailAll', $settings->get('alert-mailAll') == 1);
        $this->view->renderChecked('telegramUsers', $settings->get('alert-telegramUsers') == 1);
        $this->view->renderChecked('telegramAll', $settings->get('alert-telegramAll') == 1);

        $this->view->renderChecked('cUri', $settings->get('collect_uri') == 1);
        $this->view->renderChecked('cIP', $settings->get('collect_ip') == 1);
        $this->view->renderChecked('cReferer', $settings->get('collect_referer') == 1);
        $this->view->renderChecked('cUserAgent', $settings->get('collect_user-agent') == 1);
        $this->view->renderChecked('cCookies', $settings->get('collect_cookies') == 1);

        $this->view->renderChecked('cLocalStorage', $settings->get('collect_localstorage') == 1);
        $this->view->renderChecked('cSessionStorage', $settings->get('collect_sessionstorage') == 1);
        $this->view->renderChecked('cDOM', $settings->get('collect_dom') == 1);
        $this->view->renderChecked('cOrigin', $settings->get('collect_origin') == 1);
        $this->view->renderChecked('cScreenshot', $settings->get('collect_screenshot') == 1);

        return $this->showContent();
    }

    private function applicationSettings()
    {
        $timezone = $this->getPostValue('timezone');
        $theme = $this->getPostValue('theme');
        $filter = $this->getPostValue('filter');
        $dompart = $this->getPostValue('dompart');

        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            throw new Exception('The timezone is not a valid timezone.');
        }

        $theme = preg_replace('/[^a-zA-Z0-9]/', '', $theme);
        if (!file_exists(__DIR__ . "/../../assets/css/{$theme}.css")) {
            throw new Exception('This theme is not installed.');
        }

        if (!ctype_digit($dompart)) {
            throw new Exception('The dom length needs to be a int number.');
        }

        $filterSave = ($filter == 1 || $filter == 2) ? 1 : 0;
        $filterAlert = ($filter == 1 || $filter == 3) ? 1 : 0;

        $this->model('Setting')->set('dompart', $dompart);
        $this->model('Setting')->set('filter-save', $filterSave);
        $this->model('Setting')->set('filter-alert', $filterAlert);
        $this->model('Setting')->set('timezone', $timezone);
        $this->model('Setting')->set('theme', $theme);
    }

    private function payloadSettings()
    {
        $options = ['uri', 'ip', 'referer', 'user-agent', 'cookies', 'localstorage', 'sessionstorage', 'dom', 'origin', 'screenshot'];

        foreach($options as $option) {
            if ($this->getPostValue($option) !== null) {
                $this->model('Setting')->set("collect_{$option}", '1');
            } else {
                $this->model('Setting')->set("collect_{$option}", '0');
            }
        }

        $this->model('Setting')->set("customjs", $this->getPostValue('customjs'));
    }

    private function killSwitch()
    {
        $password = $this->getPostValue('password');
        $this->model('Setting')->set("killswitch", $password);
        $this->view->renderErrorPage('ezXSS is now killed with password ' . e($password));
    }

    private function emailAlertSettings()
    {
        $mailOn = $this->getPostValue('mailon') !== null ? '1' : '0';
        $this->model('Setting')->set("alert-mail", $mailOn);
        
        $mailUsers = $this->getPostValue('mailusers') !== null ? '1' : '0';
        $this->model('Setting')->set("alert-mailUsers", $mailUsers);

        $mailAll = $this->getPostValue('mailall') !== null ? '1' : '0';
        $this->model('Setting')->set("alert-mailAll", $mailAll);

        $email = $this->getPostValue('email');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('This is not a correct email address.');
        }

        $this->model('Setting')->set("email", $email);
        $this->model('Setting')->set("emailfrom", $this->getPostValue('emailfrom'));
    }

    private function telegramAlertSettings()
    {
        $telegramOn = $this->getPostValue('telegramon') !== null ? '1' : '0';
        $this->model('Setting')->set("alert-telegram", $telegramOn);
        
        $telegramUsers = $this->getPostValue('telegramusers') !== null ? '1' : '0';
        $this->model('Setting')->set("alert-telegramUsers", $telegramUsers);

        $telegramAll = $this->getPostValue('telegramall') !== null ? '1' : '0';
        $this->model('Setting')->set("alert-telegramAll", $telegramAll);

        $bottoken = $this->getPostValue('telegram_bottoken');
        $chatid = $this->getPostValue('telegram_chatid');
        if(!preg_match('/^[a-zA-Z0-9:_-]+$/', $bottoken)) {
            throw new Exception('This does not look like an valid Telegram bot token');
        }

        if (!ctype_digit($chatid)) {
            throw new Exception('The chat id needs to be a digits');
        }
    }

    private function callbackAlertSettings()
    {
        $callbackOn = $this->getPostValue('callbackon') !== null ? '1' : '0';
        $this->model('Setting')->set("alert-callback", $callbackOn);

        $url = $this->getPostValue('callback_url');
        if (!empty($url) && (strpos($url, "http") !== 0 || filter_var($url, FILTER_VALIDATE_URL) === false)) {
            throw new Exception('Invalid callback URL');
        }
        $this->model('Setting')->set("callback-url", $url);
    }
}
