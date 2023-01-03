<?php

class Report_model extends Model
{
    /**
     * Summary of table
     * 
     * @var string
     */
    public $table = 'reports';

    /**
     * Get all reports
     * 
     * @return array
     */
    public function getAll()
    {
        $database = Database::openConnection();
        $database->prepare('SELECT id,uri,ip,payload,archive,shareid FROM reports ORDER BY id DESC');
        $database->execute();

        $data = $database->fetchAll();

        return $data;
    }

    /**
     * Get report by id
     * 
     * @param int $id The report id
     * @throws Exception
     * @return array
     */
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

    /**
     * Get report by share id
     * 
     * @param mixed $id The share id
     * @throws Exception
     * @return array
     */
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

    /**
     * Get report by payload
     * @param string $payload The payload
     * @throws Exception
     * @return array
     */
    public function getAllByPayload($payload)
    {
        $database = Database::openConnection();
        $database->prepare('SELECT id,uri,ip,payload,archive,shareid FROM reports WHERE payload LIKE :payload ORDER BY id DESC');
        $database->bindValue(':payload', $payload);
        
        if (!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        return $database->fetchAll();
    }

    /**
     * Get all statictics data
     * 
     * @throws Exception
     * @return array
     */
    public function getAllStaticticsData()
    {
        $database = Database::openConnection();
        $database->prepare('SELECT origin,time,referer FROM reports ORDER BY id ASC');
        
        if (!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        return $database->fetchAll();
    }

    /**
     * Get al statictics data by payload
     * 
     * @param string $payload The payload
     * @throws Exception
     * @return array
     */
    public function getAllStaticticsDataByPayload($payload)
    {
        $database = Database::openConnection();
        $database->prepare('SELECT origin,time,referer,payload FROM reports WHERE payload LIKE :payload ORDER BY id ASC');
        $database->bindValue(':payload', $payload);

        if (!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        return $database->fetchAll();
    }

    /**
     * Add report
     * 
     * @param string $shareId The share id
     * @param string $cookies The cookies
     * @param string $dom The HTML dom
     * @param string $origin The origin
     * @param string $referer The referer
     * @param string $uri The url
     * @param string $userAgent The user agent
     * @param string $ip The IP
     * @param string $screenshotName The name of the screenshot
     * @param string $localStorage The local storage
     * @param string $sessionStorage The session storage
     * @param string $payload The payload name
     * @throws Exception
     * @return string
     */
    public function add($shareId, $cookies, $dom, $origin, $referer, $uri, $userAgent, $ip, $screenshotName, $localStorage, $sessionStorage, $payload)
    {
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
        $database->bindValue(':screenshot', $screenshotName);
        $database->bindValue(':localstorage', $localStorage);
        $database->bindValue(':sessionstorage', $sessionStorage);
        $database->bindValue(':payload', $payload);

        if (!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        return $database->lastInsertId();
    }

    /**
     * Search for dublicate reports
     * 
     * @param string $cookies The cookies
     * @param string $dom The HTML dom
     * @param string $origin The origin
     * @param string $referer The referer
     * @param string $uri The url
     * @param string $userAgent The user agent
     * @param string $ip The IP
     * @throws Exception
     * @return bool
     */
    public function searchForDublicates($cookies, $dom, $origin, $referer, $uri, $userAgent, $ip)
    {
        $database = Database::openConnection();
        $database->prepare('SELECT id FROM reports WHERE cookies = :cookies AND dom = :dom AND origin = :origin AND referer = :referer AND uri = :uri AND `user-agent` = :userAgent AND ip = :ip LIMIT 1');
        $database->bindValue(':cookies', $cookies);
        $database->bindValue(':dom', $dom);
        $database->bindValue(':origin', $origin);
        $database->bindValue(':referer', $referer);
        $database->bindValue(':uri', $uri);
        $database->bindValue(':userAgent', $userAgent);
        $database->bindValue(':ip', $ip);
        
        if (!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        if ($database->countRows() > 0) {
            return true;
        }

        return false;
    }

    /**
     * Archive report by id
     * 
     * @param string $id The report id
     * @throws Exception
     * @return bool
     */
    public function archiveById($id)
    {
        $database = Database::openConnection();

        $report = $this->getById($id);
        $archive = $report['archive'] == '0' ? '1' : '0';

        $database->prepare('UPDATE `reports` SET `archive` = :archive WHERE id = :id');
        $database->bindValue(':archive', $archive);
        $database->bindValue(':id', $id);

        if (!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        return true;
    }

    /**
     * Delete report by id
     * 
     * @param string $id The report id
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
