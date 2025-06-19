<?php

class Reports extends Controller
{
    /**
     * Summary of rows
     * 
     * @var array
     */
    private $rows = ['id', 'uri', 'ip', 'referer', 'payload', 'user-agent', 'cookies', 'localstorage', 'sessionstorage', 'dom', 'origin', 'shareid', 'extra'];

    /**
     * Redirects to all reports
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

        // Check report permissions
        if (!is_numeric($id) || !$this->hasReportPermissions($id)) {
            throw new Exception('You dont have permissions to this report');
        }

        $report = $this->model('Report')->getById($id);

        $this->renderView($report);

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

        $this->renderView($report);

        $this->log("Visited shared report page {$id}");

        return $this->showContent();
    }

    /**
     * Renders the report view
     * 
     * @param array $report The report data
     * @return void
     */
    private function renderView($report)
    {
        // Render all rows
        if(!empty($report['screenshot'] ?? '')) {
            $screenshot = strlen($report['screenshot']) === 52 ? '<img class="report-img" src="/assets/img/report-' . e($report['screenshot']) . '.png">' : '<img class="report-img" src="data:image/png;base64,' . e($report['screenshot']) . '">';
        }
        $this->view->renderData('screenshot', $screenshot ?? '', true);
        $this->view->renderCondition('hasScreenshot', !empty($screenshot));
        $this->view->renderData('time', date('F j, Y, g:i a', $report['time']));
        $this->view->renderData('browser', parseUserAgent($report['user-agent']), true);

        // Handle extra field display logic
        $extraData = $report['extra'] ?? '';
        if(empty($extraData)) {
            $this->view->renderCondition('isJsonExtra', false);
            $this->view->renderCondition('isExtra', false);
        } else {
            $decodedExtra = json_decode($extraData, true);
            
            if(json_last_error() === JSON_ERROR_NONE && (is_array($decodedExtra) || is_object($decodedExtra))) {
                $this->view->renderCondition('isExtra', false);
                $this->view->renderCondition('isJsonExtra', true);
                $extraItems = [];
                $processItem = function($data, $keyPrefix = '', $depth = 0) use (&$processItem, &$extraItems) {                    
                    if (is_array($data) || is_object($data)) {
                        if ($depth > 3) {
                            $extraItems[] = ['key' => $keyPrefix, 'value' => json_encode($data)];
                        } else {
                            foreach ($data as $key => $value) {
                                $newKey = $keyPrefix === '' ? $key : "{$keyPrefix}.{$key}";
                                $processItem($value, $newKey, $depth + 1);
                            }
                        }
                    } else {
                        $extraItems[] = ['key' => $keyPrefix, 'value' => $data];
                    }
                };
                $processItem($decodedExtra);
                $this->view->renderDataset('extraItems', $extraItems);
            } else {
                $this->view->renderCondition('isJsonExtra', false);
                $this->view->renderCondition('isExtra', true);
                $this->view->renderData('extra', $extraData);
            }
        }

        foreach ($this->rows as $value) {
            $this->view->renderData($value, $report[$value]);
        }
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
        if (!is_numeric($id) || (!in_array(+$id, $payloadList, true) && !$this->isAdmin())) {
            throw new Exception('You dont have permissions to this payload');
        }

        // Retrieve and render all payloads of user for listing
        $payloads = [];
        foreach ($payloadList as $val) {
            $name = !$val ? 'All payloads' : $this->model('Payload')->getById($val)['payload'];
            $payloads[] = ['id' => $val, 'name' => ucfirst($name), 'selected' => $val == $id ? 'selected' : ''];
        }
        if(!in_array($id, $payloadList) && $this->isAdmin()) {
            $payload = $this->model('Payload')->getById($id);
            $payloads[] = ['id' => $id, 'name' => ucfirst($payload['payload']), 'selected' => 'selected'];
        }
        $this->view->renderDataset('payload', $payloads);

        return $this->showContent();
    }

    /**
     * Returns the data of all reports
     * 
     * @return string
     */ 
    public function data()
    {
        $this->isAPIRequest();

        $id = _JSON('id');
        $archive = _JSON('archive') === 1 ? 1 : 0;

        // Check payload permissions
        $payloadList = $this->payloadList();
        if (!is_numeric($id) || (!in_array(+$id, $payloadList, true) && !$this->isAdmin())) {
            return jsonResponse('error', 'Something went wrong');
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

        foreach ($reports as $key => $value) {
            $reports[$key]['browser'] = parseUserAgent($reports[$key]['user-agent']);
            $reports[$key]['last'] = parseTimestamp($reports[$key]['time'], 'long');
        }

        return jsonResponse('data', $reports);
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
        $this->isAPIRequest();

        if (!$this->hasReportPermissions($id)) {
            return jsonResponse('error', 'You dont have permissions to this report');
        }

        $this->model('Report')->deleteById($id);

        return jsonResponse('success', 1);
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
        $this->isAPIRequest();

        if (!$this->hasReportPermissions($id)) {
            return jsonResponse('error', 'You dont have permissions to this report');
        }

        $this->model('Report')->archiveById($id);

        return jsonResponse('success', 1);
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
        $user = $this->user();
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
}