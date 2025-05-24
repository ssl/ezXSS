<?php

class Logs extends Controller
{
    /**
     * Constructor that always validates if user is admin or not
     */
    public function __construct()
    {
        parent::__construct();

        $this->isAdminOrExit();
    }

    /**
     * Renders the logs index and returns the content.
     *
     * @return string
     */
    public function index()
    {
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

        $logs = $this->model('Log')->getAll();
        $allUsers = $this->model('User')->getAll();
        
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

        $id = _JSON('id');

        $logs = $this->model('Log')->getByUserId($id);

        foreach ($logs as $key => $value) {
            $logs[$key]['date'] = date('F j, Y, g:i a', $logs[$key]['time']);
        }

        return jsonResponse('data', $logs);
    }
}