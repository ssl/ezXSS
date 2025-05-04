<?php

class Dashboard extends Controller
{
    /**
     * Renders the users dashboard and returns the content.
     *
     * @return string
     */
    public function my()
    {
        $this->isLoggedInOrExit();
        $this->view->setTitle('My Dashboard');
        $this->view->renderTemplate('dashboard/my');

        // Render the correct 'selected' box in the 2 rows
        $user = $this->user();
        foreach (['1', '2'] as $row) {
            for ($i = 1; $i <= 5; $i++) {
                $this->view->renderData("common_{$row}_{$i}", $user['row' . $row] == $i ? 'selected' : '');
            }
        }

        // Set and render notepad value
        if (isPOST()) {
            $this->validateCsrfToken();
            $this->model('User')->setNotepad($user['id'], _POST('notepad'));
            $user['notepad'] = _POST('notepad');
        }
        $this->view->renderData('notepad', $user['notepad']);

        return $this->showContent();
    }

    /**
     * Renders the admin dashboard and returns the content.
     *
     * @return string
     */
    public function index()
    {
        $this->isAdminOrExit();
        $this->view->setTitle('Dashboard');
        $this->view->renderTemplate('dashboard/index');

        // Render the correct 'selected' box in the 2 rows
        $user = $this->user();
        foreach (['1', '2'] as $row) {
            for ($i = 1; $i <= 5; $i++) {
                $this->view->renderData("common_{$row}_{$i}", $user['row' . $row] == $i ? 'selected' : '');
            }
        }

        // Set and render notepad value
        if (isPOST()) {
            $this->validateCsrfToken();
            $this->model('Setting')->set('notepad', _POST('notepad'));
        }
        $this->view->renderData('notepad', $this->model('Setting')->get('notepad'));

        // Check ezXSS updates
        try {
            $ch = curl_init('https://status.ezxss.com/?v=' . version);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: ezXSS']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            $release = json_decode(curl_exec($ch), true);
        } catch (Exception $e) {
            $release = [['release' => '?', 'body' => 'Error loading', 'zipball_url' => '?']];
        }
        $this->view->renderData('repoVersion', $release[0]['release'] ?? '?');
        $this->view->renderData('repoBody', $release[0]['body'] ?? 'Error loading');
        $this->view->renderData('repoUrl', $release[0]['zipball_url'] ?? '?');

        return $this->showContent();
    }

    /**
     * Retrieves top 10 most common report value
     * 
     * @return string
     */
    public function mostCommon()
    {
        $this->isAPIRequest();

        $id = _JSON('id');
        $row = _JSON('row');
        $admin = _JSON('admin');
        $data = [];
        $results = [];

        // Validate if admin
        if ($admin && !$this->isAdmin()) {
            return jsonResponse('error', 'Something went wrong');
        }

        // Validate if id and row is correct
        if (!in_array($id, [1, 2, 3, 4, 5]) || !in_array($row, [1, 2])) {
            return jsonResponse('error', 'Something went wrong');
        }

        // Save row/id combination of user
        if ($row === 1) {
            $this->model('User')->setRow1($this->session->data('id'), $id);
        } else {
            $this->model('User')->setRow2($this->session->data('id'), $id);
        }

        if ($admin) {
            $allReports = $this->model('Report')->getAllCommonData();
        } else {
            $user = $this->user();
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
                    $value = parseUserAgent($report[$rows[$id]]);
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
        if ($id === 3) {
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
            $data[] = ['value' => $value, 'count' => $count];
            if (count($data) === 10) {
                break;
            }
        }

        return jsonResponse('array', $data);
    }

    /**
     * Generates statictics about reports
     * 
     * @return string
     */
    public function statistics()
    {
        $this->isAPIRequest();

        $allReports = [];
        $allSessions = [];

        // Check if requested statistics is for admin or user
        if (_JSON('page') === 'dashboard') {
            if (!$this->isAdmin()) {
                return jsonResponse('error', 'Something went wrong');
            }
            $allReports = $this->model('Report')->getAllStaticticsData();
            $allSessions = $this->model('Session')->getAllStaticticsData();
        } else {
            $user = $this->user();
            $payloads = $this->model('Payload')->getAllByUserId($user['id']);

            // Merge all reports that belong to user
            foreach ($payloads as $payload) {
                $payloadUri = '//' . $payload['payload'];
                if (strpos($payload['payload'], '/') === false) {
                    $payloadUri .= '/%';
                }
                $allReports = array_merge($allReports, $this->model('Report')->getAllStaticticsDataByPayload($payloadUri));
                $allSessions = array_merge($allSessions, $this->model('Session')->getAllStaticticsDataByPayload($payloadUri));
                usort($allReports, function($a, $b) { return $a['time'] - $b['time']; });
                usort($allSessions, function($a, $b) { return $a['time'] - $b['time']; });
            }
        }

        $statistics = [
            'total' => count($allReports),
            'week' => 0,
            'weekdomains' => 0,
            'totaldomains' => 0,
            'collected' => 0,
            'last' => 'never',
            'sessionrequests' => count($allSessions),
            'sessionclients' => 0,
            'totalsessiondomains' => 0,
            'weekrequests' => 0,
            'weekclients' => 0,
            'lastclient' => 'never',
        ];

        // Reports data
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

        // Session data
        $uniqueDomains = [];
        $uniqueClients = [];
        $uniqueClientsWeek = [];
        foreach ($allSessions as $session) {
            // Counts requests from last week
            if ($session['time'] > time() - 604800) {
                $statistics['weekrequests']++;

                // Counts unique clients from last week
                if (!in_array($session['clientid'], $uniqueClientsWeek, true)) {
                    $uniqueClientsWeek[] = $session['clientid'];
                    $statistics['weekclients']++;
                }
            }

            // Counts unique clients
            if (!in_array($session['clientid'], $uniqueClients, true)) {
                $uniqueClients[] = $session['clientid'];
                $statistics['sessionclients']++;
            }

            // Counts unique domains
            if (!in_array($session['origin'], $uniqueDomains, true)) {
                $uniqueDomains[] = $session['origin'];
                $statistics['totalsessiondomains']++;
            }
        }

        // Get the time of the last report and session
        $lastReport = end($allReports);
        $statistics['last'] = parseTimestamp($lastReport !== false ? $lastReport['time'] : 0);

        $lastSession = end($allSessions);
        $statistics['lastclient'] = parseTimestamp($lastSession !== false ? $lastSession['time'] : 0);

        return jsonResponse('array', $statistics);
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
}
