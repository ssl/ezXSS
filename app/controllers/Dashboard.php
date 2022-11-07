<?php

class Dashboard extends Controller
{

    /**
     * User dashboard
     *
     * @return string
     */
    public function my()
    {
        $this->isLoggedInOrExit();

        $this->view->setTitle('Account');
        $this->view->renderTemplate('dashboard/my');

        return $this->view->showContent();
    }

    /**
     * Admin dashboard
     *
     * @return string
     */
    public function index()
    {
        $this->isAdminOrExit();

        $this->view->setTitle('Account');
        $this->view->renderTemplate('dashboard/index');


        try {
            $ch = curl_init('https://status.ezxss.com/?v=' . version);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: ezXSS']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            $release = json_decode(curl_exec($ch), true);
        } catch (Exception $e) {
            $release = [['?', '?', '?']];
        }

        $this->view->renderData('repoVersion', $release[0]['release']);
        $this->view->renderData('repoBody', $release[0]['body']);
        $this->view->renderData('repoUrl', $release[0]['zipball_url']);

        $this->view->renderData('notepad', $this->model('Setting')->get('notepad'));

        return $this->showContent();
    }

    public function statistics()
    {
        $this->isAdminOrExit();
        $this->validateCsrfToken();

        $statistics = ['total' => 0, 'week' => 0, 'totaldomains' => 0, 'weekdomains' => 0, 'collected' => 0, 'last' => 'never'];
        $allReports = $this->model('Report')->getAllStaticticsData();
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
}
