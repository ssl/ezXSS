<?php

class Request
{

    /**
     * Default values stored
     * @method __construct
     */
    public function __construct()
    {
        $this->user = new User();
    }

    /**
     * Sends request to function and returns json
     * @method json
     * @return string converted json value
     */
    public function json(): ?string
    {
        if ($this->user->getCsrf() !== $this->post('csrf')) {
            return $this->convert('CSRF token is not valid');
        }

        if (!$this->user->isLoggedIn() && $this->post('action') !== 'login' && $this->post('action') !== 'install' && $this->post('action') !== 'update') {
            return $this->convert('You need to be logged in to perform this action.');
        }

        switch ($this->post('action')) {
            case 'login' :
                return $this->convert($this->user->login($this->post('password'), $this->post('code')));
                break;
            case 'install' :
                return $this->convert($this->user->install($this->post('password'), $this->post('email')));
                break;
            case 'notepad-settings' :
                return $this->convert($this->user->notepad($this->post('notepad')));
                break;
            case 'main-settings' :
                return $this->convert(
                    $this->user->settings(
                        $this->post('timezone'),
                        $this->post('theme')
                    )
                );
                break;
            case 'alert-settings':
                return $this->convert(
                    $this->user->alertSettings(
                        $this->post('filter'),
                        $this->post('blocked_domains'),
                        $this->post('whitelist_domains'),
                        $this->post('dompart')
                    )
                );
                break;
            case 'email-alert-settings':
                return $this->convert($this->user->emailAlertSettings(
                    $this->post('mailon'),
                    $this->post('email'),
                    $this->post('emailfrom')
                ));
                break;
            case 'telegram-alert-settings':
                return $this->convert($this->user->telegramAlertSettings(
                    $this->post('telegramon'),
                    $this->post('telegram_bottoken'),
                    $this->post('telegram_chatid')
                ));
                break;
            case 'callback-alert-settings':
                return $this->convert($this->user->callbackAlertSettings(
                    $this->post('callbackon'),
                    $this->post('callback_url')
                ));
                break;
            case 'password-settings' :
                return $this->convert(
                    $this->user->password(
                        $this->post('password'),
                        $this->post('newpassword'),
                        $this->post('newpassword2')
                    )
                );
                break;
            case 'payload-settings' :
                return $this->convert($this->user->payload($this->post('customjs')));
                break;
            case 'twofactor-settings' :
                return $this->convert($this->user->twofactor($this->post('secret'), $this->post('code')));
                break;
            case 'archive-report' :
                return $this->convert($this->user->archiveReport($this->post('id')));
                break;
            case 'delete-report' :
                return $this->convert($this->user->deleteReport($this->post('id')));
                break;
            case 'share-report' :
                return $this->convert($this->user->shareReport($this->post('reportid'), $this->post('domain'), $this->post('email')));
                break;
            case 'killswitch' :
                return $this->convert($this->user->killSwitch($this->post('pass')));
                break;
            case 'delete-selected' :
                return $this->convert($this->user->deleteSelected($this->post('ids')));
                break;
            case 'archive-selected' :
                return $this->convert($this->user->archiveSelected($this->post('ids'), $this->post('archive')));
                break;
            case 'update' :
                return $this->convert($this->user->update());
                break;
            case 'collecting' :
                return $this->convert($this->user->collecting($this->post('select')));
                break;
            case 'statistics':
                return $this->user->statistics();
            case 'getchatid':
                return $this->convert($this->user->getChatId($this->post('bottoken')));
            default :
                return $this->convert('This action does not exists.');
                break;
        }
    }

    /**
     * Get value of post
     * @method post
     * @param string $key key
     * @return string value
     */
    private function post($key)
    {
        return $_POST[$key] ?? '';
    }

    /**
     * Convert a string or array to a JSON string
     * @method post
     * @param array|string $array array or string
     * @return string json value
     */
    private function convert($array): string
    {
        if (!is_array($array)) {
            return json_encode(['echo' => $array]);
        }

        $array['echo'] = $array['echo'] ?? false;
        $array['redirect'] = $array['redirect'] ?? false;
        return json_encode($array);
    }

}
