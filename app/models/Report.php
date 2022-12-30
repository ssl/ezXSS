<?php

class Report_model extends Model
{

    public $table = 'reports';


    public function getAll()
    {
        $database = Database::openConnection();
        $database->prepare('SELECT id,uri,ip,payload,archive,shareid FROM reports ORDER BY id DESC');
        $database->execute();

        $data = $database->fetchAll();

        return $data;
    }

    public function getById($id)
    {
        $database = Database::openConnection();
        $database->getById($this->table, $id);

        if ($database->countRows() === 0) {
            throw new Exception("Report not found");
        }

        $report = $database->fetch();

        return $report;
    }

    public function getByShareId($id)
    {
        $database = Database::openConnection();
        $database->prepare('SELECT * FROM reports WHERE shareid = :shareid LIMIT 1');
        $database->bindValue(':shareid', $id);
        $database->execute();

        if ($database->countRows() === 0) {
            throw new Exception("Report not found");
        }

        $report = $database->fetch();

        return $report;
    }

    public function getAllByPayload($payload)
    {
        $database = Database::openConnection();
        $database->prepare('SELECT id,uri,ip,payload,archive,shareid FROM reports WHERE payload LIKE :payload ORDER BY id DESC');
        $database->bindValue(':payload', $payload);
        $database->execute();

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

    public function getAllStaticticsDataByPayload($payload)
    {
        $database = Database::openConnection();
        $database->prepare('SELECT origin,time,payload FROM reports WHERE payload LIKE :payload ORDER BY id ASC');
        $database->bindValue(':payload', $payload);
        $database->execute();

        $data = $database->fetchAll();

        return $data;
    }

    public function add($shareId, $cookies, $dom, $origin, $referer, $uri, $userAgent, $ip, $screenshotName, $localStorage, $sessionStorage, $payload) {

        $database = Database::openConnection();

        $database->prepare('INSERT INTO reports (`shareid`, `cookies`, `dom`, `origin`, `referer`, `uri`, `user-agent`, `ip`, `time`, `screenshot`, `localstorage`, `sessionstorage`, `payload`) VALUES (:shareid, :cookies, :dom, :origin, :referer, :uri, :userAgent, :ip, :time, :screenshot, :localstorage, :sessionstorage, :payload)');
        $database->bindValue(':shareid', $shareId);
        $database->bindValue(':cookies', $cookies);
        $database->bindValue(':dom', $dom);
        $database->bindValue(':origin', $origin);
        $database->bindValue(':referer', $referer);
        $database->bindValue(':uri', $uri);
        $database->bindValue(':userAgent', $userAgent);
        $database->bindValue(':ip', $ip);
        $database->bindValue(':time', time());
        $database->bindValue(':screenshot', ($screenshotName ?? ''));
        $database->bindValue(':localstorage', $localStorage);
        $database->bindValue(':sessionstorage', $sessionStorage);
        $database->bindValue(':payload', $payload);

        if (!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        return true;
    }

    public function searchForDublicates($cookies, $dom, $origin, $referer, $uri, $userAgent, $ip) {

        $database = Database::openConnection();
        $database->prepare('SELECT id FROM reports WHERE cookies = :cookies AND dom = :dom AND origin = :origin AND referer = :referer AND uri = :uri AND `user-agent` = :userAgent AND ip = :ip LIMIT 1');
        $database->bindValue(':cookies', $cookies);
        $database->bindValue(':dom', $dom);
        $database->bindValue(':origin', $origin);
        $database->bindValue(':referer', $referer);
        $database->bindValue(':uri', $uri);
        $database->bindValue(':userAgent', $userAgent);
        $database->bindValue(':ip', $ip);
        $database->execute();

        $rowCount = $database->countRows();

        if($rowCount > 0) {
            return true;
        }
        return false;
    }

    public function archiveById($id) {
        $database = Database::openConnection();

        $report = $this->getById($id);
        $archive = $report['archive'] == '0' ? '1' : '0';

        $database->prepare('UPDATE `reports` SET `archive` = :archive WHERE id = :id');
        $database->bindValue(':archive', $archive);
        $database->bindValue(':id', $id);
        
        if(!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        return true;
    }

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
