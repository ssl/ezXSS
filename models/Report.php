<?php
class Report extends Model
{
    use Shareable;

    protected static $table = 'reports';
    protected static $primaryKey = 'id';

    /**
     * The reports_data table (big blobs).
     */
    const TABLE_DATA = 'reports_data';

    /**
     * Upper bounds for a filtered list count; beyond the cap the UI shows "N+".
     */
    const COUNT_CAP = 50000;
    const CATCHALL_COUNT_CAP = 2000;

    /**
     * Whitelist of columns usable for duplicate detection (sanitizes duplicate_columns).
     */
    const DUPLICATE_LIGHT = array('cookies', 'origin', 'referer', 'uri', 'useragent', 'ip', 'payload');

    /**
     * The default duplicate-detection column set.
     */
    const DUPLICATE_DEFAULT = array('cookies', 'origin', 'referer', 'uri', 'useragent', 'dom', 'ip');

    /**
     * Columns an on-request share may expose publicly (never meta/archive/id).
     */
    const SHAREABLE_COLUMNS = array(
        'payload', 'uri', 'ip', 'referer', 'useragent', 'cookies', 'origin',
        'dom', 'screenshot', 'localstorage', 'sessionstorage', 'extra', 'csp', 'created_at',
    );

    /**
     * Light columns searched by the list endpoint (no blobs).
     */
    const SEARCH_COLUMNS = array('payload', 'uri', 'ip', 'referer', 'useragent', 'origin', 'cookies');

    /**
     * Light summary columns returned by the list endpoint (never blobs).
     */
    const SUMMARY_COLUMNS = array('id', 'payload', 'uri', 'ip', 'referer', 'useragent', 'origin', 'archive', 'created_at');

    /**
     * Cache keys + TTL for the dashboard aggregates.
     */
    const CACHE_COUNT_PREFIX = 'reports:count:archive:';
    const CACHE_PER_PAYLOAD  = 'reports:count:bypayload';
    const CACHE_TTL          = 300;

    /**
     * Cache keys for the admin-global dashboard aggregates.
     */
    const CACHE_DASHBOARD_GLOBAL = 'dashboard:stats:global';
    const CACHE_COMMON_PREFIX    = 'dashboard:common:';

    /**
     * Version counter embedded in every per-user dashboard cache key, bumped to invalidate them all at once.
     */
    const CACHE_DASHBOARD_VERSION = 'dashboard:ver';

    /**
     * The most-common fields the dashboard may request (also used to invalidate).
     */
    const COMMON_FIELDS = array('origins', 'ips', 'cookies', 'browsers', 'payloads');

    /**
     * Default large-DOM threshold in bytes when the setting is unset.
     */
    const DOM_THRESHOLD_DEFAULT = 2097152;

    /**
     * Store a report: a light row in `reports` plus a blob row in `reports_data`.
     *
     * @param array $fields
     */
    public static function add(array $fields)
    {
        $payload   = (string) ($fields['payload'] ?? '');
        $uri       = utf8Clean($fields['uri'] ?? '');
        $ip        = utf8Clean($fields['ip'] ?? '');
        $referer   = utf8Clean($fields['referer'] ?? '');
        $useragent = utf8Clean($fields['useragent'] ?? '');
        $cookies   = utf8Clean($fields['cookies'] ?? '');
        $origin    = utf8Clean($fields['origin'] ?? '');
        $createdAt = isset($fields['created_at']) ? (int) $fields['created_at'] : time();

        $meta = (isset($fields['meta']) && (is_array($fields['meta']) || is_object($fields['meta'])))
            ? json_encode($fields['meta'], defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0)
            : null;
        // meta is nullable: fall back to NULL on a failed encode so the light row still inserts.
        if ($meta === false) {
            $meta = null;
        }

        $dom            = utf8Clean($fields['dom'] ?? '');
        $screenshot     = utf8Clean($fields['screenshot'] ?? '');
        $localStorage   = utf8Clean($fields['localstorage'] ?? '');
        $sessionStorage = utf8Clean($fields['sessionstorage'] ?? '');
        $extra          = utf8Clean($fields['extra'] ?? '');

        $compressed = 0;
        if (Compression::enabled()) {
            $dom = Compression::pack($dom);
            $screenshot = Compression::packScreenshot($screenshot);
            $localStorage = Compression::packJson($localStorage);
            $sessionStorage = Compression::packJson($sessionStorage);
            $extra = Compression::pack($extra);
            $compressed = 1;
        }

        $reportId = (int) self::query()->insert(array(
            'payload'     => $payload,
            'uri'         => $uri,
            'ip'          => $ip,
            'referer'     => $referer,
            'useragent'   => $useragent,
            'cookies'     => $cookies,
            'origin'      => $origin,
            'archive'     => 0,
            'meta'        => $meta,
            'created_at'  => $createdAt,
        ));

        // Best-effort blob: keep the light report row even if the data row cannot be written (for
        // example a DOM over max_allowed_packet). A partial report beats losing it.
        try {
            self::query()->from(self::TABLE_DATA)->insert(array(
                'report_id'      => $reportId,
                'dom'            => $dom,
                'screenshot'     => $screenshot,
                'localstorage'   => $localStorage,
                'sessionstorage' => $sessionStorage,
                'extra'          => $extra,
                'compressed'     => $compressed,
            ));
        } catch (Throwable $e) {
            error_log('ezXSS: report ' . $reportId . ' stored without its data row: ' . $e->getMessage());
        }

        self::invalidateCaches();

        return $reportId;
    }

