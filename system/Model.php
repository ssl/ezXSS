<?php

class Model
{
    /**
     * Table
     * 
     * @var mixed
     */
    public $table = null;


    /**
     * Fixes table var
     */
    public function __construct()
    {
        if ($this->table === null) {
            $this->table = strtolower(get_class($this));
        }
    }

    /**
     * Checks if row with id exists
     *
     * @param int $id The row id
     * @return bool
     */
    public function exists($id)
    {
        $database = Database::openConnection();
        $database->getById($this->table, $id);

        return $database->countRows() === 1;
    }

    /**
     * Get by id
     * 
     * @param int $id The id
     * @throws Exception
     * @return array
     */
    public function getById($id)
    {
        $database = Database::openConnection();
        $database->getById($this->table, $id);

        if ($database->countRows() === 0) {
            throw new Exception('Not found');
        }

        $report = $database->fetch();

        return $report;
    }

    /**
     * Delete by id
     * 
     * @param string $id The id
     * @throws Exception
     * @return bool
     */
    public function deleteById($id)
    {
        $database = Database::openConnection();
        $database->deleteById($this->table, $id);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        return true;
    }

     /**
     * Get compress status
     * 
     * @return bool
     */
    public function getCompressStatus()
    {
        try {
            $database = Database::openConnection();
            $database->prepare("SELECT value FROM settings WHERE setting = 'compress' LIMIT 1");
            $database->execute();
            $status = $database->fetch();

            return $status['value'] == 1 ? 1 : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
}
