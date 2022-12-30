<?php

class Api extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->view->setContentType('application/json');
    }

    public function getAlertStatus()
    {
        $this->isLoggedInOrExit();
        $this->validateCsrfToken();

        $alertIds = [
            '1' => 'mail',
            '2' => 'telegram',
            '3' => 'slack',
            '4' => 'discord'
        ];

        try {
            $alertId = $this->getPostValue('alertId');

            if (!isset($alertIds[$alertId])) {
                throw new Exception("Invalid alert");
            }

            $enabled = $this->model('Setting')->get('alert-' . $alertIds[$alertId]);

            return json_encode(
                [
                    'enabled' => e($enabled)
                ]
            );
        } catch (Exception $e) {
            return $this->showError($e->getMessage());
        }
    }

    public function statistics()
    {
        $this->validateCsrfToken();

        $statistics = ['total' => 0, 'week' => 0, 'totaldomains' => 0, 'weekdomains' => 0, 'collected' => 0, 'last' => 'never'];

        $page = $this->getPostValue('page');
        if($page === 'dashboard') {
            $this->isAdminOrExit();
            $allReports = $this->model('Report')->getAllStaticticsData();
        } else {
            $this->isLoggedInOrExit();
    
            $user = $this->model('User')->getById($this->session->data('id'));
            $payloads = $this->model('Payload')->getAllByUserId($user['id']);

            $allReports = [];
            foreach ($payloads as $payload) {
                $payloadUri = '//' . $payload['payload'];
                if (strpos($payload['payload'], '/') === false) {
                    $payloadUri .= '/%';
                }
                $allReports = array_merge($allReports, $this->model('Report')->getAllStaticticsDataByPayload($payloadUri));
            }
        }

        $statistics['total'] = count($allReports);
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
            if (strpos($report['payload'], 'Collected page via ') === 0) {
                $statistics['collected']++;
            }
        }

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

    private function showError($error)
    {
        return json_encode(
            [
                'error' => e($error)
            ]
        );
    }

    private function showMessage($array)
    {
        return json_encode(
            $array
        );
    }
}
