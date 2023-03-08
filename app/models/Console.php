<?php

class Console_model extends Model
{
    /**
     * Summary of table
     * 
     * @var string
     */
    public $table = 'console';

    public function add($clientId, $origin, $command)
    {
        $database = Database::openConnection();

        $database->prepare('INSERT INTO `console` (`clientid`, `origin`, `command`) VALUES (:clientid, :origin, :command);');
        $database->bindValue(':clientid', $clientId);
        $database->bindValue(':origin', $origin);
        $database->bindValue(':command', $command);

        if (!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        return true;
    }

    public function getNext($clientId, $origin)
    {
        $database = Database::openConnection();

        $database->prepare('SELECT id,command FROM `console` WHERE `clientid` = :clientid AND `origin` = :origin AND executed = 0 ORDER BY id DESC LIMIT 1');
        $database->bindValue(':clientid', $clientId);
        $database->bindValue(':origin', $origin);
        $database->execute();

        if ($database->countRows() === 0) {
            return '';
        }
        $data = $database->fetch();

        $database->prepare('UPDATE `console` SET `executed` = 1 WHERE `id` = :id');
        $database->bindValue(':id', $data['id']);
        $database->execute();

        return $data['command'];
    }
}
