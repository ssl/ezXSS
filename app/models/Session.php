<?php

class Session_model extends Model
{
    /**
     * Summary of table
     * 
     * @var string
     */
    public $table = 'sessions';

    /**
     * Get all reports
     * 
     * @return array
     */
    public function getAll()
    {
        $database = Database::openConnection();
        $database->prepare('SELECT p.id, p.clientid, p.ip, p.uri, p.payload, p.time, p.origin, p.`user-agent`, last_row.requests FROM `sessions` p INNER JOIN ( SELECT MAX(id) as max_id, clientid, COUNT(*) as requests FROM `sessions` GROUP BY clientid ) last_row ON p.id = last_row.max_id ORDER BY p.id DESC;');
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
     * Get session by client id
     * 
     * @param mixed $id The share id
     * @param string $id The origin
     * @throws Exception
     * @return array
     */
    public function getByClientId($id, $origin)
    {
        $database = Database::openConnection();
        $database->prepare('SELECT * FROM `sessions` WHERE clientid = :clientid AND origin = :origin ORDER BY id DESC LIMIT 1');
        $database->bindValue(':clientid', $id);
        $database->bindValue(':origin', $origin);
        $database->execute();

        if ($database->countRows() === 0) {
            throw new Exception("Session not found");
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
        $database->prepare('SELECT id,uri,ip,payload,archive,shareid FROM `sessions` WHERE payload LIKE :payload ORDER BY id DESC');
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
     * @param string $console The console log
     * @throws Exception
     * @return string
     */
    public function add($clientId, $cookies, $dom, $origin, $referer, $uri, $userAgent, $ip, $screenshotName, $localStorage, $sessionStorage, $payload, $console)
    {
        $database = Database::openConnection();

        $database->prepare('INSERT INTO `sessions` (`clientid`, `cookies`, `dom`, `origin`, `referer`, `uri`, `user-agent`, `ip`, `time`, `screenshot`, `localstorage`, `sessionstorage`, `payload`, `console`) VALUES (:clientid, :cookies, :dom, :origin, :referer, :uri, :userAgent, :ip, :time, :screenshot, :localstorage, :sessionstorage, :payload, :console)');
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
        $database->bindValue(':console', $console);

        if (!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        return $database->lastInsertId();
    }

    /**
     * Set session value of single item by id
     * @param int $id The session id
     * @param string $column The column name
     * @param string $value The new value
     * @throws Exception
     * @return bool
     */
    public function setSingleValue($id, $column, $value)
    {
        $database = Database::openConnection();

        $database->prepare('UPDATE `sessions` SET `' . $column . '` = :value WHERE id = :id');
        $database->bindValue(':value', $value);
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