    /**
     * Get a report merged with its (decompressed) blob row, or null.
     *
     * @param int $id
     * @return array|null
     */
    public static function getFull($id)
    {
        $report = self::find((int) $id);
        if (!$report) {
            return null;
        }
        return array_merge($report, self::dataFor((int) $id));
    }

    /**
     * The decompressed blob row for a report (dom/screenshot/localstorage/sessionstorage/extra).
     *
     * @param int $reportId
     * @return array
     */
    public static function dataFor($reportId)
    {
        $row = self::query()->from(self::TABLE_DATA)->where('report_id', (int) $reportId)->first();

        if (!$row) {
            return array('dom' => '', 'screenshot' => '', 'localstorage' => '', 'sessionstorage' => '', 'extra' => '');
        }

        if ((int) (isset($row['compressed']) ? $row['compressed'] : 0) === 1) {
            $row['dom'] = Compression::unpack($row['dom']);
            $row['screenshot'] = Compression::unpackScreenshot($row['screenshot']);
            $row['localstorage'] = Compression::unpackJson($row['localstorage']);
            $row['sessionstorage'] = Compression::unpackJson($row['sessionstorage']);
            $row['extra'] = Compression::unpack($row['extra']);
        }

        return array(
            'dom'            => isset($row['dom']) ? $row['dom'] : '',
            'screenshot'     => isset($row['screenshot']) ? $row['screenshot'] : '',
            'localstorage'   => isset($row['localstorage']) ? $row['localstorage'] : '',
            'sessionstorage' => isset($row['sessionstorage']) ? $row['sessionstorage'] : '',
            'extra'          => isset($row['extra']) ? $row['extra'] : '',
        );
    }

    /**
     * Fill a withheld heavy field (dom|screenshot) on an existing report, matching.
     *
     * @param int $reportId
     * @param string $field
     * @param string $value
     * @return bool
     */
    public static function attachData($reportId, $field, $value)
    {
        $reportId = (int) $reportId;
        if ($field !== 'dom' && $field !== 'screenshot') {
            return false;
        }
        $value = (string) $value;

        $row = self::query()->from(self::TABLE_DATA)->where('report_id', $reportId)->first();
        if (!$row) {
            return false;
        }
        $compressed = (int) (isset($row['compressed']) ? $row['compressed'] : 0) === 1;

        if ($field === 'dom') {
            $stored = $compressed ? Compression::pack($value) : $value;
        } else {
            $stored = $compressed ? Compression::packScreenshot($value) : $value;
        }

        self::query()->from(self::TABLE_DATA)->where('report_id', $reportId)->update(array($field => $stored));

        if ($field === 'dom') {
            self::setMetaDomSize($reportId, strlen($value));
        }
        return true;
    }

