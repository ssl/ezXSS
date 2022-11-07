<?php

class Users extends Controller
{

    public $ranks = [
        0 => 'Banned',
        1 => 'User',
        7 => 'Admin'
    ];

    /**
     * Account index. This holds all pastes by the user
     *
     * @return string
     */
    public function index()
    {
        $this->isAdminOrExit();
        $this->view->setTitle('Users');
        $this->view->renderTemplate('users/index');

        if ($this->isPOST()) {
            try {
                $this->validateCsrfToken();

                $username = $this->getPostValue('username');
                $password = $this->getPostValue('password');
                $rank = intval($this->getPostValue('rank'));

                if (!isset($this->ranks[$rank])) {
                    throw new Exception("Invalid rank");
                }

                $this->model('User')->create($username, $password, $rank);
            } catch (Exception $e) {
                $this->view->renderMessage($e->getMessage());
            }
        }

        $users = $this->model('User')->getAllUsers();
        foreach ($users as $key => $value) {
            $users[$key]['rank'] = $this->ranks[intval($users[$key]['rank'])];
        }
        $this->view->renderDataset('user', $users);

        return $this->view->showContent();
    }
}
