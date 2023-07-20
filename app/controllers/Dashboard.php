<?php

class Dashboard extends Controller
{
    /**
     * Renders the users dashboard and returns the content.
     *
     * @return string
     */
    public function my()
    {
        $this->isLoggedInOrExit();
        $this->view->setTitle('Account');
        $this->view->renderTemplate('dashboard/my');

        // Render the correct 'selected' box in the 2 rows
        $user = $this->model('User')->getById($this->session->data('id'));
        foreach (['1', '2'] as $row) {
            for ($i = 1; $i <= 5; $i++) {
                $this->view->renderData("common_{$row}_{$i}", $user['row' . $row] == $i ? 'selected' : '');
            }
        }

        // Set and render notepad value
        if ($this->isPOST()) {
            $this->validateCsrfToken();
            $this->model('User')->setNotepad($user['id'], $this->getPostValue('notepad'));
            $user['notepad'] = $this->getPostValue('notepad');
        }
        $this->view->renderData('notepad', $user['notepad']);

        return $this->showContent();
    }

    /**
     * Renders the admin dashboard and returns the content.
     *
     * @return string
     */
    public function index()
    {
        $this->isAdminOrExit();
        $this->view->setTitle('Account');
        $this->view->renderTemplate('dashboard/index');

        // Render the correct 'selected' box in the 2 rows
        $user = $this->model('User')->getById($this->session->data('id'));
        foreach (['1', '2'] as $row) {
            for ($i = 1; $i <= 5; $i++) {
                $this->view->renderData("common_{$row}_{$i}", $user['row' . $row] == $i ? 'selected' : '');
            }
        }

        // Set and render notepad value
        if ($this->isPOST()) {
            $this->validateCsrfToken();
            $this->model('Setting')->set('notepad', $this->getPostValue('notepad'));
        }
        $this->view->renderData('notepad', $this->model('Setting')->get('notepad'));

        // Check ezXSS updates
        try {
            $ch = curl_init('https://status.ezxss.com/?v=' . version);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: ezXSS']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            $release = json_decode(curl_exec($ch), true);
        } catch (Exception $e) {
            $release = [['release' => '?', 'body' => 'Error loading', 'zipball_url' => '?']];
        }
        $this->view->renderData('repoVersion', $release[0]['release'] ?? '?');
        $this->view->renderData('repoBody', $release[0]['body'] ?? 'Error loading');
        $this->view->renderData('repoUrl', $release[0]['zipball_url'] ?? '?');

        return $this->showContent();
    }
}
