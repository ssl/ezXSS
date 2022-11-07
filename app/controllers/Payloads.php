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

        return $this->view->getContent();
    }

    /**
     * Custom payloads
     *
     * @return string
     */
    public function custom($name)
    {

        // todo: Check if custom exists

        $this->view->renderPayload($name);
        $this->view->setContentType('application/x-javascript');

        return $this->view->getContent();
    }

    /**
     * Callback function
     *
     * @return string
     */
    public function callback()
    {
        return 'github.com/ssl/ezXSS';
    }
}
