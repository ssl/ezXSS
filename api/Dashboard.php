<?php
class DashboardController extends Controller
{
    /**
     * Routable HTTP actions (everything else is an internal helper).
     */
    public static $routable = array('index', 'common', 'version');

    /**
     * Cache key + TTL for the status.ezxss.com version ping.
     */
    const VERSION_CACHE = 'version_status';
    const VERSION_TTL = 3600;

    /**
     * GET /api/dashboard - the 12 stat tiles for the actor's scope.
     */
    public function index()
    {
        $this->begin('GET');

        $this->respond($this->doStats());
    }

    /**
     * GET /api/dashboard/common?field=... - top-10 most-common for one field.
     */
    public function common()
    {
        $this->begin('GET');

        $this->respond($this->doCommon(queryParam('field', '')));
    }

    /**
     * GET /api/dashboard/version - the cached ezXSS version status.
     */
    public function version()
    {
        $this->begin('GET');

        $this->respond($this->doVersion());
    }

    /**
     * The 12 real dashboard stats for the actor's scope. Admin reads the cached.
     */
    public function doStats()
    {
        if (!Auth::can('report.view') && !Auth::can('session.view')) {
            $this->cap('report.view');
        }
        $reportAll = Auth::can('report.viewall');
        $sessionAll = Auth::can('session.viewall');

        if ($reportAll && $sessionAll) {
            return Cache::remember(Report::CACHE_DASHBOARD_GLOBAL, function () {
                return array_merge(
                    Report::dashboardStats(array()),
                    Session::dashboardStats(array())
                );
            }, Report::CACHE_TTL);
        }

        $reportOpts = $reportAll ? array() : array(
            'scope' => Auth::can('report.view') ? Report::scopeFor(Auth::id()) : array(),
        );
        $sessionOpts = $sessionAll ? array() : array(
            'identifiers' => Auth::can('session.view') ? Payload::accessibleIdentifiers(Auth::id()) : array(),
        );
        $key = 'dashboard:stats:v' . Report::dashboardVersion() . ':' . Auth::id()
            . ':' . ($reportAll ? 'R' : 'r') . ($sessionAll ? 'S' : 's');

        return Cache::remember($key, function () use ($reportOpts, $sessionOpts) {
            return array_merge(
                Report::dashboardStats($reportOpts),
                Session::dashboardStats($sessionOpts)
            );
        }, Report::CACHE_TTL);
    }

    /**
     * Top-10 most-common values for one field within the actor's scope. Admin.
     *
     * @param string $field
     * @throws HttpException
     */
    public function doCommon($field)
    {
        $this->cap('report.view');
        $field = strtolower(trim((string) $field));
        if (!in_array($field, Report::COMMON_FIELDS, true)) {
            throw new HttpException(422, 'Unknown most-common field');
        }

        if (Auth::can('report.viewall')) {
            return Cache::remember(Report::CACHE_COMMON_PREFIX . 'v' . Report::dashboardVersion() . ':' . $field, function () use ($field) {
                return $this->computeCommon($field, Report::commonData(array()));
            }, Report::CACHE_TTL);
        }

        $scope = Report::scopeFor(Auth::id());
        $key = Report::CACHE_COMMON_PREFIX . 'v' . Report::dashboardVersion() . ':' . $field . ':' . Auth::id();

        return Cache::remember($key, function () use ($field, $scope) {
            return $this->computeCommon($field, Report::commonData(array('scope' => $scope)));
        }, Report::CACHE_TTL);
    }

    /**
     * Count the chosen field across the given report rows and return the top 10.
     *
     * @param string $field
     * @param array $rows
     * @return array
     */
    public function computeCommon($field, array $rows)
    {
        $results = array();

        foreach ($rows as $row) {
            if ($field === 'origins') {
                $values = array((string) (isset($row['origin']) ? $row['origin'] : ''));
            } elseif ($field === 'ips') {
                $values = array((string) (isset($row['ip']) ? $row['ip'] : ''));
            } elseif ($field === 'payloads') {

                $values = array(Report::domainOf((string) (isset($row['payload']) ? $row['payload'] : '')));
            } elseif ($field === 'browsers') {
                $values = array(userAgentLabel((string) (isset($row['useragent']) ? $row['useragent'] : '')));
            } else {
                $values = $this->parseCookieNames((string) (isset($row['cookies']) ? $row['cookies'] : ''));
            }

            foreach ($values as $value) {
                if ($value === '') {
                    continue;
                }
                $results[$value] = isset($results[$value]) ? $results[$value] + 1 : 1;
            }
        }

        arsort($results);

        $data = array();
        foreach ($results as $value => $count) {
            $data[] = array('value' => (string) $value, 'count' => (int) $count);
            if (count($data) === 10) {
                break;
            }
        }
        return $data;
    }

