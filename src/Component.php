<?php

class Component
{

    public function __construct()
    {
        $this->user = new User();
        $this->database = new Database();
        $this->basic = new Basic();

        $this->releases = [];
        $this->filterSave = '';
        $this->filterAlert = '';
        $this->reportInfo = [];
        $this->secret = '';
        $this->base32Characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    }

    /**
     * Get csrf-token of user
     * @method csrf
     * @param boolean $plain true/false
     * @return string CSRF token
     */
    public function csrf($plain = false)
    {
        $csrf = $this->user->getCsrf();

        return ($plain) ? $csrf : "<input type=hidden hidden id=csrf value={$csrf}>";
    }

    /**
     * Get twofactor login html block
     * @method twofactorLogin
     * @return string html
     */
    public function twofactorLogin()
    {
        if (strlen($this->setting('secret')) == 16) {
            return $this->basic->htmlBlocks('twofactorLogin');
        }

        return '';
    }

    /**
     * Get setting value
     * @method setting
     * @param string $name setting name
     * @return string setting value
     */
    public function setting($name)
    {
        if ($name == 'temp-secret') {
            return $this->secret;
        }

        $setting = $this->database->fetch('SELECT value FROM settings WHERE setting = :name LIMIT 1', [':name' => $name]);
        return htmlspecialchars($setting['value'], ENT_QUOTES);
    }

    /**
     * Get statistics of amount of reports
     * @method statistics
     * @param string $branch which branch
     * @return string count
     */
    public function statistics($branch)
    {
        switch ($branch) {
            case 'total' :
                return ($this->database->fetch('SELECT COUNT(DISTINCT id) FROM reports', []))[0];
                break;
            case 'week' :
                return ($this->database->fetch(
                    'SELECT COUNT(DISTINCT id) FROM reports WHERE time > :time',
                    [':time' => time() - 604800]
                ))[0];
                break;
            case 'totaldomains' :
                return ($this->database->fetch('SELECT COUNT(DISTINCT origin) FROM reports', []))[0];
                break;
            case 'weekdomains' :
                return ($this->database->fetch(
                    'SELECT COUNT(DISTINCT origin) FROM reports WHERE time > :time',
                    [':time' => time() - 604800]
                ))[0];
                break;
            case 'totalshared' :
                return ($this->database->fetch(
                    'SELECT COUNT(DISTINCT id) FROM reports WHERE referer LIKE "Shared via %"',
                    []
                ))[0];
                break;
        }

        if ($branch == 'last') {
            $last = $this->database->fetch('SELECT id,time FROM reports ORDER BY id DESC LIMIT 1');
            if (isset($last['id'])) {
                $time = time() - $last['time'];
                $syntaxText = 's';
                if ($time > 60) {
                    $time /= 60;
                    $syntaxText = 'm';
                }
                if ($time > 60) {
                    $time /= 60;
                    $syntaxText = 'h';
                }
                if ($time > 24) {
                    $time /= 24;
                    $syntaxText = 'd';
                }
                return floor($time) . $syntaxText;
            } else {
                return 'never';
            }
        }
    }

    /**
     * Get ezXSS version
     * @method version
     * @return string version number
     */
    public function version() {
        return version;
    }

    /**
     * Get information about latests ezXSS release
     * @method repoinfo
     * @param string $key key
     * @return string value
     */
    public function repoInfo($key)
    {
        if ($this->releases === []) {
            try {
                $ch = curl_init('https://status.ezxss.com/?v=' . version);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: ezXSS']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                $this->releases = json_decode(curl_exec($ch), true);
            } catch (Exception $e) {
                $this->releases = ['timeout'];
            }
        }
        return htmlspecialchars($this->releases[0][$key]);
    }

    /**
     * Returns selected if that are current filter options
     * @method filterSelected
     * @param string $id selected value
     * @return string selected
     */
    public function filterSelected($id)
    {
        if ($this->filterSave == '') {
            $this->filterSave = $this->setting('filter-save');
            $this->filterAlert = $this->setting('filter-alert');
        }

        if ($id == 1 && $this->filterSave == 1 && $this->filterAlert == 1) {
            return 'selected';
        }

        if ($id == 2 && $this->filterSave == 1 && $this->filterAlert == 0) {
            return 'selected';
        }

        if ($id == 3 && $this->filterSave == 0 && $this->filterAlert == 1) {
            return 'selected';
        }

        if ($id == 4 && $this->filterSave == 0 && $this->filterAlert == 0) {
            return 'selected';
        }

        return '';
    }

    /**
     * Returns selected if that are current screenshot options
     * @method screenshotSelected
     * @param string $id selected value
     * @return string selected
     */
    public function screenshotSelected($id)
    {
        $screenshot = $this->setting('screenshot');

        if ($id == 1 && $screenshot == 1) {
            return 'selected';
        }

        if ($id == 0 && $screenshot == 0) {
            return 'selected';
        }

        return '';
    }

