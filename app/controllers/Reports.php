<?php

class Reports extends Controller
{
    public $rows = ['id', 'uri', 'ip', 'referer', 'payload', 'user-agent', 'cookies', 'localstorage', 'sessionstorage', 'dom', 'origin', 'screenshot', 'shareid'];

    public function __construct() {
        parent::__construct();
    }

    public function index()
    {
        $this->isLoggedInOrExit();

        header('Location: /manage/reports/all');
        exit();
    }

    public function all()
    {
        return $this->list(0);
    }

    public function view($id) {
        $this->isLoggedInOrExit();
        $this->view->setTitle('Report');
        $this->view->renderTemplate('reports/view');

        $report = $this->model('Report')->getById($id);

        if(!is_numeric($id) || !$this->hasReportPermissions($id)) {
            throw new Exception('You dont have permissions to this report');
        }

        $this->checkForShareReport();

        foreach ($this->rows as $value) {
            $this->view->renderData($value, $report[$value]);
        }
        $this->view->renderData('time', date('F j, Y, g:i a', $report['time']));

        return $this->showContent();
    }

    public function share($id) {
        $this->view->setTitle('Report');
        $this->view->renderTemplate('reports/view');

        $report = $this->model('Report')->getByShareId($id);

        foreach ($this->rows as $value) {
            $this->view->renderData($value, $report[$value]);
        }
        $this->view->renderData('time', date('F j, Y, g:i a', $report['time']));

        return $this->showContent();
    }

    public function list($id)
    {
        $this->isLoggedInOrExit();
        $this->view->setTitle('Reports');
        $this->view->renderTemplate('reports/index');

        $payloadList = $this->payloadList();
        if (!is_numeric($id) || !in_array(+$id, $payloadList, true)) {
            throw new Exception('You dont have permissions to this payload');
        }

        $this->checkForShareReport();

        $payloads = [];
        foreach ($payloadList as $val) {
            $name = !$val ? 'All reports' : $this->model('Payload')->getById($val)['payload'];
            $payloads[] = ['id' => $val, 'name' => ucfirst($name), 'selected' => $val == $id ? 'selected' : ''];
        }
        $this->view->renderDataset('payload', $payloads);

        if (+$id === 0) {
            if ($this->isAdmin()) {
                // Show all reports
                $reports = $this->model('Report')->getAll();
            } else {
                // Show all reports of allowed payloads
                $reports = [];
                foreach ($payloadList as $payloadId) {
                    if($payloadId !== 0) {
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

        $archive = $this->getGetValue('archive') == '1' ? true : false;
        foreach ($reports as $key => $value) {
            $reports[$key]['uri'] = substr($reports[$key]['uri'], 0, 70);

            if( ($reports[$key]['archive'] == '0' && $archive) ||
                ($reports[$key]['archive'] == '1' && !$archive)) {
                unset($reports[$key]);
            }
        }
        $this->view->renderCondition('hasReports', count($reports) > 0);
        $this->view->renderDataset('report', $reports);

        return $this->showContent();
    }

    public function delete($id) {
        $this->isLoggedInOrExit();
        $this->validateCsrfToken();

        if(!$this->hasReportPermissions($id)) {
            throw new Exception('You dont have permissions to this report');
        }

        $this->model('Report')->deleteById($id);

        return json_encode(['true']);
    }

    public function archive($id) {
        $this->isLoggedInOrExit();
        $this->validateCsrfToken();

        if(!$this->hasReportPermissions($id)) {
            throw new Exception('You dont have permissions to this report');
        }

        $this->model('Report')->archiveById($id);

        return json_encode(['true']);
    }

    private function checkForShareReport() {
        if($this->isPost() && $this->getPostValue('reportid') !== null) {
            $id = $this->getPostValue('reportid');
            $report = $this->model('Report')->getById($id);

            if(!is_numeric($id) || !$this->hasReportPermissions($id)) {
                throw new Exception('You dont have permissions to this report');
            }

            $this->view->renderMessage('TODO');
        }
    }

    private function hasReportPermissions($id) {
        if($this->isAdmin()) {
            return true;
        }

        $user = $this->model('User')->getById($this->session->data('id'));
        $payloads = $this->model('Payload')->getAllByUserId($user['id']);

        $hasPermissions = false;
        foreach($payloads as $payload) {
            if (strpos($payload['payload'], '/') === false) {
                // Domain *
                $payload = '//' . $payload['payload'] . '/';
                if ( $payload === $report['payload'] || substr( $report['payload'], 0, strlen($payload) ) === $payload) {
                    $hasPermissions = true;
                    break;
                }
            } else {
                // Domain + path
                if ( '//' . $payload['payload'] === $report['payload']) {
                    $hasPermissions = true;
                    break;
                }
            }
        }

        if($hasPermissions) {
            return true;
        }

        return false;
    }

    private function payloadList()
    {
        $payloadList = [];
        array_push($payloadList, 0);

        $user = $this->model('User')->getById($this->session->data('id'));
        $payloads = $this->model('Payload')->getAllByUserId($user['id']);
        foreach ($payloads as $payload) {
            array_push($payloadList, $payload['id']);
        }

        return $payloadList;
    }
}
