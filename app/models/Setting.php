<?php

class Setting_model extends Model
{
    /**
     * Summary of table
     * 
     * @var string
     */
    public $table = 'settings';

    /**
     * Get all settings
     * 
     * @return array
     */
    public function getAll()
    {
        $database = Database::openConnection();
        $database->getAll($this->table);

        $data = $database->fetchAll();

        return $data;
    }

    /**
     * Set setting value
     * 
     * @param string $setting The setting name
     * @param string $value The new value
     * @throws Exception
     * @return bool
     */
    public function set($setting, $value)
    {
        $database = Database::openConnection();

        $database->prepare("UPDATE $this->table SET value = :value WHERE `setting` = :setting");
        $database->bindValue(':setting', $setting);
        $database->bindValue(':value', $value);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        return true;
    }

    /**
     * Get setting value
     * 
     * @param string $setting The setting name
     * @throws Exception
     * @return mixed
     */
    public function get($setting)
    {
        $database = Database::openConnection();

        $database->prepare("SELECT * FROM $this->table WHERE `setting` = :setting LIMIT 1");
        $database->bindValue(':setting', $setting);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        if ($database->countRows() == 0) {
            throw new Exception('Setting not found');
        }

        $setting = $database->fetch();

        return $setting['value'];
    }
}
