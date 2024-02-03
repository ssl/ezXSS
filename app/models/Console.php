<?php

class Console_model extends Model
{
    /**
     * Summary of table
     * 
     * @var string
     */
    public $table = 'console';

    /**
     * Add console command
     * 
     * @param string $clientId The client id
     * @param string $origin The origin
     * @param string $command The command
     * @throws Exception
     * @return string
     */
    public function add($clientId, $origin, $command)
    {
        $database = Database::openConnection();

        $database->prepare("INSERT INTO $this->table (`clientid`, `origin`, `command`) VALUES (:clientid, :origin, :command);");
        $database->bindValue(':clientid', $clientId);
        $database->bindValue(':origin', $origin);
        $database->bindValue(':command', $command);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        return true;
    }

    /**
     * Get next command
     * 
     * @param string $clientId The client id
     * @param string $origin The origin
     * @throws Exception
     * @return string
     */
    public function getNext($clientId, $origin)
    {
        $database = Database::openConnection();

        $database->prepare("SELECT `id`,`command` FROM $this->table WHERE `clientid` = :clientid AND `origin` = :origin AND `executed` = 0 ORDER BY `id` ASC LIMIT 1");
        $database->bindValue(':clientid', $clientId);
        $database->bindValue(':origin', $origin);
        $database->execute();

        if ($database->countRows() === 0) {
            return '';
        }
        $data = $database->fetch();

        $database->prepare("UPDATE $this->table SET `executed` = 1 WHERE `id` = :id");
        $database->bindValue(':id', $data['id']);
        $database->execute();

        return $data['command'];
    }
}
