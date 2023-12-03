<?php

class Update extends Controller
{
    /**
     * Renders the update index and returns the content.
     *
     * @return string
     */
    public function index()
    {
        $this->view->setTitle('Update');
        $this->view->renderTemplate('system/update');

        // Make sure the platform is not already up-to-date
        $version = $this->model('Setting')->get('version');
        if ($version === version) {
            throw new Exception('ezXSS is already up-to-date');
        }

        if ($this->isPOST()) {
            try {
                $this->validateCsrfToken();

                // Check if the version is 3.x and is valid for migration
                if (preg_match('/^3\./', $version)) {
                    if ($version == '3.10' || $version == '3.11') {
                        if (version !== '4.0') {
                            throw new Exception('Please first update to 4.0 before migrating 3.x to 4.x');
                        }
                        $this->ezXSS3migrate();
                    } else {
                        throw new Exception('Please first update to 3.10 before migrating to 4.x');
                    }
                }

                if ($version == '4.0' && version === '4.1') {
                    // Update the database tables and rows
                    $sql = file_get_contents(__DIR__ . '/../../ezXSS4.1.sql');
                    $database = Database::openConnection();
                    $database->exec($sql);

                    $this->model('Setting')->set('version', version);
                }

                // Future updates come here!

                redirect('dashboard/index');
                exit();
            } catch (Exception $e) {
                $this->view->renderMessage($e->getMessage());
            }
        }

        return $this->showContent();
    }

    /**
     * Migrate ezXSS 3 database to ezXSS 4
     * 
     * @return void
     */
    private function ezXSS3migrate()
    {
        // Store old data
        $password = $this->model('Setting')->get('password');
        $notepad = $this->model('Setting')->get('notepad');

        // Update the database tables and rows
        $sql = file_get_contents(__DIR__ . '/../../ezXSS3migrate.sql');
        $database = Database::openConnection();
        $database->exec($sql);
        $database->exec('ALTER DATABASE `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;');

        $this->model('Setting')->set('version', version);

        // Create new user and update old 
        $user = $this->model('User')->create('admin', 'Temp1234!', 7);
        $this->model('User')->setPassword($user['id'], $password, true);

        $this->model('Payload')->add($user['id'], host);

        // Add note
        $this->model('Setting')->set('notepad', "Great! U have updated to ezXSS 4!\n\nA lot of things have changed, and some settings like your alerts and payloads needs to be re-done in other to make everything work correct again.\n\nPlease visit the Github wiki for help on github.com/ssl/ezXSS/wiki\n\n" . $notepad);

        // Update all oldskool 'collected pages' and NULL payloads
        $reports = $this->model('Report')->getAllInvalid();
        foreach ($reports as $report) {
            // Set payload to current host
            $this->model('Report')->setSingleValue($report['id'], 'payload', '//' . host . '/');

            // Set refer to collected if collected is set
            if (strpos($report['payload'], 'Collected page via ') === 0) {
                $this->model('Report')->setSingleValue($report['id'], 'referer', $report['payload']);
            }
        }
    }
}
