<?php

class Account extends Controller
{
    /**
     * Renders the account index and returns the content.
     *
     * @return string
     */
    public function index()
    {
        $this->isLoggedInOrExit();
        $this->view->setTitle('Account');
        $this->view->renderTemplate('account/index');

        if (isPOST()) {
            try {
                $this->validateCsrfToken();

                // Check if posted data is changing alerts
                if (_POST('alert') !== null) {
                    $this->alertSettings();
                }

                // Check if posted data is changing passwords
                if (_POST('password') !== null) {
                    $currentPassword = _POST('currentpassword');
                    $newPassword = _POST('newpassword');
                    $newPassword2 = _POST('newpassword2');
                    $this->passwordSettings($currentPassword, $newPassword, $newPassword2);
                }

                // Check if posted data is changing MFA
                if (_POST('mfa') !== null) {
                    $secret = _POST('secret') ?? '';
                    $code = _POST('code') ?? '';
                    $this->mfaSettings($secret, $code);
                }

                // Check if posted data is logout
                if (_POST('logout') !== null) {
                    $this->session->destroy();
                    redirect('/manage/account/login');
                }
            } catch (Exception $e) {
                $this->view->renderMessage($e->getMessage());
            }
        }

        // Get user data
        $user = $this->user();

        // Generate MFA secret
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'[rand(0, 31)];
        }

        // Render MFA data and conditions
        $this->view->renderData('secret', $secret);
        $this->view->renderCondition('mfaDisabled', strlen($user['secret']) !== 16);
        $this->view->renderCondition('mfaEnabled', strlen($user['secret']) === 16);

        // Render alerting checkboxes
        $alerts = $this->model('Alert');
        $this->view->renderChecked('mailOn', $alerts->get($user['id'], 1, 'enabled'));
        $this->view->renderChecked('telegramOn', $alerts->get($user['id'], 2, 'enabled'));
        $this->view->renderChecked('slackOn', $alerts->get($user['id'], 3, 'enabled'));
        $this->view->renderChecked('discordOn', $alerts->get($user['id'], 4, 'enabled'));

        // Render alerting data
        $this->view->renderData('email', $alerts->get($user['id'], 1, 'value1'));
        $this->view->renderData('telegramToken', $alerts->get($user['id'], 2, 'value1'));
        $this->view->renderData('telegramChatID', $alerts->get($user['id'], 2, 'value2'));
        $this->view->renderData('slackWebhook', $alerts->get($user['id'], 3, 'value1'));
        $this->view->renderData('discordWebhook', $alerts->get($user['id'], 4, 'value1'));

