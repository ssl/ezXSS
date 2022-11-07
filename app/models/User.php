<?php

class User_model extends Model
{

    public $table = 'users';

    /**
     * Validates login and returns user
     *
     * @param string $username
     * @param string $password
     * @return Exception|array
     */
    public function login($username, $password)
    {

        $database = Database::openConnection();
        $database->getByUsername($this->table, $username);

        if ($database->countRows() !== 1) {
            throw new Exception("Login combination not found");
        }

        $user = $database->fetch();

        if (!password_verify($password, $user['password'])) {
            throw new Exception("Login combination not found");
        }

        if ($user['rank'] == 0) {
            throw new Exception("Login combination not found");
        }

        return $user;
    }

    /**
     * Update password
     *
     * @param int $id
     * @param string $password
     * @return Exception|bool
     */
    public function updatePassword($id, $password)
    {
        $database = Database::openConnection();

        if (
            strlen($password) < 8 || !preg_match('@[A-Z]@', $password) ||
            !preg_match('@[0-9]@', $password) || !preg_match('@[^\w]@', $password)
        ) {
            throw new Exception("Password not strong enough");
        }

        $database->prepare('UPDATE `users` SET password = :password WHERE id = :id');
        $database->bindValue(':id', $id);
        $database->bindValue(':password', password_hash($password, PASSWORD_BCRYPT));

        if (!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        return true;
    }

    public function updateUsername($id, $username) {
        $database = Database::openConnection();
        $database->getByUsername($this->table, $username);

        if ($database->countRows() >= 1) {
            throw new Exception("Username is already taken");
        }

        if (preg_match('/[^A-Za-z0-9]/', $username)) {
            throw new Exception("Invalid characters in the username. Use a-Z0-9");
        }

        if (strlen($username) < 3 || strlen($username) > 25) {
            throw new Exception("Username needs to be between 3-25 long");
        }

        $database->prepare('UPDATE `users` SET username = :username WHERE id = :id');
        $database->bindValue(':id', $id);
        $database->bindValue(':username', $username);

        if (!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        return true;
    }

    public function updateRank($id, $rank) {
        $database = Database::openConnection();

        $database->prepare('UPDATE `users` SET rank = :rank WHERE id = :id');
        $database->bindValue(':id', $id);
        $database->bindValue(':rank', $rank);

        if (!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        return true;
    }

    /**
     * Creates an new user
     *
     * @param string $username
     * @param string $password
     * @return Exception|array
     */
    public function create($username, $password, $rank)
    {
        $database = Database::openConnection();
        $database->getByUsername($this->table, $username);

        if ($database->countRows() >= 1) {
            throw new Exception("Username is already taken");
        }

        if (preg_match('/[^A-Za-z0-9]/', $username)) {
            throw new Exception("Invalid characters in the username. Use a-Z0-9");
        }

        if (strlen($username) < 3 || strlen($username) > 25) {
            throw new Exception("Username needs to be between 3-25 long");
        }

        if (
            strlen($password) < 8 || !preg_match('@[A-Z]@', $password) ||
            !preg_match('@[0-9]@', $password) || !preg_match('@[^\w]@', $password)
        ) {
            throw new Exception("Password not strong enough");
        }

        $database->prepare('INSERT INTO `users` (`username`, `password`, `rank`) VALUES (:username, :password, :rank);');
        $database->bindValue(':username', $username);
        $database->bindValue(':password', password_hash($password, PASSWORD_BCRYPT));
        $database->bindValue(':rank', $rank);

        if (!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        $database->getByUsername($this->table, $username);
        $user = $database->fetch();

        return $user;
    }

    /**
     * Returns all users
     *
     * @return array
     */
    public function getAllUsers()
    {
        $database = Database::openConnection();
        $database->getAll($this->table);

        $users = $database->fetchAll();

        return $users;
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
            throw new Exception("Account not found");
        }

        $account = $database->fetch();

        return $account;
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
