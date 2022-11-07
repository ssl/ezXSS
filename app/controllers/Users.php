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
            
            $payloads = $this->model('Payload')->getAllByUserId($users[$key]['id']);
            $payloadString = $users[$key]['rank'] == 'Admin' ? '*, ' : '';
            foreach($payloads as $payload) {
                $payloadString .= e($payload['payload']) . ', ';
            }
            $payloadString = $payloadString === '' ? $payloadString : substr($payloadString, 0, -2);
            $payloadString = (strlen($payloadString) > 50) ? substr($payloadString,0,50).'...' : $payloadString;
            $users[$key]['payloads'] = $payloadString;
        }
        $this->view->renderDataset('user', $users);

        return $this->showContent();
    }

    public function edit($id)
    {
        $this->isAdminOrExit();
        $this->view->setTitle('Edit User');
        $this->view->renderTemplate('users/edit');

        $userModel = $this->model('User');
        if ($this->isPOST()) {
            try {
                $user = $userModel->getById($id);

                // Editing user
                if ($this->getPostValue('edit') !== null) {
                    $username = $this->getPostValue('username');
                    $password = $this->getPostValue('password');
                    $rank = intval($this->getPostValue('rank'));

                    if ($user['id'] == $this->session->data('id')) {
                        throw new Exception("Can't edit your own user");
                    }

                    if ($password != '') {
                        $userModel->updatePassword($user['id'], $password);
                    }

                    if ($username !== $user['username']) {
                        $userModel->updateUsername($user['id'], $username);
                    }

                    if (!isset($this->ranks[$rank])) {
                        throw new Exception("Invalid rank");
                    }
                    $userModel->updateRank($user['id'], $rank);
                }

                // Add payload
                if ($this->getPostValue('add') !== null) {
                    $payload = $this->getPostValue('payload');

                    if (strpos($payload, 'http://') === 0 || strpos($payload, 'https://') === 0) {
                        throw new Exception("Payload needs to be in format without http://");
                    }

                    $this->model('Payload')->add($user['id'], $payload);
                }
            } catch (Exception $e) {
                $this->view->renderMessage($e->getMessage());
            }
        }

        try {
            $user = $userModel->getById($id);
            $this->view->renderData('username', $user['username']);

            $this->view->renderData('rankOptions', $this->rankOptions($user['rank']), true);

            $payloads = $this->model('Payload')->getAllByUserId($user['id']);
            $this->view->renderDataset('payload', $payloads);
        } catch (Exception $e) {
            return $this->view->renderErrorPage($e->getMessage());
        }

        return $this->showContent();
    }

    public function delete($id)
    {
        $this->isAdminOrExit();
        $this->view->setTitle('Delete User');
        $this->view->renderTemplate('users/delete');

        $user = $this->model('User')->getById($id);
        $this->view->renderData('username', $user['username']);

        if ($this->isPOST()) {
            $this->validateCsrfToken();

            if ($user['id'] == $this->session->data('id')) {
                throw new Exception("Can't delete your own account");
            }

            $this->model('User')->deleteById($id);
            header('Location: /manage/users');
        }

        return $this->showContent();
    }

    public function deletePayload($id)
    {
        $this->isAdminOrExit();
        $this->validateCsrfToken();

        $this->model('Payload')->getById($id);
        $this->model('Payload')->deleteById($id);

        return json_encode(['true']);
    }

    private function rankOptions($default = 0)
    {
        $html = '';
        foreach ($this->ranks as $id => $name) {
            $selected = $id == $default ? 'selected' : '';
            $html .= '<option ' . $selected . ' value="' . $id . '">' . $name . '</option>';
        }
        return $html;
    }
}