    /**
     * The cached ezXSS version status. Pings status.ezxss.com at most once.
     */
    public function doVersion()
    {
        $this->cap('report.view');

        $current = (string) Config::get('VERSION', '5.0');

        $cached = Cache::get(self::VERSION_CACHE);
        if (is_array($cached) && isset($cached['checked_at'])
            && (time() - (int) $cached['checked_at']) < self::VERSION_TTL) {
            $cached['current'] = $current;

            return $this->mergeComparison($cached);
        }

        $fetched = $this->fetchVersionStatus($current);
        if ($fetched !== null) {
            $result = array(
                'current'    => $current,
                'latest'     => $fetched['latest'],
                'changelog'  => $fetched['changelog'],
                'download'   => $fetched['download'],
                'checked_at' => time(),
            );
            Cache::set(self::VERSION_CACHE, $result);
            return $this->mergeComparison($result);
        }

        if (is_array($cached)) {
            $cached['current'] = $current;
            return $this->mergeComparison($cached);
        }

        return $this->mergeComparison(array(
            'current'    => $current,
            'latest'     => null,
            'changelog'  => null,
            'download'   => null,
            'checked_at' => null,
        ));
    }

    /**
     * Compare the running version against the latest public release. state.
     *
     * @param string $current
     * @param string $latest
     * @return array
     */
    public function compareVersion($current, $latest)
    {
        $current = (string) $current;
        $hasLatest = is_string($latest) && $latest !== '';
        if (!$hasLatest) {
            return array('update_available' => false, 'state' => 'unknown');
        }
        if (version_compare($latest, $current, '>')) {
            return array('update_available' => true, 'state' => 'update_available');
        }
        if (version_compare($current, $latest, '>')) {
            return array('update_available' => false, 'state' => 'development');
        }
        return array('update_available' => false, 'state' => 'up_to_date');
    }

    /**
     * Merge the version comparison (update_available + state) into a version.
     *
     * @param array $result
     */
    private function mergeComparison(array $result)
    {
        $current = isset($result['current']) ? $result['current'] : '';
        $latest = isset($result['latest']) ? $result['latest'] : null;
        return array_merge($result, $this->compareVersion($current, $latest));
    }

    /**
     * Fetch the latest version from the status service.
     *
     * @param string $version
     * @return string|null
     */
    private function fetchVersionStatus($version)
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $url = 'https://status.ezxss.com/?v=' . rawurlencode((string) $version);

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'ezXSS');
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            if (defined('CURLPROTO_HTTPS')) {
                curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
                curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
            }
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body === false || $status < 200 || $status >= 300) {
                return null;
            }
            return $this->parseVersionRelease($body);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Parse the version response body.
     *
     * @param string $raw
     * @return array|null
     */
    public function parseVersionRelease($raw)
    {
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded) || !isset($decoded[0]) || !is_array($decoded[0])) {
            return null;
        }

        $rel = $decoded[0];
        return array(
            'latest'    => isset($rel['release']) ? (string) $rel['release'] : null,
            'changelog' => isset($rel['body']) ? (string) $rel['body'] : null,
            'download'  => isset($rel['zipball_url']) ? (string) $rel['zipball_url'] : null,
        );
    }

    /**
     * Split a cookies string into its cookie names.
     *
     * @param string $cookies
     */
    private function parseCookieNames($cookies)
    {
        $names = array();
        foreach (explode(';', (string) $cookies) as $cookie) {
            $pair = explode('=', $cookie, 2);
            $names[] = trim($pair[0]);
        }
        return $names;
    }

}
