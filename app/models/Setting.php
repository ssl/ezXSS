<?php

class Setting_model extends Model
{

    public $table = 'settings';

    public function getAll()
    {
        $database = Database::openConnection();
        $database->getAll($this->table);

        $data = $database->fetchAll();

        return $data;
    }

    public function get($setting)
    {
        $database = Database::openConnection();

        $database->prepare('SELECT * FROM `settings` WHERE setting = :setting LIMIT 1');
        $database->bindValue(':setting', $setting);

        if (!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        if ($database->countRows() == 0) {
            throw new Exception("Setting not found");
        }

        $setting = $database->fetch();

        return $setting['value'];
    }
}
