<?php

class Persistent_model extends Model
{
    /**
     * Summary of table
     * 
     * @var string
     */
    public $table = 'persistent';

    /**
     * Get all reports
     * 
     * @return array
     */
    public function getAll()
    {
        $database = Database::openConnection();
        $database->prepare('SELECT p.id, p.clientid, p.ip, p.uri, p.payload, p.time, p.`user-agent`, last_row.requests FROM persistent p INNER JOIN ( SELECT MAX(id) as max_id, clientid, COUNT(*) as requests FROM persistent GROUP BY clientid ) last_row ON p.id = last_row.max_id ORDER BY p.id DESC;');
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
    public function getByClientId($id)
    {
        $database = Database::openConnection();
        $database->prepare('SELECT * FROM persistent WHERE clientid = :clientid LIMIT 1');
        $database->bindValue(':clientid', $id);
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
        $database->prepare('SELECT id,uri,ip,payload,archive,shareid FROM persistent WHERE payload LIKE :payload ORDER BY id DESC');
        $database->bindValue(':payload', $payload);

        if (!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        return $database->fetchAll();
    }

    /**
     * Add report
     * 
     * @param string $clientId The client id
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
    public function add($clientId, $cookies, $dom, $origin, $referer, $uri, $userAgent, $ip, $screenshotName, $localStorage, $sessionStorage, $payload)
    {
        $database = Database::openConnection();

        $database->prepare('INSERT INTO persistent (`clientid`, `cookies`, `dom`, `origin`, `referer`, `uri`, `user-agent`, `ip`, `time`, `screenshot`, `localstorage`, `sessionstorage`, `payload`) VALUES (:clientid, :cookies, :dom, :origin, :referer, :uri, :userAgent, :ip, :time, :screenshot, :localstorage, :sessionstorage, :payload)');
        $database->bindValue(':clientid', $clientId);
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
