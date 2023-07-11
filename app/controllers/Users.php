<?php

class Users extends Controller
{
    /**
     * Summary of ranks
     * 
     * @var array
     */
    public $ranks = [0 => 'Banned', 1 => 'User', 7 => 'Admin'];

    /**
     * Constructor that always validates if user is admin or not
     */
    public function __construct()
    {
        parent::__construct();

        // Validate if user is admin
        $this->isAdminOrExit();
    }

    /**
     * Renders the users index and returns the content.
     *
     * @return string
     */
    public function index()
    {
        $this->view->setTitle('Users');
        $this->view->renderTemplate('users/index');

        // Check if request is trying to create user
        if ($this->isPOST()) {
            try {
                $this->validateCsrfToken();

                $username = $this->getPostValue('username');
                $password = $this->getPostValue('password');
                $rank = intval($this->getPostValue('rank'));

                // Validate rank type
                if (!isset($this->ranks[$rank])) {
                    throw new Exception("Invalid rank");
                }

                // Try to create user
                $this->model('User')->create($username, $password, $rank);
                $this->log("Created new user {$username} with rank {$rank}");
            } catch (Exception $e) {
                $this->view->renderMessage($e->getMessage());
            }
        }

        // Renders user list on page
        $users = $this->model('User')->getAllUsers();
        foreach ($users as &$user) {
            // Translate rank id to readable name
            $user['rank'] = $this->ranks[$user['rank']];

            // Create list of all payloads of user
            $payloads = $this->model('Payload')->getAllByUserId($user['id']);
            $payloadString = $user['rank'] == 'Admin' ? '*, ' : '';
            foreach ($payloads as $payload) {
                $payloadString .= e($payload['payload']) . ', ';
            }
            $payloadString = $payloadString === '' ? $payloadString : substr($payloadString, 0, -2);
            $payloadString = (strlen($payloadString) > 35) ? substr($payloadString, 0, 35) . '...' : $payloadString;
            $user['payloads'] = $payloadString;
        }
        $this->view->renderDataset('user', $users);

        return $this->showContent();
    }

    /**
     * Renders or edits the user edit and returns the content.
     * 
     * @param string $id The user id
     * @throws Exception
     * @return string
     */
    public function edit($id)
    {
        $this->view->setTitle('Edit User');
        $this->view->renderTemplate('users/edit');

        $userModel = $this->model('User');
        $user = $userModel->getById($id);

        if ($this->isPOST()) {
            try {
                $this->validateCsrfToken();

                // Check if posted data is changing alerts
                if ($this->getPostValue('edit') !== null) {
                    $username = $this->getPostValue('username');
                    $password = $this->getPostValue('password');
                    $rank = intval($this->getPostValue('rank'));

                    // Check if posted data wants to change password
                    if ($password != '') {
                        if ($user['id'] == $this->session->data('id')) {
                            throw new Exception("Can't edit your own users password here");
                        }
                        $userModel->setPassword($user['id'], $password, true);
                    }

                    // Check if posted data wants to change username
                    if ($username !== $user['username']) {
                        $userModel->setUsername($user['id'], $username);
                    }

                    // Validate and update rank
                    if (!isset($this->ranks[$rank])) {
                        throw new Exception('Invalid rank');
                    }
                    $userModel->setRank($user['id'], $rank);
                    $this->log("Eddited user {$username}");
                }

                // Check if posted data is adding payload
                if ($this->getPostValue('add') !== null) {
                    $payload = $this->getPostValue('payload');

                    // Validate payload url
                    if (strpos($payload, 'http://') === 0 || strpos($payload, 'https://') === 0 || substr($payload, 0, 1) === '/') {
                        throw new Exception("Payload needs to be in format without http://");
                    }

                    $this->model('Payload')->add($user['id'], $payload);
                }
            } catch (Exception $e) {
                $this->view->renderMessage($e->getMessage());
            }
        }

        // Render user data
        $user = $userModel->getById($id);
        $payloads = $this->model('Payload')->getAllByUserId($user['id']);
        $this->view->renderDataset('payload', $payloads);
        $this->view->renderData('username', $user['username']);
        $this->view->renderData('rankOptions', $this->rankOptions($user['rank']), true);

        return $this->showContent();
    }

    /**
     * Deletes user account
     * 
     * @param string $id The user id
     * @throws Exception
     * @return string
     */
    public function delete($id)
    {
        $this->view->setTitle('Delete User');
        $this->view->renderTemplate('users/delete');

        // Retrieve user by id
        $user = $this->model('User')->getById($id);
        $this->view->renderData('username', $user['username']);

        if ($this->isPOST()) {
            $this->validateCsrfToken();

            // Prevent deleting own user
            if ($user['id'] == $this->session->data('id')) {
                throw new Exception("Can't delete your own user");
            }

            $this->model('User')->deleteById($id);
            $this->log('Deleted user ' . $user['username']);
            redirect('/manage/users');
        }

        return $this->showContent();
    }

    /**
     * Deletes a users payload
     * 
     * @param string $id The payload id
     * @throws Exception
     * @return string
     */
    public function deletePayload($id)
    {
        $this->validateCsrfToken();

        // Check if payload is not default payload
        if (!+$id) {
            throw new Exception("Can't delete this payload");
        }

        // Delete payload
        $this->model('Payload')->getById($id);
        $this->model('Payload')->deleteById($id);

        return json_encode([1]);
    }

    /**
     * Creates and returns select box of available ranks
     * 
     * @param int $default The id of the current selected payload
     * @return string
     */
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
