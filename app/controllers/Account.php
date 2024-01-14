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

        if ($this->isPOST()) {
            try {
                $this->validateCsrfToken();

                // Check if posted data is changing alerts
                if ($this->getPostValue('alert') !== null) {
                    $this->alertSettings();
                }

                // Check if posted data is changing passwords
                if ($this->getPostValue('password') !== null) {
                    $currentPassword = $this->getPostValue('currentpassword');
                    $newPassword = $this->getPostValue('newpassword');
                    $newPassword2 = $this->getPostValue('newpassword2');
                    $this->passwordSettings($currentPassword, $newPassword, $newPassword2);
                }

                // Check if posted data is changing MFA
                if ($this->getPostValue('mfa') !== null) {
                    $secret = $this->getPostValue('secret') ?? '';
                    $code = $this->getPostValue('code');
                    $this->mfaSettings($secret, $code);
                }

                // Check if posted data is logout
                if ($this->getPostValue('logout') !== null) {
                    $this->session->deleteSession();
                    redirect('/manage/account/login');
                }
            } catch (Exception $e) {
                $this->view->renderMessage($e->getMessage());
            }
        }

        // Get user data
        $user = $this->model('User')->getById($this->session->data('id'));

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

        if ($this->isPOST()) {
            try {
                $this->validateCsrfToken();

                $username = $this->getPostValue('username');
                $password = $this->getPostValue('password');

                $user = $this->model('User')->login($username, $password);

                if (strlen($user['secret']) === 16) {
                    $this->session->createTempSession($user);
                    redirect('/manage/account/mfa');
                } else {
                    $this->session->createSession($user);
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
            exit();
        }

        if ($this->isPOST()) {
            try {
                $this->validateCsrfToken();

                $code = $this->getPostValue('code');
                $user = $this->model('User')->getById($this->session->data('id'));

                if (getAuthCode($user['secret']) !== $code) {
                    throw new Exception('Code is incorrect');
                }

                $this->session->createSession($user);
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

        if ($this->isPOST()) {
            try {
                if (!signupEnabled) {
                    throw new Exception('Signup is disabled');
                }

                $this->validateCsrfToken();

                $username = $this->getPostValue('username');
                $password = $this->getPostValue('password');
                $domain = $this->getPostValue('domain');

                if ($domain === null || preg_match('/[^A-Za-z0-9]/', $domain)) {
                    throw new Exception('Invalid characters in the domain. Use a-Z0-9');
                }

                if (!$this->model('Payload')->isAvailable("{$domain}." . host)) {
                    throw new Exception('Payload domain is already in use');
                }

                if (strlen($domain) < 1 || strlen($username) > 25) {
                    throw new Exception('Domain needs to be between 1-25 long');
                }

                if (!preg_match('/^(?=.{1,255}$)[a-z0-9][a-z0-9-]{0,62}(?<!-)(\.[a-z0-9][a-z0-9-]{0,62}(?<!-))*\.?$/i', host)) {
                    throw new Exception('Host is not a valid hostname');
                }

                $user = $this->model('User')->create($username, $password, 1);
                $user = $this->model('User')->login($username, $password);
                $this->model('Payload')->add($user['id'], "{$domain}." . host);
                $this->session->createSession($user);
                $this->log('Succesfully created account');

                redirect('manage/dashboard/index');
            } catch (Exception $e) {
                $this->view->renderMessage($e->getMessage());
            }
        }

        return $this->showContent();
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
        $user = $this->model('User')->getById($this->session->data('id'));

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
        $user = $this->model('User')->getById($this->session->data('id'));
        $secretCode = $user['secret'];

        if (strlen($secret) === 16) {
            if (strlen($secretCode) === 16) {
                throw new Exception('2FA settings are already enabled');
            }

            if (strlen($secret) !== 16) {
                throw new Exception('Secret length needs to be 16 characters long');
            }

            if (getAuthCode($secret) !== $code) {
                throw new Exception('Code is incorrect.');
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
        $this->model('User')->setSecret($user['id'], $secret);
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

        $user = $this->model('User')->getById($this->session->data('id'));

        // Mail
        $mailOn = $this->getPostValue('mailon');
        $mail = $this->getPostValue('mail');
        if (!filter_var($mail, FILTER_VALIDATE_EMAIL) && !empty($mail)) {
            throw new Exception('This is not a correct email address');
        }
        $alerts->set($user['id'], 1, $mailOn !== null, $mail);

        // Telegram
        $telegramOn = $this->getPostValue('telegramon');
        $telegramToken = $this->getPostValue('telegram_bottoken');
        $telegramChatID = $this->getPostValue('chatid');
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
        $slackOn = $this->getPostValue('slackon');
        $slackWebhook = $this->getPostValue('slack_webhook');
        if (!empty($slackWebhook)) {
            if (!preg_match('/https:\/\/hooks\.slack\.com\/services\/([a-zA-Z0-9]+)\/([a-zA-Z0-9_-]+)\/([a-zA-Z0-9_-]+)$/', $slackWebhook)) {
                throw new Exception('This does not look like a valid Slack webhook URL');
            }
        }
        $alerts->set($user['id'], 3, $slackOn !== null, $slackWebhook);

        // Discord
        $discordOn = $this->getPostValue('discordon');
        $discordWebhook = $this->getPostValue('discord_webhook');
        if (!empty($discordWebhook)) {
            if (!preg_match('/https:\/\/(discord|discordapp)\.com\/api\/webhooks\/([\d]+)\/([a-zA-Z0-9_-]+)$/', $discordWebhook)) {
                throw new Exception('This does not look like a valid Discord webhook URL');
            }
        }
        $alerts->set($user['id'], 4, $discordOn !== null, $discordWebhook);

        $this->log('Editted personal alert settings');
    }
}
