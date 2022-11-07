<?php

class Report_model extends Model
{

    public $table = 'reports';


    public function getAll()
    {
        $database = Database::openConnection();
        $database->getAll($this->table);

        $data = $database->fetchAll();

        return $data;
    }

    public function getAllStaticticsData()
    {
        $database = Database::openConnection();
        $database->prepare('SELECT origin,time,payload FROM reports ORDER BY id ASC');
        $database->execute();

        $data = $database->fetchAll();

        return $data;
    }
}
