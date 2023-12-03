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

            if (!is_string($alertId) || !isset($alertIds[$alertId])) {
                throw new Exception('Invalid alert');
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
            return $this->showEcho('This does not look like a valid Telegram bot token');
        }

        // Get last chat from bot
        $api = curl_init("https://api.telegram.org/bot{$bottoken}/getUpdates");
        curl_setopt($api, CURLOPT_RETURNTRANSFER, true);
        $results = json_decode(curl_exec($api), true);

        // Check if result is OK
        if ($results['ok'] !== true) {
            return $this->showEcho('Something went wrong, your bot token is probably invalid');
        }

        // Check if result contains any chat
        if (isset($results['result'][0]['message']['chat']['id'])) {
            return $this->showEcho('chatId:' . $results['result'][0]['message']['chat']['id']);
        }

        // No recent chat found
        return $this->showEcho('Your bot token seems valid, but I cannot find a chat. Start a chat with your bot by sending /start');
    }

    /**
     * Retrieves top 10 most common report value
     * 
     * @return string
     */
    public function getMostCommon()
    {
        $id = $this->getPostValue('id');
        $row = $this->getPostValue('row');
        $admin = $this->getPostValue('admin') === '1';
        $data = [];
        $results = [];

        // Validate if admin
        if ($admin && !$this->isAdmin()) {
            return $this->showEcho('Something went wrong');
        }

        // Validate if id and row is correct
        if (!in_array($id, [1, 2, 3, 4, 5]) || !in_array($row, [1, 2])) {
            return $this->showEcho('Something went wrong');
        }

        // Save row/id combination of user
        if ($row === '1') {
            $this->model('User')->setRow1($this->session->data('id'), $id);
        } else {
            $this->model('User')->setRow2($this->session->data('id'), $id);
        }

        if ($admin) {
            $allReports = $this->model('Report')->getAllCommonData();
        } else {
            $user = $this->model('User')->getById($this->session->data('id'));
            $payloads = $this->model('Payload')->getAllByUserId($user['id']);
            $allReports = [];

            // Merge all reports that belong to user
            foreach ($payloads as $payload) {
                $payloadUri = '//' . $payload['payload'];
                if (strpos($payload['payload'], '/') === false) {
                    $payloadUri .= '/%';
                }
                $allReports = array_merge($allReports, $this->model('Report')->getAllCommonDataByPayload($payloadUri));
            }
        }

        $rows = [1 => 'origin', 2 => 'ip', 4 => 'user-agent', 5 => 'payload'];
        if (in_array($id, [1, 2, 4, 5])) {
            // Loop through the reports and count dublicates
            foreach ($allReports as $report) {
                if ($rows[$id] == 'user-agent') {
                    $value = $this->parseUserAgent($report[$rows[$id]]);
                } else {
                    $value = $report[$rows[$id]];
                }

                if (isset($results[$value])) {
                    $results[$value]++;
                } else {
                    $results[$value] = 1;
                }
            }
        }

        // Get top cookies
        if ($id === '3') {
            $cookies = [];
            // Loop through the cookies of each report and parse them as seperate cookies
            foreach ($allReports as $cookie) {
                $foundCookies = $this->parseCookies($cookie['cookies']);
                foreach ($foundCookies as $foundCookie) {
                    if ($foundCookie !== '') {
                        $cookies[] = $foundCookie;
                    }
                }
            }

            // Count dublicate cookies
            foreach ($cookies as $cookie) {
                if (isset($results[$cookie])) {
                    $results[$cookie]++;
                } else {
                    $results[$cookie] = 1;
                }
            }
        }

        // Create top 10 of most common
        arsort($results);
        foreach ($results as $value => $count) {
            $data[] = ["value" => $value, "count" => $count];
            if (count($data) === 10) {
                break;
            }
        }

        return json_encode($data);
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
            'total' => count($allReports),
            'week' => 0,
            'weekdomains' => 0,
            'totaldomains' => 0,
            'collected' => 0,
            'last' => 'never',
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
        $statistics['last'] = $this->parseTimestamp($lastReport !== false ? $lastReport['time'] : 0);

        return json_encode($statistics);
    }

    /**
     * Parses cookies string as single cookies array
     * 
     * @param string $cookies The cookies string
     * @return array
     */
    private function parseCookies($cookies)
    {
        // Split the string on the ';' character to get an array of cookie strings
        $cookieArray = explode(';', $cookies ?? '');
        $cookieNames = [];

        // Iterate over the array of cookie strings
        foreach ($cookieArray as $cookie) {
            $nameValue = explode('=', $cookie);
            $cookieName = $nameValue[0];
            $cookieName = trim($cookieName);

            // Add the cookie name to the array
            $cookieNames[] = $cookieName;
        }

        // Return the array of cookie names
        return $cookieNames;
    }

    /**
     * Renders the list of all reports within payload and returns the content.
     * 
     * @return string
     */
    public function reports()
    {
        $id = $this->getPostValue('id');
        $archive = $this->getPostValue('archive') === '1' ? 1 : 0;

        // Check payload permissions
        $payloadList = $this->payloadList();
        if (!is_numeric($id) || !in_array(+$id, $payloadList, true)) {
            return $this->showEcho('Something went wrong');
        }

        // Checks if requested id is 'all'
        if (+$id === 0) {
            if ($this->isAdmin()) {
                // Show all reports
                $reports = $this->model('Report')->getAllByArchive($archive);
            } else {
                // Show all reports of allowed payloads
                $reports = [];
                foreach ($payloadList as $payloadId) {
                    if ($payloadId !== 0) {
                        $payload = $this->model('Payload')->getById($payloadId);
                        $payloadUri = '//' . $payload['payload'];
                        if (strpos($payload['payload'], '/') === false) {
                            $payloadUri .= '/%';
                        }
                        $reports = array_merge($reports, $this->model('Report')->getAllByPayload($payloadUri, $archive));
                    }
                }
            }
        } else {
            // Show reports of payload
            $payload = $this->model('Payload')->getById($id);

            $payloadUri = '//' . $payload['payload'];
            if (strpos($payload['payload'], '/') === false) {
                $payloadUri .= '/%';
            }
            $reports = $this->model('Report')->getAllByPayload($payloadUri, $archive);
        }

        return json_encode(["data" => array_values($reports)]);
    }

    /**
     * Renders the list of all sessions within payload and returns the content.
     * 
     * @return string
     */
    public function sessions()
    {
        $id = $this->getPostValue('id');

        // Check payload permissions
        $payloadList = $this->payloadList();
        if (!is_numeric($id) || !in_array(+$id, $payloadList, true)) {
            throw new Exception('You dont have permissions to this payload');
        }

        // Checks if requested id is 'all'
        if (+$id === 0) {
            if ($this->isAdmin()) {
                // Show all sessions
                $sessions = $this->model('Session')->getAll();
            } else {
                // Show all sessions of allowed payloads
                $sessions = [];
                foreach ($payloadList as $payloadId) {
                    if ($payloadId !== 0) {
                        $payload = $this->model('Payload')->getById($payloadId);
                        $payloadUri = '//' . $payload['payload'];
                        if (strpos($payload['payload'], '/') === false) {
                            $payloadUri .= '/%';
                        }
                        $sessions = array_merge($sessions, $this->model('Session')->getAllByPayload($payloadUri));
                    }
                }
            }
        } else {
            // Show sessions of payload
            $payload = $this->model('Payload')->getById($id);

            $payloadUri = '//' . $payload['payload'];
            if (strpos($payload['payload'], '/') === false) {
                $payloadUri .= '/%';
            }
            $sessions = $this->model('Session')->getAllByPayload($payloadUri);
        }

        foreach ($sessions as $key => $value) {
            $sessions[$key]['browser'] = $this->parseUserAgent($sessions[$key]['user-agent']);
            $sessions[$key]['last'] = $this->parseTimestamp($sessions[$key]['time'], 'long');
            $sessions[$key]['shorturi'] = substr($sessions[$key]['uri'], 0, 50);
        }

        return json_encode(["data" => array_values($sessions)]);
    }

    /**
     * Renders the logs content and returns the content.
     * 
     * @return string
     */
    public function logs()
    {
        $this->isAdminOrExit();

        $logs = $this->model('Log')->getAll();

        foreach ($logs as $key => $value) {
            if ($logs[$key]['user_id'] !== 0) {
                try {
                    $user = $this->model('User')->getById($logs[$key]['user_id']);
                    $logs[$key]['user'] = $user['username'];
                } catch (Exception $e) {
                    $logs[$key]['user'] = 'Deleted user';
                }
            } else {
                $logs[$key]['user'] = 'Not logged in';
            }
            $logs[$key]['date'] = date('F j, Y, g:i a', $logs[$key]['time']);
        }

        return json_encode(["data" => array_values($logs)]);
    }

    /**
     * Renders the users content and returns the content.
     * 
     * @return string
     */
    public function users()
    {
        $this->isAdminOrExit();
        
        $ranks = [0 => 'Banned', 1 => 'User', 7 => 'Admin'];

        $users = $this->model('User')->getAllUsers();

        foreach ($users as &$user) {
            // Translate rank id to readable name
            $user['rank'] = $ranks[$user['rank']];

            // Create list of all payloads of user
            $payloads = $this->model('Payload')->getAllByUserId($user['id']);
            $payloadString = $user['rank'] == 'Admin' ? '*, ' : '';
            foreach ($payloads as $payload) {
                $payloadString .= e($payload['payload']) . ', ';
            }
            $payloadString = $payloadString === '' ? $payloadString : substr($payloadString, 0, -2);
            $payloadString = (strlen($payloadString) > 35) ? substr($payloadString, 0, 35) . '...' : $payloadString;
            $user['payloads'] = $payloadString;
        }

        return json_encode(["data" => array_values($users)]);
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
