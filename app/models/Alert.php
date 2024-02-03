<?php

class Alert_model extends Model
{
    /**
     * Summary of table
     * 
     * @var string
     */
    public $table = 'alerts';

    /**
     * Updates alert values
     * 
     * @param int $userId The user id
     * @param int $methodId The method id
     * @param int $enabled The enabled value
     * @param string $value1 The first value
     * @param string $value2 The second value
     * @throws Exception
     * @return bool
     */
    public function set($userId, $methodId, $enabled, $value1 = '', $value2 = '')
    {
        $database = Database::openConnection();

        $database->prepare("SELECT * FROM $this->table WHERE `user_id` = :user_id AND `method_id` = :method_id LIMIT 1");
        $database->bindValue(':user_id', $userId);
        $database->bindValue(':method_id', $methodId);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        if ($database->countRows() == 0) {
            // Create new alerting setting
            $database->prepare("INSERT INTO $this->table (`user_id`, `method_id`, `enabled`, `value1`, `value2`) VALUES (:user_id, :method_id, :enabled, :value1, :value2);");
        } else {
            // Update alert setting
            $database->prepare("UPDATE $this->table SET `enabled` = :enabled, `value1` = :value1, `value2` = :value2 WHERE `user_id` = :user_id AND `method_id` = :method_id");
        }
        $database->bindValue(':user_id', $userId);
        $database->bindValue(':method_id', $methodId);
        $database->bindValue(':enabled', $enabled);
        $database->bindValue(':value1', $value1);
        $database->bindValue(':value2', $value2);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        return true;
    }

    /**
     * Get alert by user id and method id
     * 
     * @param int $userId The user id
     * @param int $methodId The method id
     * @param string $value The value
     * @throws Exception
     * @return mixed
     */
    public function get($userId, $methodId, $value = 'value1')
    {
        $database = Database::openConnection();

        $database->prepare("SELECT * FROM $this->table WHERE `user_id` = :user_id AND `method_id` = :method_id LIMIT 1");
        $database->bindValue(':user_id', $userId);
        $database->bindValue(':method_id', $methodId);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        if ($database->countRows() == 0) {
            // Fallback for unset alert settings
            if ($value == 'enabled') {
                return false;
            } else {
                return '';
            }
        }

        $alert = $database->fetch();

        return $alert[$value];
    }

    /**
     * Gets alert by method id
     * 
     * @param int $id The method id
     * @throws Exception
     * @return array
     */
    public function getAllByMethodId($id)
    {
        $database = Database::openConnection();
        $database->prepare("SELECT * FROM $this->table WHERE `method_id` = :method_id AND `enabled` = 1 ORDER BY `id` ASC");
        $database->bindValue(':method_id', $id);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        $data = $database->fetchAll();

        return $data;
    }
}