    /**
     * Rewrite a report's meta.dom_size so the panel's inline/download split stays correct.
     *
     * @param int $reportId
     * @param int $size
     */
    private static function setMetaDomSize($reportId, $size)
    {
        $report = self::find((int) $reportId);
        if (!$report) {
            return;
        }
        $meta = array();
        if (isset($report['meta']) && $report['meta'] !== null && $report['meta'] !== '') {
            $decoded = json_decode((string) $report['meta'], true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }
        $meta['dom_size'] = (int) $size;
        self::query()->where('id', (int) $reportId)->update(array('meta' => json_encode($meta, defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0)));
    }

    /**
     * Resolve the duplicate-detection column set from the `duplicate_columns` setting (CSV).
     */
    public static function duplicateColumns()
    {
        $raw = (string) Setting::get('duplicate_columns', '');
        $cols = array();
        if ($raw !== '') {
            foreach (explode(',', $raw) as $part) {
                $part = strtolower(trim($part));
                if ($part === '') {
                    continue;
                }
                if (in_array($part, self::DUPLICATE_LIGHT, true) || $part === 'dom') {
                    $cols[] = $part;
                }
            }
        }
        if (empty($cols)) {
            $cols = self::DUPLICATE_DEFAULT;
        }
        return array_values(array_unique($cols));
    }

    /**
     * Search for a duplicate report comparing on the given column set.
     *
     * @param array $values
     * @param array $columns
     * @return bool
     */
    public static function searchForDuplicates(array $values, array $columns)
    {
        $query = self::query()->select('id');
        $useDom = false;
        $usedAny = false;

        foreach ($columns as $col) {
            if ($col === 'dom') {
                $useDom = true;
                continue;
            }
            if (!in_array($col, self::DUPLICATE_LIGHT, true)) {
                continue;
            }
            $query->where($col, isset($values[$col]) ? (string) $values[$col] : '');
            $usedAny = true;
        }

        if (!$usedAny && !$useDom) {
            return false;
        }

        $row = $query->orderBy('id', 'DESC')->first();
        if (!$row) {
            return false;
        }

        if ($useDom) {
            $data = self::dataFor((int) $row['id']);
            $dom = isset($values['dom']) ? (string) $values['dom'] : '';
            if ((string) $data['dom'] !== $dom) {
                return false;
            }
        }

        return (int) $row['id'];
    }

    /**
     * Host-only normalizer for a payload string, mirrored by the frontend payloadDomain().
     *
     * @param string $raw
     * @return string
     */
    public static function domainOf($raw)
    {
        $raw = strtolower((string) $raw);
        if ($raw === '') {
            return '';
        }

        $raw = preg_replace('/^[a-z][a-z0-9+.\-]*:\/\//', '', $raw);

        if (strpos($raw, '//') === 0) {
            $raw = substr($raw, 2);
        }

        $parts = preg_split('/[\/?#:]/', $raw, 2);
        $host = $parts[0];

        if ($host !== '' && substr($host, -1) === '.') {
            $host = substr($host, 0, -1);
        }

        if (strlen($host) > 255) {
            $host = substr($host, 0, 255);
        }
        return $host;
    }

    /**
     * Build the OR of predicates matching every stored `payload` whose host equals $domain.
     *
     * @param string $domain
     * @param array &$params
     * @return string
     */
    private static function payloadDomainClause($domain, &$params)
    {
        $d = strtolower((string) $domain);

        $de = str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $d);

        $boundaries = array('/%', '?%', '#%', ':%');
        $clauses = array();

        $clauses[] = '`payload` = ?';
        $params[] = $d;
        $clauses[] = '`payload` = ?';
        $params[] = '//' . $d;

        foreach ($boundaries as $b) {
            $clauses[] = "`payload` LIKE ? ESCAPE '\\\\'";
            $params[] = $de . $b;
        }

        foreach ($boundaries as $b) {
            $clauses[] = "`payload` LIKE ? ESCAPE '\\\\'";
            $params[] = '//' . $de . $b;
        }

        return '(' . implode(' OR ', $clauses) . ')';
    }