    /**
     * Returns all reports of that page/search
     * @method reportsList
     * @param string $archive true/false
     * @return string HTML of reports
     */
    public function reportsList($archive)
    {
        if (isset($_GET['search'])) {
            $query = 'SELECT id,shareid,uri,ip,origin FROM reports WHERE uri LIKE :uri OR ip LIKE :ip OR origin LIKE :origin LIMIT :limit,50';
            $array = [
                ':uri' => '%' . $_GET['search'] . '%',
                ':ip' => '%' . $_GET['search'] . '%',
                ':origin' => '%' . $_GET['search'] . '%',
                ':limit' => $this->page() * 50
            ];
        } else {
            $query = 'SELECT id,shareid,uri,ip,origin FROM reports WHERE archive = :archive ORDER BY id DESC LIMIT :limit,50';
            $array = [':archive' => $archive, ':limit' => $this->page() * 50];
        }

        $htmlTemplate = $this->basic->htmlBlocks('reportList');
        $html = '';

        foreach ($this->database->fetchAll($query, $array) as $report) {
            $report['uri'] = strlen($report['uri']) > 80 ? substr($report['uri'], 0, 80) . '..' : $report['uri'];
            $report['ip'] = strlen($report['ip']) > 15 ? substr($report['ip'], 0, 15) . '..' : $report['ip'];
            $report['origin'] = strlen($report['origin']) > 20 ? substr(
                    $report['origin'],
                    0,
                    20
                ) . '..' : $report['origin'];

            $tempHtml = $htmlTemplate;
            preg_match_all('/{{(.*?)\[(.*?)\]}}/', $tempHtml, $matches);
            foreach ($matches[1] as $key => $value) {
                $tempHtml = str_replace($matches[0][$key], htmlspecialchars($report["{$matches[2][$key]}"]), $tempHtml);
            }

            $html .= $tempHtml;
        }

        return $html;
    }

    /**
     * Returns page number
     * @method page
     * @param bool $navigation true/false
     * @return string page number
     */
    public function page($navigation = false)
    {
        $page = (isset($_GET['page'])) ? (int)trim(htmlspecialchars($_GET['page'])) : 0;

        switch ($navigation) {
            case '+' :
                ++$page;
                break;
            case '-' :
                --$page;
                break;
        }

        if ($page < 0) {
            $page = 0;
        }

        return $page;
    }

    /**
     * Gets and returns information about a report
     * @method report
     * @param string $key key of what is needed
     * @return string value of key
     */
    public function report($key)
    {
        if ($this->reportInfo === []) {
            $id = explode('/', $_SERVER['REQUEST_URI'])[3];

            if (is_numeric($id)) {
                if (!$this->user->isLoggedIn()) {
                    return header('Location: /manage/login');
                }
                $this->reportInfo = $this->database->fetch(
                    'SELECT * FROM reports WHERE id = :id LIMIT 1',
                    [':id' => $id]
                );
            } else {
                $this->reportInfo = $this->database->fetch(
                    'SELECT * FROM reports WHERE shareid = :id LIMIT 1',
                    [':id' => $id]
                );
            }

            if (!isset($this->reportInfo['id'])) {
                return header('Location: /manage/reports');
            }
        }

        if (isset($this->reportInfo[$key])) {
            return ($key == 'time') ? date('F j, Y, g:i a', $this->reportInfo[$key]) : htmlspecialchars(
                $this->reportInfo[$key]
            );
        }
    }

    /**
     * Provides the search bar html
     * @method searchBar
     * @return string search bar html
     */
    public function searchBar()
    {
        if ($this->user->isLoggedIn()) {
            $html = str_replace('{{searchQuery}}', $this->searchQuery(0), $this->basic->htmlBlocks('searchBar'));
        } else {
            $html = '';
        }

        return $html;
    }

    /**
     * Provides the search query
     * @method searchQuery
     * @param string $navigation true/false
     * @return string search query
     */
    public function searchQuery($navigation)
    {
        if (isset($_GET['search'])) {
            if ($navigation === true) {
                return '&search=' . htmlspecialchars($_GET['search']);
            }

            return htmlspecialchars($_GET['search']);
        }
    }

    /**
     * Provides html blocks of twofactor settings
     * @method twofactorSettings
     * @return string html
     */
    public function twofactorSettings()
    {
        $secretCheck = $this->database->fetch('SELECT value FROM settings WHERE setting = "secret"');

        if (strlen($secretCheck['value']) !== 16) {
            if ($this->secret == '') {
                for ($i = 0; $i < 16; $i++) {
                    $this->secret .= $this->base32Characters[rand(0, 31)];
                }
            }
            $html = str_replace('{{secret}}', $this->secret, $this->basic->htmlBlocks('twofactorEnable'));
        } else {
            $html = $this->basic->htmlBlocks('twofactorDisable');
        }

        return $html;
    }

    /**
     * Provides current domain name
     * @method domain
     * @return string
     */
    public function domain() {
        return $this->basic->domain();
    }

}
