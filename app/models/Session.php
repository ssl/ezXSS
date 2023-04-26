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
     * Get all sessions
     * 
     * @return array
     */
    public function getAll()
    {
        $database = Database::openConnection();
        $database->prepare('SELECT p.id, p.clientid, p.ip, p.uri, p.payload, p.time, p.origin, p.`user-agent`, last_row.requests FROM `sessions` p INNER JOIN ( SELECT MAX(id) as max_id, clientid, COUNT(*) as requests FROM `sessions` GROUP BY clientid ) last_row ON p.id = last_row.max_id ORDER BY p.time DESC;');
        $database->execute();

        $data = $database->fetchAll();

        return $data;
    }

    /**
     * Get all console data
     * 
     * @param string $clientId The client id
     * @param string $origin The origin
     * @throws Exception
     * @return array
     */
    public function getAllConsole($clientId, $origin)
    {
        $database = Database::openConnection();
        $database->prepare('SELECT `console` FROM `sessions` WHERE clientid = :clientid AND origin = :origin AND console != "" ORDER BY id DESC');
        $database->bindValue(':clientid', $clientId);
        $database->bindValue(':origin', $origin);
        $database->execute();

        if ($database->countRows() === 0) {
            return '';
        }

        $console = '';
        $sessions = $database->fetchAll();

        foreach ($sessions as $value) {
            $console .= $value['console'];
        }

        return $console;
    }

    /**
     * Get by client id
     * 
     * @param string $clientId The client id
     * @param string $origin The origin
     * @throws Exception
     * @return array
     */
    public function getByClientId($clientId, $origin)
    {
        $database = Database::openConnection();
        $database->prepare('SELECT * FROM `sessions` WHERE clientid = :clientid AND origin = :origin ORDER BY id DESC LIMIT 1');
        $database->bindValue(':clientid', $clientId);
        $database->bindValue(':origin', $origin);
        $database->execute();

        if ($database->countRows() === 0) {
            throw new Exception("Session not found");
        }

        $report = $database->fetch();

        return $report;
    }

    /**
     * Get session by payload
     * @param string $payload The payload
     * @throws Exception
     * @return array
     */
    public function getAllByPayload($payload)
    {
        $database = Database::openConnection();
        $database->prepare('SELECT p.id, p.clientid, p.ip, p.uri, p.payload, p.time, p.origin, p.`user-agent`, last_row.requests FROM `sessions` p INNER JOIN ( SELECT MAX(id) as max_id, clientid, COUNT(*) as requests FROM `sessions` GROUP BY clientid ) last_row ON p.id = last_row.max_id WHERE payload LIKE :payload ORDER BY p.time DESC;');
        $database->bindValue(':payload', $payload);

        if (!$database->execute()) {
            throw new Exception("Something unexpected went wrong");
        }

        return $database->fetchAll();
    }

    /**
     * Get request count by client id
     * 
     * @param string $clientId The client id
     * @throws Exception
     * @return int
     */
    public function getRequestCount($clientId)
    {
        $database = Database::openConnection();
        $database->prepare('SELECT clientid FROM `sessions` WHERE clientid = :clientid');
        $database->bindValue(':clientid', $clientId);
        $database->execute();

        return $database->countRows();
    }

    /**
     * Add session
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
     * Delete all by client id
     * 
     * @param string $clientId The client id
     * @param string $origin The origin
     * @throws Exception
     * @return array
     */
    public function deleteAll($clientId, $origin)
    {
        $database = Database::openConnection();
        $database->prepare('DELETE FROM `sessions` WHERE clientid = :clientid AND origin = :origin');
        $database->bindValue(':clientid', $clientId);
        $database->bindValue(':origin', $origin);
        $database->execute();
    }
}