    /**
     * Paginated, searchable report listing (summary columns only, never the blobs).
     *
     * @param array $opts
     * @return array
     */
    public static function listFor(array $opts)
    {
        $db = Database::getInstance();

        $where = array();
        $params = array();

        $archive = isset($opts['archive']) ? (int) $opts['archive'] : 0;
        $where[] = '`archive` = ?';
        $params[] = $archive;

        $catchall = !empty($opts['catchall']);

        $payloadDomain = '';
        if ($catchall) {
            $where[] = Payload::catchAllNotExistsSql('reports');
        } elseif (isset($opts['payload']) && $opts['payload'] !== null && $opts['payload'] !== '') {
            $payloadDomain = self::domainOf((string) $opts['payload']);
            if ($payloadDomain !== '') {
                $where[] = self::payloadDomainClause($payloadDomain, $params);
            }
        }

        if (isset($opts['search']) && $opts['search'] !== null && $opts['search'] !== '') {
            $like = '%' . QueryBuilder::escapeLike($opts['search']) . '%';
            $ors = array();
            foreach (self::SEARCH_COLUMNS as $col) {
                $ors[] = '`' . $col . "` LIKE ? ESCAPE '\\\\'";
                $params[] = $like;
            }
            $where[] = '(' . implode(' OR ', $ors) . ')';
        }

        if (array_key_exists('scope', $opts) && $opts['scope'] !== null) {
            $scopeSql = self::scopeClause($opts['scope'], $params);
            if ($scopeSql === null) {
                return array('rows' => array(), 'total' => 0);
            }
            $where[] = $scopeSql;
        }

        $whereSql = empty($where) ? '' : (' WHERE ' . implode(' AND ', $where));

        $filtered = (isset($opts['search']) && $opts['search'] !== '' && $opts['search'] !== null)
            || $payloadDomain !== ''
            || $catchall
            || (array_key_exists('scope', $opts) && $opts['scope'] !== null);

        $capped = false;

        $capFilter = $catchall || $payloadDomain !== '';
        if (!empty($opts['cacheTotal']) && !$filtered) {
            $total = self::countCached($archive);
        } elseif ($capFilter) {
            $cap = $catchall ? self::CATCHALL_COUNT_CAP : self::COUNT_CAP;
            $stmt = $db->prepare('SELECT COUNT(*) FROM (SELECT 1 FROM `reports`' . $whereSql . ' LIMIT ' . ($cap + 1) . ') `t`');
            $stmt->execute($params);
            $n = (int) $stmt->fetchColumn();
            $capped = $n > $cap;
            $total = $capped ? $cap : $n;
        } else {
            $stmt = $db->prepare('SELECT COUNT(*) FROM `reports`' . $whereSql);
            $stmt->execute($params);
            $total = (int) $stmt->fetchColumn();
        }

        $page = QueryBuilder::page($opts, 25);

        $cols = '`' . implode('`, `', self::SUMMARY_COLUMNS) . '`';
        $stmt = $db->prepare(
            'SELECT ' . $cols . ' FROM `reports`' . $whereSql .
            ' ORDER BY `id` DESC LIMIT ' . $page['offset'] . ', ' . $page['limit']
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array('rows' => $rows, 'total' => $total, 'capped' => $capped);
    }

    /**
     * Resolve every shareable column for a report into a flat map, or null when missing.
     *
     * @param int $id
     * @return array|null
     */
    public static function columnValues($id)
    {
        $report = self::find((int) $id);
        if (!$report) {
            return null;
        }
        $data = self::dataFor((int) $id);

        $merged = array_merge($report, $data);
        $out = array();
        foreach (self::SHAREABLE_COLUMNS as $col) {
            $out[$col] = isset($merged[$col]) ? $merged[$col] : '';
        }

        $out['csp'] = '';
        if (isset($merged['meta']) && $merged['meta'] !== '' && $merged['meta'] !== null) {
            $meta = json_decode((string) $merged['meta'], true);
            if (is_array($meta) && isset($meta['csp']['policy'])) {
                $out['csp'] = (string) $meta['csp']['policy'];
            }
        }

        return $out;
    }

    /**
     * Present a stored screenshot value as a file URL, an inline data URI, or null when empty.
     *
     * @param string $value
     * @return array|null
     */
    public static function presentScreenshot($value)
    {
        $value = (string) $value;
        if ($value === '') {
            return null;
        }
        if (Compression::isStoredScreenshotFile($value)) {
            return array('type' => 'file', 'url' => '/assets/img/report-' . $value . '.png');
        }
        return array('type' => 'base64', 'value' => 'data:image/png;base64,' . $value);
    }

    /**
     * The large-DOM threshold in bytes from the `dom_threshold` setting (default 2 MB).
     */
    public static function domThreshold()
    {
        $raw = Setting::get('dom_threshold', null);
        if ($raw === null || !ctype_digit((string) $raw)) {
            return self::DOM_THRESHOLD_DEFAULT;
        }
        return (int) $raw;
    }

    /**
     * The byte size of a report's DOM (from meta.dom_size, else measured from the blob).
     *
     * @param array $report
     * @param array $data
     * @return int|string
     */
    public static function domSize($report, $data)
    {
        if (isset($report['meta']) && $report['meta'] !== null && $report['meta'] !== '') {
            $meta = json_decode((string) $report['meta'], true);
            if (is_array($meta) && isset($meta['dom_size']) && is_numeric($meta['dom_size'])) {
                return (int) $meta['dom_size'];
            }
        }
        return strlen((string) (isset($data['dom']) ? $data['dom'] : ''));
    }

    /**
     * Fully delete a report: its blob row, every share, the on-disk screenshot and the light row.
     *
     * @param int $id
     */
    public static function deleteFull($id)
    {
        $id = (int) $id;

        $blob = self::query()->from(self::TABLE_DATA)->where('report_id', $id)->first();
        if ($blob && isset($blob['screenshot']) && Compression::isStoredScreenshotFile((string) $blob['screenshot'])) {
            $file = FRAMEWORK_ROOT . '/assets/img/report-' . $blob['screenshot'] . '.png';
            if (is_file($file)) {
                @unlink($file);
            }
        }

        self::query()->from(self::TABLE_DATA)->where('report_id', $id)->delete();
        ReportShare::deleteForReport($id);
        self::query()->from('user_access')->where('for_table', 'reports')->where('for_id', $id)->delete();
        self::query()->where('id', $id)->delete();

        self::invalidateCaches();
    }

    /**
     * Toggle a report's archive flag (0 <-> 1). Returns the new flag.
     *
     * @param int $id
     * @throws HttpException
     */
    public static function toggleArchive($id)
    {
        $id = (int) $id;
        $report = self::find($id);
        if (!$report) {
            throw new HttpException(404, 'Report not found');
        }
        $new = ((int) $report['archive'] === 0) ? 1 : 0;
        self::query()->where('id', $id)->update(array('archive' => $new));
        self::invalidateCaches();
        return $new;
    }

    /**
     * Cached count of active/archived reports.
     *
     * @param bool $archive
     * @return int
     */
    public static function countCached($archive)
    {
        $archive = (int) $archive;
        $value = Cache::remember(self::CACHE_COUNT_PREFIX . $archive, function () use ($archive) {
            return self::query()->where('archive', $archive)->count();
        }, self::CACHE_TTL);
        return (int) $value;
    }

    /**
     * Cached per-payload report counts (payload => count), newest-heavy first.
     *
     * @return bool
     */
    public static function perPayloadCached()
    {
        $value = Cache::remember(self::CACHE_PER_PAYLOAD, function () {
            $rows = self::query()
                ->select('`payload`, COUNT(*) AS c')
                ->groupBy('payload')
                ->orderBy('c', 'DESC')
                ->get();

            $out = array();
            foreach ($rows as $row) {
                $domain = self::domainOf((string) $row['payload']);
                $out[$domain] = (isset($out[$domain]) ? $out[$domain] : 0) + (int) $row['c'];
            }
            arsort($out);
            return $out;
        }, self::CACHE_TTL);
        return is_array($value) ? $value : array();
    }

    /**
     * Dashboard report statistics over active and archived reports in scope.
     *
     * @param array $opts
     */
    public static function dashboardStats(array $opts)
    {
        $params = array();
        $whereSql = self::scopeWhere($opts, $params);
        if ($whereSql === null) {
            return self::emptyReportStats();
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT `origin`, `referer`, `created_at` FROM `reports`' . $whereSql . ' ORDER BY `id` ASC'
        );
        $stmt->execute($params);

        $weekAgo = time() - 604800;
        $stats = self::emptyReportStats();
        $domains = array();
        $weekDomains = array();
        $last = null;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats['reports']++;
            $origin = (string) (isset($row['origin']) ? $row['origin'] : '');
            $referer = (string) (isset($row['referer']) ? $row['referer'] : '');
            $time = (int) (isset($row['created_at']) ? $row['created_at'] : 0);

            if ($time > $weekAgo) {
                $stats['reports_week']++;
                if ($origin !== '' && !isset($weekDomains[$origin])) {
                    $weekDomains[$origin] = true;
                    $stats['report_domains_week']++;
                }
            }
            if ($origin !== '' && !isset($domains[$origin])) {
                $domains[$origin] = true;
                $stats['report_domains']++;
            }
            if (strpos($referer, 'Collected page via ') === 0) {
                $stats['collected_pages']++;
            }
            $last = $time;
        }

        $stats['last_report'] = $last;
        return $stats;
    }

