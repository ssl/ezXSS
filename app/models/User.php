<?php

class User_model extends Model
{
    /**
     * Summary of table
     * 
     * @var string
     */
    public $table = 'users';

    /**
     * Validates login and returns user
     *
     * @param string $username The username
     * @param string $password The password
     * @throws Exception
     * @return array
     */
    public function login($username, $password)
    {

        $database = Database::openConnection();
        $database->getByUsername($this->table, $username);

        if ($database->countRows() !== 1) {
            throw new Exception('Login combination not found');
        }

        $user = $database->fetch();

        if (!password_verify($password, $user['password'])) {
            throw new Exception('Login combination not found');
        }

        if ($user['rank'] == 0) {
            throw new Exception('Login combination not found');
        }

        return $user;
    }

    /**
     * Update password
     *
     * @param int $id The user id
     * @param string $password The password
     * @throws Exception
     * @return bool
     */
    public function setPassword($id, $password, $hashed = false)
    {
        $database = Database::openConnection();

        if (
            strlen($password) < 8 || !preg_match('@[A-Z]@', $password) ||
            !preg_match('@[0-9]@', $password) || !preg_match('@[^\w]@', $password)
        ) {
            throw new Exception('Password not strong enough');
        }

        $database->prepare("UPDATE $this->table SET `password` = :password WHERE `id` = :id");
        $database->bindValue(':id', $id);
        $database->bindValue(':password', !$hashed ? password_hash($password, PASSWORD_BCRYPT, ['cost' => 14]) : $password);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        return true;
    }

    /**
     * Updates username
     * 
     * @param int $id The user id
     * @param string $username The new username
     * @throws Exception
     * @return bool
     */
    public function setUsername($id, $username)
    {
        $database = Database::openConnection();
        $database->getByUsername($this->table, $username);

        if ($database->countRows() >= 1) {
            throw new Exception('Username is already taken');
        }

        if (preg_match('/[^A-Za-z0-9]/', $username)) {
            throw new Exception('Invalid characters in the username. Use a-Z0-9');
        }

        if (strlen($username) < 3 || strlen($username) > 25) {
            throw new Exception('Username needs to be between 3-25 long');
        }

        $database->prepare("UPDATE $this->table SET `username` = :username WHERE `id` = :id");
        $database->bindValue(':id', $id);
        $database->bindValue(':username', $username);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        return true;
    }

    /**
     * Update rank
     * 
     * @param int $id The user id
     * @param int $rank The new rank
     * @throws Exception
     * @return bool
     */
    public function setRank($id, $rank)
    {
        $database = Database::openConnection();

        $database->prepare("UPDATE $this->table SET `rank` = :rank WHERE `id` = :id");
        $database->bindValue(':id', $id);
        $database->bindValue(':rank', $rank);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        return true;
    }

    /**
     * Updates secret
     * 
     * @param int $id The user id
     * @param string $secret The new secret
     * @throws Exception
     * @return bool
     */
    public function setSecret($id, $secret)
    {
        $database = Database::openConnection();

        $database->prepare("UPDATE $this->table SET `secret` = :secret WHERE `id` = :id");
        $database->bindValue(':id', $id);
        $database->bindValue(':secret', $secret);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        return true;
    }

    /**
     * Updates row 1
     * 
     * @param int $id The user id
     * @param string $value The new value
     * @throws Exception
     * @return bool
     */
    public function setRow1($id, $value)
    {
        $database = Database::openConnection();

        $database->prepare("UPDATE $this->table SET `row1` = :value WHERE `id` = :id");
        $database->bindValue(':id', $id);
        $database->bindValue(':value', $value);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        return true;
    }

    /**
     * Updates row 2
     * 
     * @param int $id The user id
     * @param string $value The new value
     * @throws Exception
     * @return bool
     */
    public function setRow2($id, $value)
    {
        $database = Database::openConnection();

        $database->prepare("UPDATE $this->table SET `row2` = :value WHERE `id` = :id");
        $database->bindValue(':id', $id);
        $database->bindValue(':value', $value);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        return true;
    }

    /**
     * Updates notepad
     * 
     * @param int $id The user id
     * @param string $value The new value
     * @throws Exception
     * @return bool
     */
    public function setNotepad($id, $value)
    {
        $database = Database::openConnection();

        $database->prepare("UPDATE $this->table SET `notepad` = :value WHERE `id` = :id");
        $database->bindValue(':id', $id);
        $database->bindValue(':value', $value);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        return true;
    }

    /**
     * Creates an new user
     *
     * @param string $username The username
     * @param string $password The password
     * @param string $rank The rank
     * @throws Exception
     * @return bool
     */
    public function create($username, $password, $rank)
    {
        $database = Database::openConnection();
        $database->getByUsername($this->table, $username);

        if ($database->countRows() >= 1) {
            throw new Exception('Username is already taken');
        }

        if (preg_match('/[^A-Za-z0-9]/', $username)) {
            throw new Exception('Invalid characters in the username. Use a-Z0-9');
        }

        if (strlen($username) < 3 || strlen($username) > 25) {
            throw new Exception('Username needs to be between 3-25 long');
        }

        if (
            strlen($password) < 8 || !preg_match('@[A-Z]@', $password) ||
            !preg_match('@[0-9]@', $password) || !preg_match('@[^\w]@', $password)
        ) {
            throw new Exception('Password not strong enough');
        }

        $database->prepare("INSERT INTO $this->table (`username`, `password`, `rank`, `secret`, `notepad`) VALUES (:username, :password, :rank, :secret, :notepad)");
        $database->bindValue(':username', $username);
        $database->bindValue(':password', password_hash($password, PASSWORD_BCRYPT, ['cost' => 14]));
        $database->bindValue(':rank', $rank);
        $database->bindValue(':secret', '');
        $database->bindValue(':notepad', 'Welcome to ezXSS');

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
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
}
