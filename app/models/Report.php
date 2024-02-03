<?php

class Report_model extends Model
{
    /**
     * Summary of table
     * 
     * @var string
     */
    public $table = 'reports';
    public $table_data = 'reports_data';

    /**
     * Get all reports
     * 
     * @return array|object
     */
    public function getAll()
    {
        $database = Database::openConnection();
        $database->prepare("SELECT `id`,`uri`,`ip`,`payload`,`archive`,`shareid` FROM $this->table ORDER BY `id` DESC LIMIT 100000");
        $database->execute();

        $data = $database->fetchAll();

        return $data;
    }

    /**
     * Get all reports by archive status
     * 
     * @param string $archive Archive status
     * @return array
     */
    public function getAllByArchive($archive)
    {
        $database = Database::openConnection();
        $database->prepare("SELECT `id`,`uri`,`ip`,`payload`,`shareid`,`user-agent`,time FROM $this->table WHERE `archive` = :archive ORDER BY `id` DESC");
        $database->bindValue(':archive', $archive);
        $database->execute();

        $data = $database->fetchAll();

        return $data;
    }

    /**
     * Get report by id
     * 
     * @param mixed $id The report id
     * @throws Exception
     * @return array
     */
    public function getById($id)
    {
        $report = parent::getById($id);
        $report_data = $this->getReportData($report['id']);

        return array_merge($report, $report_data);
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
        $database->prepare("SELECT * FROM $this->table WHERE `shareid` = :shareid LIMIT 1");
        $database->bindValue(':shareid', $id);
        $database->execute();

        if ($database->countRows() === 0) {
            throw new Exception('Report not found');
        }

        $report = $database->fetch();
        $report_data = $this->getReportData($report['id']);

        return array_merge($report, $report_data);
    }

    /**
     * Get report by payload
     * 
     * @param string $payload The payload
     * @param string $archive Archive status
     * @throws Exception
     * @return array
     */
    public function getAllByPayload($payload, $archive)
    {
        $database = Database::openConnection();
        $database->prepare("SELECT `id`,`uri`,`ip`,`payload`,`shareid`,`user-agent`,time FROM $this->table WHERE `payload` LIKE :payload AND `archive` = :archive ORDER BY `id` DESC");
        $database->bindValue(':archive', $archive);
        $database->bindValue(':payload', $payload);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
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
        $database->prepare("SELECT `origin`,`time`,`referer` FROM $this->table ORDER BY `id` ASC");

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        return $database->fetchAll();
    }

    /**
     * Get all statictics data by payload
     * 
     * @param string $payload The payload
     * @throws Exception
     * @return array
     */
    public function getAllStaticticsDataByPayload($payload)
    {
        $database = Database::openConnection();
        $database->prepare("SELECT `origin`,`time`,`referer`,`payload` FROM $this->table WHERE `payload` LIKE :payload ORDER BY `id` ASC");
        $database->bindValue(':payload', $payload);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        return $database->fetchAll();
    }

    /**
     * Get all common data by payload
     * 
     * @param string $payload The payload
     * @throws Exception
     * @return array
     */
    public function getAllCommonDataByPayload($payload)
    {
        $database = Database::openConnection();
        $database->prepare("SELECT `origin`,`ip`,`cookies`,`user-agent`,`payload` FROM $this->table WHERE `payload` LIKE :payload ORDER BY `id` ASC");
        $database->bindValue(':payload', $payload);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        return $database->fetchAll();
    }

    /**
     * Get all common data
     * 
     * @throws Exception
     * @return array
     */
    public function getAllCommonData()
    {
        $database = Database::openConnection();
        $database->prepare("SELECT `origin`,`ip`,`cookies`,`user-agent`,`payload` FROM $this->table ORDER BY `id` ASC");

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
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
     * @param string $screenshot The name/base64 of the screenshot
     * @param string $localStorage The local storage
     * @param string $sessionStorage The session storage
     * @param string $payload The payload name
     * @throws Exception
     * @return string
     */
    public function add($shareId, $cookies, $dom, $origin, $referer, $uri, $userAgent, $ip, $screenshot, $localStorage, $sessionStorage, $payload)
    {
        $database = Database::openConnection();
        $database->prepare("INSERT INTO $this->table (`shareid`, `cookies`, `origin`, `referer`, `uri`, `user-agent`, `ip`, `time`, `payload`) VALUES (:shareid, :cookies, :origin, :referer, :uri, :userAgent, :ip, :time, :payload)");
        $database->bindValue(':shareid', $shareId);
        $database->bindValue(':cookies', $cookies);
        $database->bindValue(':origin', $origin);
        $database->bindValue(':referer', $referer);
        $database->bindValue(':uri', $uri);
        $database->bindValue(':userAgent', $userAgent);
        $database->bindValue(':ip', $ip);
        $database->bindValue(':time', time());
        $database->bindValue(':payload', $payload);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }
        $reportId = $database->lastInsertId();

        // Compress data if enabled
        $compressed = false;
        if ($this->getCompressStatus() === 1) {
            $dom = base64_encode(gzdeflate($dom, 9));
            $screenshot = strlen($screenshot) === 52 || empty($screenshot) ? $screenshot : base64_encode(gzdeflate(base64_decode($screenshot), 9));
            $localStorage = $localStorage === '{}' ? '{}' : base64_encode(gzdeflate($localStorage, 9));
            $sessionStorage = $sessionStorage === '{}' ? '{}' : base64_encode(gzdeflate($sessionStorage, 9));
            $compressed = true;
        }

        $database->prepare("INSERT INTO $this->table_data (`reportid`, `dom`, `screenshot`, `localstorage`, `sessionstorage`, `compressed`) VALUES (:reportid, :dom, :screenshot, :localstorage, :sessionstorage, :compressed)");
        $database->bindValue(':reportid', $reportId);
        $database->bindValue(':dom', $dom);
        $database->bindValue(':screenshot', $screenshot);
        $database->bindValue(':localstorage', $localStorage);
        $database->bindValue(':sessionstorage', $sessionStorage);
        $database->bindValue('compressed', $compressed === true ? 1 : 0);
        $database->execute();

        return $reportId;
    }

