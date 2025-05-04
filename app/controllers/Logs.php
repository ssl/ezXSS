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

    /**
     * Renders the logs content and returns the content.
     * 
     * @return string
     */
    public function data()
    {
        $this->isAPIRequest();

        if (!$this->isAdmin()) {
            return jsonResponse('error', 'You dont have permissions to this page');
        }

        $logs = $this->model('Log')->getAll();
        $allUsers = $this->model('User')->getAllUsers();
        
        // Create a map of user IDs to their usernames
        $userMap = [];
        foreach ($allUsers as $user) {
            $userMap[$user['id']] = $user['username'];
        }

        foreach ($logs as $key => $value) {
            if ($logs[$key]['user_id'] !== 0) {
                $logs[$key]['user'] = isset($userMap[$logs[$key]['user_id']]) 
                    ? $userMap[$logs[$key]['user_id']] 
                    : 'Deleted user';
            } else {
                $logs[$key]['user'] = 'Not logged in';
            }
            $logs[$key]['date'] = date('F j, Y, g:i a', $logs[$key]['time']);
        }

        return jsonResponse('data', $logs);
    }

    /**
     * Renders the user logs content and returns the content.
     * 
     * @return string
     */
    public function users()
    {
        $this->isAPIRequest();

        if (!$this->isAdmin()) {
            return jsonResponse('error', 'You dont have permissions to this page');
        }

        $id = _JSON('id');

        $logs = $this->model('Log')->getByUserId($id);

        foreach ($logs as $key => $value) {
            $logs[$key]['date'] = date('F j, Y, g:i a', $logs[$key]['time']);
        }

        return jsonResponse('data', $logs);
    }
}