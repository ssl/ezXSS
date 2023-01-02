<?php

class Payload_model extends Model
{

    public $table = 'payloads';

    public function getAll()
    {
        $database = Database::openConnection();
        $database->getAll($this->table);

        $data = $database->fetchAll();

        return $data;
    }

    public function setSingleValue($id, $column, $value) {
        $database = Database::openConnection();

        $database->prepare('UPDATE `payloads` SET `'.$column.'` = :value WHERE id = :id');
        $database->bindValue(':value', $value);
        $database->bindValue(':id', $id);
        
        if(!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        return true;
    }

    public function getAllByUserId($id)
    {
        $database = Database::openConnection();
        $database->prepare('SELECT * FROM payloads WHERE user_id = :user_id ORDER BY id ASC');
        $database->bindValue(':user_id', $id);
        $database->execute();

        $data = $database->fetchAll();

        return $data;
    }

    public function add($userId, $payload)
    {
        $database = Database::openConnection();

        $database->prepare('INSERT INTO `payloads` (`payload`, `user_id`) VALUES (:payload, :user_id);');
        $database->bindValue(':payload', $payload);
        $database->bindValue(':user_id', $userId);

        if (!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        return true;
    }

    public function getByPayload($payload) {
        $database = Database::openConnection();
        $database->prepare('SELECT * FROM payloads WHERE payload = :payload ORDER BY id DESC LIMIT 1');
        $database->bindValue(':payload', $payload);
        $database->execute();

        if ($database->countRows() === 0) {
            throw new Exception("Payload not found");
        }

        $payload = $database->fetch();

        return $payload;
    }

    /**
     * Return user by id
     *
     * @param int $id
     * @return Exception|array
     */
    public function getById($id)
    {
        $database = Database::openConnection();
        $database->getById($this->table, $id);

        if ($database->countRows() === 0) {
            throw new Exception("Payload not found");
        }

        $payload = $database->fetch();

        return $payload;
    }

    /**
     * Delete user by id
     *
     * @param int $id
     * @return Exception|bool
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
