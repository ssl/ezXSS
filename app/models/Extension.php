<?php

class Extension_model extends Model
{
    /**
     * Summary of table
     * 
     * @var string
     */
    public $table = 'extensions';

    /**
     * Get all extensions
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

    public function update($id, $name, $description, $version, $author, $code)
    {
        $this->validate($name, $description, $version, $author);

        $database = Database::openConnection();
        $database->prepare("UPDATE $this->table SET `name` = :name, `description` = :description, `version` = :version, `author` = :author, `code` = :code WHERE `id` = :id");
        $database->bindValue(':id', $id);
        $database->bindValue(':name', $name);
        $database->bindValue(':description', $description);
        $database->bindValue(':version', $version);
        $database->bindValue(':author', $author);
        $database->bindValue(':code', $code);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        return true;
    }

    public function add($name, $description, $version, $author, $source, $code)
    {
        $this->validate($name, $description, $version, $author);

        $database = Database::openConnection();
        $database->prepare("INSERT INTO $this->table (`name`, `description`, `version`, `author`, `source`, `code`) VALUES (:name, :description, :version, :author, :source, :code)");
        $database->bindValue(':name', $name);
        $database->bindValue(':description', $description);
        $database->bindValue(':version', $version);
        $database->bindValue(':author', $author);
        $database->bindValue(':source', $source);
        $database->bindValue(':code', $code);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        return true;
    }

    public function getBySource($source)
    {
        $database = Database::openConnection();
        $database->prepare("SELECT * FROM $this->table WHERE `source` = :source");
        $database->bindValue(':source', $source);
        
        if(!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        return $database->fetch();
    }

    private function validate($name, $description, $version, $author)
    {
        if(strlen($name) < 2 || strlen($name) > 35) {
            throw new Exception('Name must be between 2 and 35 characters');
        }

        if(strlen($description) > 255) {
            throw new Exception('Description must be less than 255 characters');
        }

        if(strlen($author) > 50) {
            throw new Exception('Author must be less than 50 characters');
        }

        if(strlen($version) > 10) {
            throw new Exception('Version must be less than 10 characters');
        }
    }
}