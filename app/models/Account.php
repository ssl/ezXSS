<?php

class User_model extends Model {

    public $table = 'users';

    /**
     * Validates login and returns account
     *
     * @param string $username
     * @param string $password
     * @return Exception|array
     */
    public function login($username, $password) {
        
        $database = Database::openConnection();
        $database->getByUsername($this->table, $username);

        if ($database->countRows() !== 1) {
            throw new Exception("Login combination not found");
        }

        $account = $database->fetch();

        if (!password_verify($password, $account['password'])) {
            throw new Exception("Login combination not found");
        }

        return $account;
    }

    /**
     * Update password
     *
     * @param int $id
     * @param string $password
     * @return Exception|bool
     */
    public function updatePassword($id, $password) {
        $database = Database::openConnection();

        if (strlen($password) < 8 || !preg_match('@[A-Z]@', $password) || 
        !preg_match('@[0-9]@', $password) || !preg_match('@[^\w]@', $password)) {
            throw new Exception("Password not strong enough");
        }
        
        $database->prepare('UPDATE `users` SET password = :password WHERE id = :id');
        $database->bindValue(':id', $id);
        $database->bindValue(':password', password_hash($password, PASSWORD_BCRYPT));
        
        if(!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        return true;
    }

    /**
     * Creates an new account
     *
     * @param string $username
     * @param string $password
     * @return Exception|array
     */
    public function create($username, $password) {
        $database = Database::openConnection();
        $database->getByUsername($this->table, $username);

        if ($database->countRows() >= 1){
            throw new Exception("Username is already taken");
        }

        if (preg_match('/[^A-Za-z0-9]/', $username)) {
            throw new Exception("Invalid characters in your username. Use a-Z0-9");
        }

        if (strlen($username) < 3 || strlen($username) > 30) {
            throw new Exception("Username needs to be between 3-30 long");
        }

        if (strlen($password) < 8 || !preg_match('@[A-Z]@', $password) || 
        !preg_match('@[0-9]@', $password) || !preg_match('@[^\w]@', $password)) {
            throw new Exception("Password not strong enough");
        }

        $database->prepare('INSERT INTO `users` (`username`, `password`) VALUES (:username, :password);');
        $database->bindValue(':username', $username);
        $database->bindValue(':password', password_hash($password, PASSWORD_BCRYPT));

        if(!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        $database->getByUsername($this->table, $username);
        $account = $database->fetch();

        return $account;
    }

    /**
     * Returns all accounts
     *
     * @return array
     */
    public function getAllAccounts() {
        $database = Database::openConnection();
        $database->getAll($this->table);

        $accounts = $database->fetchAll();

        return $accounts;
    }

    /**
     * Return account by id
     *
     * @param int $id
     * @return Exception|array
     */
    public function getById($id) {
        $database = Database::openConnection();
        $database->getById($this->table, $id);

        if ($database->countRows() === 0){
            throw new Exception("Account not found");
        }

        $account = $database->fetch();

        return $account;
    }

    /**
     * Delete account by id
     *
     * @param int $id
     * @return Exception|bool
     */
    public function deleteById($id) {
        $database = Database::openConnection();
        $database->deleteById($this->table, $id);

        if(!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        return true;
    }
}