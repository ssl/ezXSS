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
                        $this->ezXSS3migrate();
                    } else {
                        throw new Exception('Please first update to 3.10 before migrating to 4.x');
                    }
                }

                header('Location: dashboard/index');
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
        // Store old password
        $currentPassword = $this->model('Setting')->get('password');

        // Update the database tables and rows
        $sql = file_get_contents(__DIR__ . '/../../ezXSS3migrate.sql');
        $database = Database::openConnection();
        $database->exec($sql);
        $database->exec('ALTER DATABASE `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;');
        
        $this->model('Setting')->set('version', version);

        // Create new user and update password to old password
        $user = $this->model('User')->create('admin', 'Temp1234!', 7);
        $this->model('User')->updatePassword($user['id'], $currentPassword, true);
    }
}
