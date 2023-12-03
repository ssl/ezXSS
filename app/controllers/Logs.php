<?php

class Logs extends Controller
{
    /**
     * Renders the logs index and returns the content.
     *
     * @return string
     */
    public function index()
    {
        $this->isAdminOrExit();

        $this->view->setTitle('Logs');
        $this->view->renderTemplate('logs/index');

        return $this->showContent();
    }
}