    /**
     * The light columns the most-common panels count over (same scope as dashboardStats).
     *
     * @param array $opts
     * @return array
     */
    public static function commonData(array $opts)
    {
        $params = array();
        $whereSql = self::scopeWhere($opts, $params);
        if ($whereSql === null) {
            return array();
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT `origin`, `ip`, `cookies`, `useragent`, `payload` FROM `reports`' . $whereSql . ' ORDER BY `id` ASC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Zeroed report stat map (the shape dashboardStats always returns).
     *
     * @return array
     */
    private static function emptyReportStats()
    {
        return array(
            'reports'             => 0,
            'reports_week'        => 0,
            'report_domains'      => 0,
            'report_domains_week' => 0,
            'collected_pages'     => 0,
            'last_report'         => null,
        );
    }

    /**
     * WHERE clause for the dashboard aggregates: '' for no scope, null when nothing is in scope.
     *
     * @param array $opts
     * @param array &$params
     * @return string|null
     */
    private static function scopeWhere(array $opts, &$params)
    {
        if (!array_key_exists('scope', $opts) || $opts['scope'] === null) {
            return '';
        }
        $sql = self::scopeClause($opts['scope'], $params);
        return $sql === null ? null : ' WHERE ' . $sql;
    }

    /**
     * Reports a user can see without report.viewall: direct grants plus reports on their payloads.
     *
     * @param int $userId
     * @return array
     */
    public static function scopeFor($userId)
    {
        return array(
            'ids'         => self::accessibleIds($userId),
            'identifiers' => Payload::accessibleIdentifiers($userId),
        );
    }

    /**
     * SQL predicate for a scopeFor() result, or null when the scope is empty.
     *
     * @param array $scope
     * @param array &$params
     * @return string|null
     */
    private static function scopeClause(array $scope, &$params)
    {
        $ors = array();

        $ids = array();
        foreach ((array) (isset($scope['ids']) ? $scope['ids'] : array()) as $id) {
            $ids[(int) $id] = (int) $id;
        }
        if (!empty($ids)) {
            $ors[] = '`reports`.`id` IN (' . implode(',', array_values($ids)) . ')';
        }

        $idents = array();
        foreach ((array) (isset($scope['identifiers']) ? $scope['identifiers'] : array()) as $ident) {
            $idents[(string) $ident] = (string) $ident;
        }
        if (!empty($idents)) {
            // Same resolution as Payload::resolveByIdentifier: domain/path wins over the bare domain.
            $norm = "TRIM(BOTH '/' FROM SUBSTRING_INDEX(SUBSTRING_INDEX(LOWER(`reports`.`payload`), '?', 1), '://', -1))";
            $seg1 = 'SUBSTRING_INDEX(' . $norm . ", '/', 1)";
            $seg2 = 'SUBSTRING_INDEX(' . $norm . ", '/', 2)";
            $place = implode(', ', array_fill(0, count($idents), '?'));
            $ors[] = '(' . $seg2 . ' IN (' . $place . ') OR (' . $seg1 . ' IN (' . $place . ')'
                . ' AND NOT EXISTS (SELECT 1 FROM `payloads` `p` WHERE `p`.`identifier` = ' . $seg2
                . ' AND ' . $seg2 . ' <> ' . $seg1 . ')))';
            $params = array_merge($params, array_values($idents), array_values($idents));
        }

        return empty($ors) ? null : '(' . implode(' OR ', $ors) . ')';
    }

    /**
     * Whether a user can reach a report through a direct grant or through its payload.
     *
     * @param int $userId
     * @param int $id
     * @param string $type
     * @return bool
     */
    public static function userCan($userId, $id, $type)
    {
        if (self::hasAccess($userId, $id, $type, 'reports')) {
            return true;
        }
        $payload = self::payloadOf($id);
        return $payload ? Payload::hasAccess($userId, (int) $payload['id'], $type, 'payloads') : false;
    }

    /**
     * The payload row a report was collected on, or null.
     *
     * @param int $id
     * @return array|null
     */
    public static function payloadOf($id)
    {
        $report = self::find((int) $id);
        if (!$report || !isset($report['payload']) || (string) $report['payload'] === '') {
            return null;
        }
        return Payload::resolveByIdentifier((string) $report['payload']);
    }

    /**
     * Report ids a user can view: own grants plus global (user_id 0) grants.
     *
     * @param int $userId
     */
    public static function accessibleIds($userId)
    {
        $userId = (int) $userId;
        $rows = self::query()
            ->from('user_access')
            ->where('for_table', 'reports')
            ->where('view', 1)
            ->get();

        $ids = array();
        foreach ($rows as $row) {
            $uid = (int) $row['user_id'];
            if ($uid === $userId || $uid === 0) {
                $ids[(int) $row['for_id']] = (int) $row['for_id'];
            }
        }
        return array_values($ids);
    }

    /**
     * Invalidate every dashboard aggregate cache (called on add/delete/archive).
     */
    public static function invalidateCaches()
    {
        try {
            Cache::forget(self::CACHE_COUNT_PREFIX . '0');
            Cache::forget(self::CACHE_COUNT_PREFIX . '1');
            Cache::forget(self::CACHE_PER_PAYLOAD);
            Cache::forget(self::CACHE_DASHBOARD_GLOBAL);

            self::bumpDashboardVersion();
        } catch (Throwable $e) {
        }
    }

    /**
     * The current per-user dashboard cache version (0 when never bumped).
     */
    public static function dashboardVersion()
    {
        $value = Cache::get(self::CACHE_DASHBOARD_VERSION);
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Increment the per-user dashboard cache version and return the new value.
     */
    public static function bumpDashboardVersion()
    {
        $next = self::dashboardVersion() + 1;
        Cache::set(self::CACHE_DASHBOARD_VERSION, $next);
        return $next;
    }

    /**
     * Cache-key prefixes for beacon<->report correlation.
     */
    const CSP_NONCE_PREFIX = 'cspnonce:';
    const CSP_BUFFER_PREFIX = 'cspbuf:';
    /**
     * Max buffered directives for an un-correlated nonce.
     */
    const CSP_BUFFER_MAX = 50;

    /**
     * Merge a CSP-bypass signal into a report's `meta` JSON (deep-merged into meta.csp).
     *
     * @param int $id
     * @param array $patch
     */
    public static function updateMeta($id, array $patch)
    {
        $id = (int) $id;
        $report = self::find($id);
        if (!$report) {
            return;
        }

        $meta = array();
        if (isset($report['meta']) && $report['meta'] !== null && $report['meta'] !== '') {
            $decoded = json_decode((string) $report['meta'], true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        if (!isset($meta['csp']) || !is_array($meta['csp'])) {
            $meta['csp'] = array();
        }
        if (!isset($meta['csp']['permitted']) || !is_array($meta['csp']['permitted'])) {
            $meta['csp']['permitted'] = array();
        }
        if (!isset($meta['csp']['channels']) || !is_array($meta['csp']['channels'])) {
            $meta['csp']['channels'] = array();
        }

        if (isset($patch['directive']) && $patch['directive'] !== '') {
            if (!in_array($patch['directive'], $meta['csp']['permitted'], true)) {
                $meta['csp']['permitted'][] = $patch['directive'];
            }
        }
        if (isset($patch['channel']) && $patch['channel'] !== '') {
            $ch = (string) $patch['channel'];
            $meta['csp']['channels'][$ch] = (isset($meta['csp']['channels'][$ch]) ? (int) $meta['csp']['channels'][$ch] : 0) + 1;
        }
        $meta['csp']['last_seen'] = time();

        if (!empty($patch['sec_fetch']) && is_array($patch['sec_fetch']) && empty($meta['sec_fetch'])) {
            $meta['sec_fetch'] = $patch['sec_fetch'];
        }

        self::query()->where('id', $id)->update(array('meta' => json_encode($meta, defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0)));
    }

    /**
     * Correlate a freshly-stored report with its per-pageload beacon nonce.
     *
     * @param string $nonce
     * @param int $reportId
     */
    public static function linkBeaconNonce($nonce, $reportId)
    {
        $nonce = (string) $nonce;
        if ($nonce === '' || !preg_match('/^[a-z0-9]{1,64}$/', $nonce)) {
            return;
        }
        $reportId = (int) $reportId;

        try {
            Cache::set(self::CSP_NONCE_PREFIX . $nonce, $reportId);

            $buffered = Cache::get(self::CSP_BUFFER_PREFIX . $nonce);
            if (is_array($buffered) && !empty($buffered)) {
                foreach ($buffered as $directive) {
                    self::updateMeta($reportId, array('directive' => (string) $directive));
                }
                Cache::forget(self::CSP_BUFFER_PREFIX . $nonce);
            }
        } catch (Throwable $e) {
        }
    }

    /**
     * Route a beacon signal into the report pipeline.
     *
     * @param array $sig
     * @return bool
     */
    public static function recordBeaconSignal(array $sig)
    {
        $nonce = isset($sig['nonce']) ? (string) $sig['nonce'] : '';
        $type = isset($sig['type']) ? (string) $sig['type'] : '';
        $directive = isset($sig['directive']) ? (string) $sig['directive'] : '';
        $secFetch = (isset($sig['sec_fetch']) && is_array($sig['sec_fetch'])) ? $sig['sec_fetch'] : array();
        $core = (isset($sig['core']) && is_array($sig['core'])) ? $sig['core'] : null;

        if ($nonce === '' || !preg_match('/^[a-z0-9]{1,64}$/', $nonce)) {
            return false;
        }

        $reportId = Cache::get(self::CSP_NONCE_PREFIX . $nonce);
        if ($reportId !== null && ctype_digit((string) $reportId)) {
            self::updateMeta((int) $reportId, array(
                'directive' => $directive,
                'channel'   => $type,
                'sec_fetch' => $secFetch,
            ));
            return (int) $reportId;
        }

        if ($core !== null && !empty($core)
            && isset($core['payload']) && $core['payload'] !== ''
            && Payload::identifierExists((string) $core['payload'])) {
            $meta = array(
                'csp' => array(
                    'nonce'      => $nonce,
                    'permitted'  => $directive !== '' ? array($directive) : array(),
                    'channels'   => $type !== '' ? array($type => 1) : array(),
                    'inferred'   => true,
                    'first_seen' => time(),
                    'last_seen'  => time(),
                ),
                'sec_fetch' => (object) $secFetch,
            );
            $newId = (int) self::add(array(
                'payload'    => isset($core['payload']) ? (string) $core['payload'] : '',
                'uri'        => isset($core['uri']) ? (string) $core['uri'] : '',
                'origin'     => isset($core['origin']) ? (string) $core['origin'] : '',
                'referer'    => isset($core['referer']) ? (string) $core['referer'] : '',
                'cookies'    => isset($core['cookies']) ? (string) $core['cookies'] : '',
                'useragent'  => isset($core['useragent']) ? (string) $core['useragent'] : '',
                'ip'         => isset($core['ip']) ? (string) $core['ip'] : '',
                'meta'       => $meta,
                'created_at' => time(),
            ));

            try {
                Cache::set(self::CSP_NONCE_PREFIX . $nonce, $newId);
            } catch (Throwable $e) {
            }

            try {
                $report = self::getFull($newId);
                if (is_array($report)) {
                    $report['time'] = time();
                    $report['domain'] = defined('HOST') ? HOST : '';
                    Alert::dispatch($report);
                }
            } catch (Throwable $e) {
            }

            return $newId;
        }

        if ($directive !== '') {
            $buf = Cache::get(self::CSP_BUFFER_PREFIX . $nonce);
            if (!is_array($buf)) {
                $buf = array();
            }
            if (!in_array($directive, $buf, true) && count($buf) < self::CSP_BUFFER_MAX) {
                $buf[] = $directive;
                try {
                    Cache::set(self::CSP_BUFFER_PREFIX . $nonce, $buf);
                } catch (Throwable $e) {
                }
            }
        }

        return false;
    }
}
