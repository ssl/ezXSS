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
}
