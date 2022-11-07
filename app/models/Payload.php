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

    public function getAllByUserId($id)
    {
        $database = Database::openConnection();
        $database->prepare('SELECT * FROM payloads WHERE user_id = :user_id ORDER BY id ASC');
        $database->bindValue(':user_id', $id);
        $database->execute();

        $data = $database->fetchAll();

        return $data;
    }

    public function add($user_id, $payload)
    {
        $database = Database::openConnection();

        $database->prepare('INSERT INTO `payloads` (`payload`, `user_id`) VALUES (:payload, :user_id);');
        $database->bindValue(':payload', $payload);
        $database->bindValue(':user_id', $user_id);

        if (!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        return true;
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
