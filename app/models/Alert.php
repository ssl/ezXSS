<?php

class Alert_model extends Model
{

    public $table = 'alerts';


    public function set($userId, $methodId, $enabled, $value1 = '', $value2 = '')
    {
        $database = Database::openConnection();

        $database->prepare('SELECT * FROM `alerts` WHERE user_id = :user_id AND method_id = :method_id LIMIT 1');
        $database->bindValue(':user_id', $userId);
        $database->bindValue(':method_id', $methodId);

        if (!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        if ($database->countRows() == 0) {
            // Create new alerting setting
            $database->prepare('INSERT INTO `alerts` (`user_id`, `method_id`, `enabled`, `value1`, `value2`) VALUES (:user_id, :method_id, :enabled, :value1, :value2);');
        } else {
            // Update
            $database->prepare('UPDATE `alerts` SET `enabled` = :enabled, `value1` = :value1, `value2` = :value2 WHERE `user_id` = :user_id AND `method_id` = :method_id');
        }
        $database->bindValue(':user_id', $userId);
        $database->bindValue(':method_id', $methodId);
        $database->bindValue(':enabled', $enabled);
        $database->bindValue(':value1', $value1);
        $database->bindValue(':value2', $value2);

        if (!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        return true;
    }

    public function get($userId, $methodId, $value = 'value1')
    {
        $database = Database::openConnection();

        $database->prepare('SELECT * FROM `alerts` WHERE `user_id` = :user_id AND `method_id` = :method_id LIMIT 1');
        $database->bindValue(':user_id', $userId);
        $database->bindValue(':method_id', $methodId);

        if (!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
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
}
