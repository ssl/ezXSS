<?php

class Api extends Controller
{
    /**
     * Contains functionality that always needs to be done for these type of requests
     */
    public function __construct()
    {
        parent::__construct();

        // Set content type to json
        $this->view->setContentType('application/json');

        // Validate session
        $this->isLoggedInOrExit();
        $this->validateCsrfToken();
    }

    /**
     * Returns all enabled alerting methods to user
     * 
     * @throws Exception
     * @return bool|string
     */
    public function getAlertStatus()
    {
        $alertIds = ['1' => 'mail', '2' => 'telegram', '3' => 'slack', '4' => 'discord'];

        try {
            $alertId = $this->getPostValue('alertId');

            if (!isset($alertIds[$alertId])) {
                throw new Exception("Invalid alert");
            }

            $enabled = $this->model('Setting')->get('alert-' . $alertIds[$alertId]);
            return json_encode(['enabled' => e($enabled)]);
        } catch (Exception $e) {
            return $this->showError($e->getMessage());
        }
    }

    /**
     * Retrieves chat ID from telegram bot
     * 
     * @return string
     */
    public function getChatId()
    {
        $bottoken = $this->getPostValue('bottoken');

        // Validate bottoken string
        if (!preg_match('/^[a-zA-Z0-9:_-]+$/', $bottoken)) {
            return $this->showEcho('This does not look like an valid Telegram bot token');
        }

        // Get last chat from bot
        $api = curl_init("https://api.telegram.org/bot{$bottoken}/getUpdates");
        curl_setopt($api, CURLOPT_RETURNTRANSFER, true);
        $results = json_decode(curl_exec($api), true);

        // Check if result is OK
        if ($results['ok'] !== true) {
            return $this->showEcho('Something went wrong. Your bot token is probably invalid.');
        }

        // Check if result contains any chat
        if (isset($results['result'][0]['message']['chat']['id'])) {
            return $this->showEcho('chatId:' . $results['result'][0]['message']['chat']['id']);
        }

        // No recent chat found
        return $this->showEcho('Your bot token seems valid, but I cannot find a chat. Start a chat with your bot by sending /start');
    }

    /**
     * Generates statictics about reports
     * 
     * @return string
     */
    public function statistics()
    {
        $allReports = [];

        // Check if requested statistics is for admin or user
        if ($this->getPostValue('page') === 'dashboard') {
            $this->isAdminOrExit();
            $allReports = $this->model('Report')->getAllStaticticsData();
        } else {
            $user = $this->model('User')->getById($this->session->data('id'));
            $payloads = $this->model('Payload')->getAllByUserId($user['id']);

            // Merge all reports that belong to user
            foreach ($payloads as $payload) {
                $payloadUri = '//' . $payload['payload'];
                if (strpos($payload['payload'], '/') === false) {
                    $payloadUri .= '/%';
                }
                $allReports = array_merge($allReports, $this->model('Report')->getAllStaticticsDataByPayload($payloadUri));
            }
        }

        $statistics = [
            'total'        => count($allReports),
            'week'         => 0,
            'weekdomains'  => 0,
            'totaldomains' => 0,
            'collected'    => 0,
            'last'         => 'never',
        ];
        $uniqueDomains = [];
        $uniqueDomainsWeek = [];

        foreach ($allReports as $report) {
            // Counts report from last week
            if ($report['time'] > time() - 604800) {
                $statistics['week']++;

                // Counts unique domains from last week
                if (!in_array($report['origin'], $uniqueDomainsWeek, true)) {
                    $uniqueDomainsWeek[] = $report['origin'];
                    $statistics['weekdomains']++;
                }
            }

            // Counts unique domains
            if (!in_array($report['origin'], $uniqueDomains, true)) {
                $uniqueDomains[] = $report['origin'];
                $statistics['totaldomains']++;
            }

            // Counts amount of collected pages
            if (strpos($report['referer'], 'Collected page via ') === 0) {
                $statistics['collected']++;
            }
        }

        // Get the time of the last report
        $lastReport = end($allReports);
        if (isset($lastReport['time'])) {
            $time = time() - $lastReport['time'];
            $syntaxText = 's';
            if ($time > 60) {
                $time /= 60;
                $syntaxText = 'm';
            }
            if ($time > 60) {
                $time /= 60;
                $syntaxText = 'h';
            }
            if ($time > 24) {
                $time /= 24;
                $syntaxText = 'd';
            }
            $statistics['last'] = floor($time) . $syntaxText;
        }

        return json_encode($statistics);
    }

    /**
     * Renders error message
     * 
     * @param string $error The error message
     * @return string
     */
    private function showError($error)
    {
        return json_encode(
            [
                'error' => e($error)
            ]
        );
    }

    /**
     * Renders message from array
     * 
     * @param array $array The array
     * @return string
     */
    private function showMessage($array)
    {
        return json_encode(
            $array
        );
    }

    /**
     * Renders echo message from string
     * 
     * @param string $string The message
     * @return string
     */
    private function showEcho($string)
    {
        return json_encode(
            [
                'echo' => e($string)
            ]
        );
    }
}