        return $this->showContent();
    }

    /**
     * Renders the account login page and returns the content.
     *
     * @return string
     */
    public function login()
    {
        $this->isLoggedOutOrExit();
        $this->view->setTitle('Login');
        $this->view->renderTemplate('account/login');

        if (isPOST()) {
            try {
                $this->validateCsrfToken();

                $username = _POST('username');
                $password = _POST('password');

                $user = $this->model('User')->login($username, $password);

                if (strlen($user['secret']) === 16) {
                    $this->session->createTemp($user);
                    redirect('/manage/account/mfa');
                } else {
                    $this->session->create($user);
                    $this->log('Succesfully logged in');

                    if ($this->session->data('redirect') !== '') {
                        redirect($this->session->data('redirect'));
                    } else {
                        redirect('dashboard/index');
                    }
                }
            } catch (Exception $e) {
                $this->view->renderMessage($e->getMessage());
            }
        }

        return $this->showContent();
    }

    /**
     * Renders the account MFA page and returns the content.
     *
     * @return string
     */
    public function mfa()
    {
        $this->isLoggedOutOrExit();
        $this->view->setTitle('Login');
        $this->view->renderTemplate('account/mfa');

        if ($this->session->data('temp') != true) {
            redirect('dashboard/index');
        }

        if (isPOST()) {
            try {
                $this->validateCsrfToken();

                $code = _POST('code');
                $user = $this->user();

                if (getAuthCode($user['secret']) !== $code) {
                    throw new Exception('Code is incorrect');
                }

                $this->session->create($user);
                $this->log('Succesfully logged in with MFA');

                if ($this->session->data('redirect') !== '') {
                    redirect($this->session->data('redirect'));
                } else {
                    redirect('dashboard/index');
                }
            } catch (Exception $e) {
                $this->view->renderMessage($e->getMessage());
            }
        }

        return $this->showContent();
    }

    /**
     * Renders the signup page and returns the content.
     *
     * @return string
     */
    public function signup()
    {
        $this->isLoggedOutOrExit();
        $this->view->setTitle('Sign up');
        $this->view->renderTemplate('account/signup');

        $this->view->renderCondition('isEnabled', signupEnabled);

        if (isPOST()) {
            try {
                if (!signupEnabled) {
                    throw new Exception('Signup is disabled');
                }

                $this->validateCsrfToken();

                $username = _POST('username');
                $password = _POST('password');
                $domain = _POST('domain');

                if ($domain === null || preg_match('/[^A-Za-z0-9]/', $domain)) {
                    throw new Exception('Invalid characters in the domain. Use a-Z0-9');
                }

                if (!$this->model('Payload')->isAvailable("{$domain}." . host)) {
                    throw new Exception('Payload domain is already in use');
                }

                if (strlen($domain) < 1 || strlen($domain) > 25) {
                    throw new Exception('Domain needs to be between 1-25 long');
                }

                if (!preg_match('/^(?=.{1,255}$)[a-z0-9][a-z0-9-]{0,62}(?<!-)(\.[a-z0-9][a-z0-9-]{0,62}(?<!-))*\.?$/i', host)) {
                    throw new Exception('Host is not a valid hostname');
                }

                $user = $this->model('User')->create($username, $password, 1);
                $user = $this->model('User')->login($username, $password);
                $this->model('Payload')->add($user['id'], "{$domain}." . host);
                $this->session->create($user);
                $this->log('Succesfully created account');

                redirect('manage/dashboard/index');
            } catch (Exception $e) {
                $this->view->renderMessage($e->getMessage());
            }
        }

        return $this->showContent();
    }

    /**
     * Returns all enabled alerting methods to user
     * 
     * @throws Exception
     * @return bool|string
     */
    public function getAlertStatus()
    {
        $this->isAPIRequest();

        $alertIds = ['1' => 'mail', '2' => 'telegram', '3' => 'slack', '4' => 'discord'];

        try {
            $alertId = _JSON('alertId');

            if (!is_int($alertId) || !isset($alertIds[$alertId])) {
                throw new Exception('Invalid alert');
            }

            $enabled = $this->model('Setting')->get('alert-' . $alertIds[$alertId]);
            return jsonResponse('enabled', intval($enabled));
        } catch (Exception $e) {
            return jsonResponse('error', $e->getMessage());
        }
    }

    /**
     * Retrieves chat ID from telegram bot
     * 
     * @return string
     */
    public function getChatId()
    {
        $this->isAPIRequest();

        $bottoken = _JSON('bottoken');

        // Validate bottoken string
        if (!preg_match('/^[a-zA-Z0-9:_-]+$/', $bottoken)) {
            return jsonResponse('error', 'This does not look like a valid Telegram bot token');
        }

        // Get last chat from bot
        $api = curl_init("https://api.telegram.org/bot{$bottoken}/getUpdates");
        curl_setopt($api, CURLOPT_RETURNTRANSFER, true);
        $results = json_decode(curl_exec($api), true);

        // Check if result is OK
        if ($results['ok'] !== true) {
            return jsonResponse('error', 'Something went wrong, your bot token is probably invalid');
        }

        $result = end($results['result']);
        // Check if result contains any chat
        if (isset($result['message']['chat']['id'])) {
            return jsonResponse('chatid', $result['message']['chat']['id']);
        }

        // No recent chat found
        return jsonResponse('error', 'The bot token seems valid, but no chat can be found. Start a chat with your bot by sending /start');
    }


    /**
     * Updates the users password
     * 
     * @param string $currentPassword The current password
     * @param string $newPassword The new password
     * @param string $newPassword2 The new password for confirmation
     * @throws Exception
     * @return void
     */
    private function passwordSettings($currentPassword, $newPassword, $newPassword2)
    {
        $user = $this->user();

        if (!password_verify($currentPassword, $user['password'])) {
            throw new Exception('Current password is incorrect');
        }

        if ($newPassword !== $newPassword2) {
            throw new Exception('The retyped password is not the same as the new password');
        }

        $this->log('Changed password');
        $this->model('User')->setPassword($user['id'], $newPassword);
    }

    /**
     * Updates the users MFA settings
     * 
     * @param string $secret The used secret
     * @param string $code The corresponding code
     * @throws Exception
     * @return void
     */
    private function mfaSettings($secret, $code)
    {
        $user = $this->user();
        $secretCode = $user['secret'];

        if (strlen($secret) === 16) {
            if (strlen($secretCode) === 16) {
                throw new Exception('2FA settings are already enabled');
            }

            if (strlen($secret) !== 16) {
                throw new Exception('Secret length needs to be 16 characters long');
            }

            if (getAuthCode($secret) !== $code) {
                throw new Exception('Code is incorrect');
            }
        } else {
            if (strlen($secretCode) !== 16) {
                throw new Exception('2FA settings are already disabled');
            }

            if (getAuthCode($secretCode) !== $code) {
                throw new Exception('Code is incorrect');
            }
            $secret = '';
        }
        $this->log('Updated MFA settings');
        $this->model('User')->set($user['id'], 'secret', $secret);
    }

    /**
     * Updates the users alerting settings
     * 
     * @throws Exception
     * @return void
     */
    private function alertSettings()
    {
        $alerts = $this->model('Alert');

        $user = $this->user();

        // Mail
        $mailOn = _POST('mailon');
        $mail = _POST('mail');
        if (!filter_var($mail, FILTER_VALIDATE_EMAIL) && !empty($mail)) {
            throw new Exception('This is not a correct email address');
        }
        $alerts->set($user['id'], 1, $mailOn !== null, $mail);

        // Telegram
        $telegramOn = _POST('telegramon');
        $telegramToken = _POST('telegram_bottoken');
        $telegramChatID = _POST('chatid');
        if (!empty($telegramToken) || !empty($telegramChatID)) {
            if (!preg_match('/^[a-zA-Z0-9:_-]+$/', $telegramToken)) {
                throw new Exception('This does not look like a valid Telegram bot token');
            }

            if (!preg_match('/^[0-9-]*$/', $telegramChatID)) {
                throw new Exception('The chat id needs to be numeric');
            }
        }
        $alerts->set($user['id'], 2, $telegramOn !== null, $telegramToken, $telegramChatID);

        // Slack
        $slackOn = _POST('slackon');
        $slackWebhook = _POST('slack_webhook');
        if (!empty($slackWebhook)) {
            if (!preg_match('/https:\/\/hooks\.slack\.com\/services\/([a-zA-Z0-9]+)\/([a-zA-Z0-9_-]+)\/([a-zA-Z0-9_-]+)$/', $slackWebhook)) {
                throw new Exception('This does not look like a valid Slack webhook URL');
            }
        }
        $alerts->set($user['id'], 3, $slackOn !== null, $slackWebhook);

        // Discord
        $discordOn = _POST('discordon');
        $discordWebhook = _POST('discord_webhook');
        if (!empty($discordWebhook)) {
            if (!preg_match('/https:\/\/(discord|discordapp)\.com\/api\/webhooks\/([\d]+)\/([a-zA-Z0-9_-]+)$/', $discordWebhook)) {
                throw new Exception('This does not look like a valid Discord webhook URL');
            }
        }
        $alerts->set($user['id'], 4, $discordOn !== null, $discordWebhook);

        $this->log('Updated personal alert settings');
    }
}
