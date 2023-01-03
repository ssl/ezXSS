<?php

class Payload_model extends Model
{
    /**
     * Summary of table
     * 
     * @var string
     */
    public $table = 'payloads';

    /**
     * Get all payloads
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
     * Set payload value of single item by id
     * @param int $id The setting id
     * @param string $column The column name
     * @param string $value The new value
     * @throws Exception
     * @return bool
     */
    public function setSingleValue($id, $column, $value)
    {
        $database = Database::openConnection();

        $database->prepare('UPDATE `payloads` SET `' . $column . '` = :value WHERE id = :id');
        $database->bindValue(':value', $value);
        $database->bindValue(':id', $id);

        if (!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        return true;
    }

    /**
     * Get all payloads from user by user id
     * 
     * @param int $id The user id
     * @throws Exception
     * @return array
     */
    public function getAllByUserId($id)
    {
        $database = Database::openConnection();
        $database->prepare('SELECT * FROM payloads WHERE user_id = :user_id ORDER BY id ASC');
        $database->bindValue(':user_id', $id);

        if (!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        $data = $database->fetchAll();

        return $data;
    }

    /**
     * Add payload
     * 
     * @param int $userId The user id
     * @param string $payload The payload url
     * @throws Exception
     * @return bool
     */
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

    /**
     * Get payload by payload url
     * 
     * @param mixed $payload The payload url
     * @throws Exception
     * @return mixed
     */
    public function getByPayload($payload)
    {
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
     * Return payload by id
     *
     * @param int $id The payload id
     * @throws Exception
     * @return array
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
     * Delete payload by id
     *
     * @param int $id The payload id
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
