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
            throw new Exception("Something unexpected went wrong");
        }

        return true;
    }
}
