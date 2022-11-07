<?php

class Settings extends Controller
{


    public function index()
    {
        $this->isAdminOrExit();

        $this->view->setTitle('Settings');
        $this->view->renderTemplate('settings/index');

        return $this->view->showContent();
    }
}
