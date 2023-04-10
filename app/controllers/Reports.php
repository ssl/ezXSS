<?php

class Reports extends Controller
{
    /**
     * Summary of rows
     * 
     * @var array
     */
    private $rows = ['id', 'uri', 'ip', 'referer', 'payload', 'user-agent', 'cookies', 'localstorage', 'sessionstorage', 'dom', 'origin', 'shareid'];

    /**
     * Redirects to all reports
     * 
     * @return never
     */
    public function index()
    {
        $this->isLoggedInOrExit();

        redirect('/manage/reports/all/');
    }

    /**
     * Returns the report view for 0 (all).
     * 
     * @return string
     */
    public function all()
    {
        return $this->list(0);
    }

    /**
     * Renders the report view and returns the content.
     * 
     * @param string $id The report id
     * @throws Exception
     * @return string
     */
    public function view($id)
    {
        $this->isLoggedInOrExit();
        $this->view->setTitle('Report');
        $this->view->renderTemplate('reports/view');

        $report = $this->model('Report')->getById($id);

        // Check report permissions
        if (!is_numeric($id) || !$this->hasReportPermissions($id)) {
            throw new Exception('You dont have permissions to this report');
        }

        // Render all rows
        $screenshot = !empty($report['screenshot']) ? '<img src="/assets/img/report-' . e($report['screenshot']) . '.png" style="max-width:100%">' : '';
        $this->view->renderData('screenshot', $screenshot, true);
        $this->view->renderData('time', date('F j, Y, g:i a', $report['time']));

        foreach ($this->rows as $value) {
            $this->view->renderData($value, $report[$value]);
        }

        return $this->showContent();
    }

    /**
     * Renders the shared report view and returns the content.
     * 
     * @param string $id The report id
     * @return string
     */
    public function share($id)
    {
        $this->view->setTitle('Report');
        $this->view->renderTemplate('reports/view');

        $report = $this->model('Report')->getByShareId($id);

        // Render all rows
        $screenshot = !empty($report['screenshot']) ? '<img src="/assets/img/report-' . e($report['screenshot']) . '.png">' : '';
        $this->view->renderData('screenshot', $screenshot, true);
        $this->view->renderData('time', date('F j, Y, g:i a', $report['time']));

        foreach ($this->rows as $value) {
            $this->view->renderData($value, $report[$value]);
        }

        $this->log("Visited shared report page {$id}");

        return $this->showContent();
    }

    /**
     * Renders the list of all reports within payload and returns the content.
     * 
     * @param string $id The payload id
     * @throws Exception
     * @return string
     */
    public function list($id)
    {
        $this->isLoggedInOrExit();
        $this->view->setTitle('Reports');
        $this->view->renderTemplate('reports/index');

        // Check payload permissions
        $payloadList = $this->payloadList();
        if (!is_numeric($id) || !in_array(+$id, $payloadList, true)) {
            throw new Exception('You dont have permissions to this payload');
        }

        // Retrieve and render all payloads of user for listing
        $payloads = [];
        foreach ($payloadList as $val) {
            $name = !$val ? 'All reports' : $this->model('Payload')->getById($val)['payload'];
            $payloads[] = ['id' => $val, 'name' => ucfirst($name), 'selected' => $val == $id ? 'selected' : ''];
        }
        $this->view->renderDataset('payload', $payloads);

        // Checks if requested id is 'all'
        if (+$id === 0) {
            if ($this->isAdmin()) {
                // Show all reports
                $reports = $this->model('Report')->getAll();
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
                        $reports = array_merge($reports, $this->model('Report')->getAllByPayload($payloadUri));
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
            $reports = $this->model('Report')->getAllByPayload($payloadUri);
        }

        // Remove or keep reports depending the requested archive value
        $archive = $this->getGetValue('archive') == '1' ? true : false;
        foreach ($reports as $key => $value) {
            $reports[$key]['uri'] = substr($reports[$key]['uri'], 0, 85);
            $reports[$key]['ip'] = substr($reports[$key]['ip'], 0, 25);
            $reports[$key]['payload'] = substr($reports[$key]['payload'], 0, 50);

            if (($reports[$key]['archive'] == '0' && $archive) ||
                ($reports[$key]['archive'] == '1' && !$archive)
            ) {
                unset($reports[$key]);
            }
        }
        $this->view->renderCondition('hasReports', count($reports) > 0);
        $this->view->renderDataset('report', $reports);

        return $this->showContent();
    }

    /**
     * Deletes a report
     * 
     * @param string $id The report id
     * @throws Exception
     * @return string
     */
    public function delete($id)
    {
        $this->isLoggedInOrExit();
        $this->validateCsrfToken();

        if (!$this->hasReportPermissions($id)) {
            throw new Exception('You dont have permissions to this report');
        }

        $this->model('Report')->deleteById($id);

        return json_encode(['true']);
    }

    /**
     * (Un)Archives a report
     * 
     * @param string $id The report id
     * @throws Exception
     * @return string
     */
    public function archive($id)
    {
        $this->isLoggedInOrExit();
        $this->validateCsrfToken();

        if (!$this->hasReportPermissions($id)) {
            throw new Exception('You dont have permissions to this report');
        }

        $this->model('Report')->archiveById($id);

        return json_encode(['true']);
    }

    /**
     * Checks if user is allowed to view report
     * 
     * @param string $id The report id
     * @return bool
     */
    private function hasReportPermissions($id)
    {
        if ($this->isAdmin()) {
            return true;
        }

        // Get data about report and payloads of user
        $report = $this->model('Report')->getById($id);
        $user = $this->model('User')->getById($this->session->data('id'));
        $payloads = $this->model('Payload')->getAllByUserId($user['id']);

        // Check all payloads if it correspondents to report 
        foreach ($payloads as $payload) {
            if (strpos($payload['payload'], '/') === false) {
                // Check for domain
                $payload = '//' . $payload['payload'] . '/';
                if ($payload === $report['payload'] || substr($report['payload'], 0, strlen($payload)) === $payload) {
                    return true;
                }
            } else {
                // Check for domain + path
                if ('//' . $payload['payload'] === $report['payload']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Returns the payload list array
     * 
     * @return array
     */
    private function payloadList()
    {
        $payloadList = [];
        // '0' correspondents to 'all'
        array_push($payloadList, 0);

        // Push all payloads of user to list
        $user = $this->model('User')->getById($this->session->data('id'));
        $payloads = $this->model('Payload')->getAllByUserId($user['id']);
        foreach ($payloads as $payload) {
            array_push($payloadList, $payload['id']);
        }

        return $payloadList;
    }
}