    /**
     * Search for dublicate reports
     * 
     * @param string $cookies The cookies
     * @param string $origin The origin
     * @param string $referer The referer
     * @param string $uri The url
     * @param string $userAgent The user agent
     * @param string $ip The IP
     * @throws Exception
     * @return bool
     */
    public function searchForDublicates($cookies, $origin, $referer, $uri, $userAgent, $dom, $ip)
    {
        $database = Database::openConnection();
        $database->prepare("SELECT `id` FROM $this->table WHERE `cookies` = :cookies AND `origin` = :origin AND `referer` = :referer AND `uri` = :uri AND `user-agent` = :userAgent AND `ip` = :ip ORDER BY `id` DESC LIMIT 1");
        $database->bindValue(':cookies', $cookies);
        $database->bindValue(':origin', $origin);
        $database->bindValue(':referer', $referer);
        $database->bindValue(':uri', $uri);
        $database->bindValue(':userAgent', $userAgent);
        $database->bindValue(':ip', $ip);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        if ($database->countRows() > 0) {
            $report = $database->fetch();
            $report_data = $this->getReportData($report['id']);
            if ($report_data['dom'] === $dom) {
                return $report['id'];
            }
        }

        return false;
    }

    /**
     * Set report value of single item by id
     * 
     * @param int $id The report id
     * @param string $column The column name
     * @param string $value The new value
     * @throws Exception
     * @return bool
     */
    public function setSingleValue($id, $column, $value)
    {
        $database = Database::openConnection();

        $database->prepare("UPDATE $this->table SET `$column` = :value WHERE `id` = :id");
        $database->bindValue(':value', $value);
        $database->bindValue(':id', $id);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        return true;
    }

    /**
     * Set report data value of single item by id
     * 
     * @param int $id The report id
     * @param string $column The column name
     * @param string $value The new value
     * @throws Exception
     * @return bool
     */
    public function setSingleDataValue($id, $column, $value)
    {
        if ($this->getCompressStatus() === 1) {
            $value = $column === 'screenshot' ? base64_encode(gzdeflate(base64_decode($value), 9)) : base64_encode(gzdeflate($value, 9));
        }

        $database = Database::openConnection();
        $database->prepare("UPDATE $this->table_data SET `$column` = :value WHERE `reportid` = :reportid");
        $database->bindValue(':value', $value);
        $database->bindValue(':reportid', $id);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        return true;
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

        $database->prepare("UPDATE $this->table SET `archive` = :archive WHERE `id` = :id");
        $database->bindValue(':archive', $archive);
        $database->bindValue(':id', $id);

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        return true;
    }

    /**
     * Get report id by screenshot name
     * 
     * @param mixed $id The screenshot name
     * @throws Exception
     * @return array
     */
    public function getIdByScreenshot($screenshotName)
    {
        $database = Database::openConnection();
        $database->prepare("SELECT `reportid` FROM $this->table_data WHERE `screenshot` = :screenshot LIMIT 1");
        $database->bindValue(':screenshot', $screenshotName);
        $database->execute();

        if ($database->countRows() === 0) {
            throw new Exception('Report not found');
        }

        $data = $database->fetch();

        return $data['reportid'];
    }

    /**
     * Get all 3.x invalid reports
     * 
     * @return array
     */
    public function getAllInvalid()
    {
        $database = Database::openConnection();
        $database->prepare("SELECT `id`,`payload` FROM $this->table WHERE `payload` LIKE '%Collected page via %' OR `payload` IS NULL OR `payload` = ''");

        if (!$database->execute()) {
            throw new Exception('Something unexpected went wrong');
        }

        $data = $database->fetchAll();

        return $data;
    }

    /**
     * Get big data from report by id
     * 
     * @param int $id The report id
     * @return array
     */
    private function getReportData($id)
    {
        $database = Database::openConnection();
        $database->prepare("SELECT `dom`,`screenshot`,`localstorage`,`sessionstorage`,`compressed` FROM $this->table_data WHERE `reportid` = :reportid LIMIT 1");
        $database->bindValue(':reportid', $id);
        $database->execute();

        if ($database->countRows() === 1) {
            $report_data = $database->fetch();
        }

        // Decompress if compressed
        if($report_data['compressed'] ?? 0 == 1) {
            $report_data['dom'] = gzinflate(base64_decode($report_data['dom']));
            $report_data['screenshot'] = strlen($report_data['screenshot']) === 52 || empty($report_data['screenshot']) ? $report_data['screenshot'] : base64_encode(gzinflate(base64_decode($report_data['screenshot']))) ?? '';
            $report_data['localstorage'] = $report_data['localstorage'] === '{}' ? '{}' : gzinflate(base64_decode($report_data['localstorage'])) ?? '';
            $report_data['sessionstorage'] = $report_data['sessionstorage'] === '{}' ? '{}' : gzinflate(base64_decode($report_data['sessionstorage'])) ?? '';
        }

        return $report_data ?? ['dom' => '', 'screenshot' => '', 'localstorage' => '', 'sessionstorage' => ''];
    }
}
