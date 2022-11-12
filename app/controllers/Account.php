<?php

class Account extends Controller
{

    /**
     * Account index.
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
                if ($this->getPostValue('alert') !== null) {
                    $this->alertSettings();
                }
                if ($this->getPostValue('password') !== null) {
                    $this->passwordSettings();
                }
                if ($this->getPostValue('mfa') !== null) {
                    $this->mfaSettings();
                }
            } catch (Exception $e) {
                $this->view->renderMessage($e->getMessage());
            }
        }

        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'[rand(0, 31)];
        }
        $this->view->renderData('secret', $secret);

        $user = $this->model('User')->getById($this->session->data('id'));
        $this->view->renderCondition('mfaDisabled', strlen($user['secret']) !== 16);
        $this->view->renderCondition('mfaEnabled', strlen($user['secret']) === 16);

        $alerts = $this->model('Alert');
        $this->view->renderChecked('mailOn', $alerts->get($user['id'], 1, 'enabled'));
        $this->view->renderChecked('telegramOn', $alerts->get($user['id'], 2, 'enabled'));
        $this->view->renderChecked('slackOn', $alerts->get($user['id'], 3, 'enabled'));
        $this->view->renderChecked('discordOn', $alerts->get($user['id'], 4, 'enabled'));

        $this->view->renderData('email', $alerts->get($user['id'], 1, 'value1'));
        $this->view->renderData('telegramToken', $alerts->get($user['id'], 2, 'value1'));
        $this->view->renderData('telegramChatID', $alerts->get($user['id'], 2, 'value2'));
        $this->view->renderData('slackWebhook', $alerts->get($user['id'], 3, 'value1'));
        $this->view->renderData('discordWebhook', $alerts->get($user['id'], 4, 'value1'));

        return $this->showContent();
    }

    /**
     * Login page
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

                $account = $this->model('User')->login($username, $password);
                $this->session->createSession($account);
                header('Location: dashboard/index');
            } catch (Exception $e) {
                $this->view->renderMessage($e->getMessage());
            }
        }

        return $this->showContent();
    }

    private function passwordSettings()
    {
        $currentPassword = $this->getPostValue('currentpassword');
        $newPassword = $this->getPostValue('newpassword');
        $newPassword2 = $this->getPostValue('newpassword2');

        $user = $this->model('User')->getById($this->session->data('id'));

        if (!password_verify($currentPassword, $user['password'])) {
            throw new Exception('Current password is not correct.');
        }

        if ($newPassword !== $newPassword2) {
            throw new Exception('The retyped password is not the same as the new password.');
        }

        $this->model('User')->updatePassword($user['id'], $newPassword);
    }

    private function mfaSettings()
    {
        $user = $this->model('User')->getById($this->session->data('id'));
        $secretCode = $user['secret'];
        $secret = $this->getPostValue('secret');
        $code = $this->getPostValue('code');

        if (strlen($secret) == 16) {
            if (strlen($secretCode) === 16) {
                throw new Exception('2FA settings are already enabled.');
            }

            if (strlen($secret) !== 16) {
                throw new Exception('Secret length needs to be 16 characters long');
            }

            if (getAuthCode($secret) != $code) {
                throw new Exception('Code is incorrect.');
            }
        } else {
            if (strlen($secretCode) !== 16) {
                throw new Exception('2FA settings are already disabled.');
            }

            if (getAuthCode($secretCode) != $code) {
                throw new Exception('Code is incorrect.');
            }
            $secret = '';
        }
        $this->model('User')->updateSecret($user['id'], $secret);
    }

    private function alertSettings()
    {
        $alerts = $this->model('Alert');

        $user = $this->model('User')->getById($this->session->data('id'));

        // Mail
        $mailOn = $this->getPostValue('mailon');
        $mail = $this->getPostValue('mail');
        if (!filter_var($mail, FILTER_VALIDATE_EMAIL) && !empty($mail)) {
            throw new Exception('This is not a correct email address.');
        }
        $alerts->set($user['id'], 1, $mailOn !== null, $mail);

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
        $alerts->set($user['id'], 2, $telegramOn !== null, $telegramToken, $telegramChatID);

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
}
