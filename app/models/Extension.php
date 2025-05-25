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

    /**
     * Update extension
     * 
     * @param int $id The extension id
     * @param string $name The extension name
     * @param string $description The extension description
     * @param string $version The extension version
     * @param string $author The extension author
     * @param string $code The extension code
     * @throws Exception
     * @return bool
     */
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

    /**
     * Add extension
     * 
     * @param string $name The extension name
     * @param string $description The extension description
     * @param string $version The extension version
     * @param string $author The extension author
     * @param string $source The extension source
     * @param string $code The extension code
     * @throws Exception
     * @return bool
     */
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

    /**
     * Get extension by source
     * 
     * @param string $source The extension source
     * @throws Exception
     * @return array
     */
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

    /**
     * Get all enabled extensions
     * 
     * @return array
     */
    public function getAllEnabled()
    {
        $database = Database::openConnection();
        $database->prepare("SELECT * FROM $this->table WHERE `enabled` = 1");
        
        if(!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        return $database->fetchAll();
    }

    /**
     * Toggle extension enabled status
     * 
     * @param int $id The extension id
     * @throws Exception
     * @return bool
     */
    public function toggleEnabled($id)
    {
        $database = Database::openConnection();
        $database->prepare("UPDATE $this->table SET `enabled` = 1 - `enabled` WHERE `id` = :id");
        $database->bindValue(':id', $id);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        return true;
    }

    /**
     * Validate extension
     * 
     * @param string $name The extension name
     * @param string $description The extension description
     * @param string $version The extension version
     * @param string $author The extension author
     * @throws Exception
     * @return void
     */
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