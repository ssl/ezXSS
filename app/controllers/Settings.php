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

                if ($this->getPostValue('application') !== null) {
                    $this->applicationSettings();
                }

                if ($this->getPostValue('global-payload') !== null) {
                    $this->payloadSettings();
                }

                if ($this->getPostValue('global-alert') !== null) {
                    $this->alertSettings();
                }

                if ($this->getPostValue('killswitch') !== null) {
                    $this->killSwitch();
                }

                if ($this->getPostValue('alert-methods') !== null) {
                    $this->alertMethods();
                }

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

        // This is just one big mess of rendering trying to make this work
        $this->view->renderData('filter1', $filterSave == 1 && $filterAlert == 1 ? 'selected' : '');
        $this->view->renderData('filter2', $filterSave == 1 && $filterAlert == 0 ? 'selected' : '');
        $this->view->renderData('filter3', $filterSave == 0 && $filterAlert == 1 ? 'selected' : '');
        $this->view->renderData('filter4', $filterSave == 0 && $filterAlert == 0 ? 'selected' : '');

        // Global payload settings
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
        $this->view->renderData('customjs', $settings->get('customjs'));

        // Settings
        $this->view->renderData('dompart', $settings->get('dompart'));
        $this->view->renderChecked('mailOn', $settings->get('alert-mail') == 1);
        $this->view->renderChecked('telegramOn', $settings->get('alert-telegram') == 1);
        $this->view->renderChecked('slackOn', $settings->get('alert-slack') == 1);
        $this->view->renderChecked('discordOn', $settings->get('alert-discord') == 1);
        $this->view->renderChecked('callbackOn', $settings->get('alert-callback') == 1);
        $this->view->renderData('callbackURL', $settings->get('callback-url'));

        // Alerts
        $alerts = $this->model('Alert');
        $this->view->renderChecked('mailAll', $alerts->get(0, 1, 'enabled'));
        $this->view->renderChecked('telegramAll', $alerts->get(0, 2, 'enabled'));
        $this->view->renderChecked('slackAll', $alerts->get(0, 3, 'enabled'));
        $this->view->renderChecked('discordAll', $alerts->get(0, 4, 'enabled'));

        $this->view->renderData('email', $alerts->get(0, 1, 'value1'));
        $this->view->renderData('telegramToken', $alerts->get(0, 2, 'value1'));
        $this->view->renderData('telegramChatID', $alerts->get(0, 2, 'value2'));
        $this->view->renderData('slackWebhook', $alerts->get(0, 3, 'value1'));
        $this->view->renderData('discordWebhook', $alerts->get(0, 4, 'value1'));

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

        foreach ($options as $option) {
            if ($this->getPostValue($option) !== null) {
                $this->model('Setting')->set("collect_{$option}", '1');
            } else {
                $this->model('Setting')->set("collect_{$option}", '0');
            }
        }

        $this->model('Setting')->set("customjs", $this->getPostValue('customjs'));
    }

    private function alertSettings()
    {
        $alerts = $this->model('Alert');

        // Mail
        $mailOn = $this->getPostValue('mailon');
        $mail = $this->getPostValue('mail');
        if (!filter_var($mail, FILTER_VALIDATE_EMAIL) && !empty($mail)) {
            throw new Exception('This is not a correct email address.');
        }
        $alerts->set(0, 1, $mailOn !== null, $mail);

        // Telegram
        $telegramOn = $this->getPostValue('telegramon');
        $telegramToken = $this->getPostValue('telegram_bottoken');
        $telegramChatID = $this->getPostValue('chatid');
        if (!empty($telegramToken) || !empty($telegramChatID)) {
            if (!preg_match('/^[a-zA-Z0-9:_-]+$/', $telegramToken)) {
                throw new Exception('This does not look like an valid Telegram bot token');
            }

            if (!ctype_digit($telegramChatID)) {
                throw new Exception('The chat id needs to be a digits');
            }
        }
        $alerts->set(0, 2, $telegramOn !== null, $telegramToken, $telegramChatID);

        // Slack
        $slackOn = $this->getPostValue('slackon');
        $slackWebhook = $this->getPostValue('slack_webhook');
        if (!empty($slackWebhook)) {
            if (!preg_match('/https:\/\/hooks\.slack\.com\/services\/([a-zA-Z0-9]+)\/([a-zA-Z0-9_-]+)\/([a-zA-Z0-9_-]+)$/', $slackWebhook)) {
                throw new Exception('This does not look like an valid Slack webhook URL');
            }
        }
        $alerts->set(0, 3, $slackOn !== null, $slackWebhook);

        // Discord
        $discordOn = $this->getPostValue('discordon');
        $discordWebhook = $this->getPostValue('discord_webhook');
        if (!empty($discordWebhook)) {
            if (!preg_match('/https:\/\/(discord|discordapp)\.com\/api\/webhooks\/([\d]+)\/([a-zA-Z0-9_-]+)$/', $discordWebhook)) {
                throw new Exception('This does not look like an valid Discord webhook URL');
            }
        }
        $alerts->set(0, 4, $discordOn !== null, $discordWebhook);
    }

    private function killSwitch()
    {
        $password = $this->getPostValue('password');
        $this->model('Setting')->set("killswitch", $password);
        $this->view->renderErrorPage("ezXSS is now killed with password $password");
    }

    private function alertMethods()
    {
        $mailOn = $this->getPostValue('mailon') !== null ? '1' : '0';
        $this->model('Setting')->set("alert-mail", $mailOn);

        $telegramOn = $this->getPostValue('telegramon') !== null ? '1' : '0';
        $this->model('Setting')->set("alert-telegram", $telegramOn);

        $slackOn = $this->getPostValue('slackon') !== null ? '1' : '0';
        $this->model('Setting')->set("alert-slack", $slackOn);

        $discordOn = $this->getPostValue('discordon') !== null ? '1' : '0';
        $this->model('Setting')->set("alert-discord", $discordOn);
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
