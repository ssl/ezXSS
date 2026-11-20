<?php
class SessionsController extends Controller
{
    /**
     * Routable HTTP actions (everything else is an internal helper).
     */
    public static $routable = array('index', 'get', 'requests', 'request', 'archive', 'delete', 'bulk', 'count', 'payloads');

    /**
     * Maximum number of groups a single scope-bulk request may resolve and act on.
     */
    const SCOPE_MAX = 2000;

    /**
     * GET /api/sessions - paginated grouped listing, archive/payload filtered.
     */
    public function index()
    {
        $this->begin('GET');

        $result = $this->doList();
        $this->respond($result['data'], array('pagination' => $result['pagination']));
    }

    /**
     * GET /api/sessions/get/{id} - one session group with decompressed blob.
     *
     * @param int $id
     */
    public function get($id)
    {
        $this->begin();
        $this->respond($this->doGet($id));
    }

    /**
     * GET /api/sessions/requests/{id} - the group's request/navigation history.
     *
     * @param int $id
     */
    public function requests($id)
    {
        $this->begin();
        $result = $this->doRequests($id);
        $this->respond($result['data'], array('pagination' => $result['pagination']));
    }

    /**
     * GET /api/sessions/request/{requestId} - a single request row (full).
     *
     * @param string $requestId
     */
    public function request($requestId)
    {
        $this->begin();
        $this->respond($this->doRequest($requestId));
    }

    /**
     * POST /api/sessions/archive/{id} - toggle the group's archive flag.
     *
     * @param int $id
     */
    public function archive($id)
    {
        $this->begin('POST');

        $this->respond($this->doArchive($id));
    }

    /**
     * DELETE /api/sessions/delete/{id} - delete group + blobs + console rows.
     *
     * @param int $id
     */
    public function delete($id)
    {
        $this->begin('DELETE');

        $this->respond($this->doDelete($id));
    }

    /**
     * POST /api/sessions/bulk - archive/unarchive/delete an id array in one.
     */
    public function bulk()
    {
        $this->begin('POST');

        $this->respond($this->doBulk($this->input()));
    }

    /**
     * GET /api/sessions/count - the number of groups a scope resolves.
     */
    public function count()
    {
        $this->begin('GET');

        $this->respond($this->doCount());
    }

    /**
     * GET /api/sessions/payloads - distinct payload identifiers that have.
     */
    public function payloads()
    {
        $this->begin('GET');

        $this->respond($this->doPayloads());
    }

    /**
     * Build the paginated grouped list for the actor. Admins list all groups.
     *
     * @return array
     */
    public function doList()
    {
        $perPage = $this->intParam('per_page', 25, 1, 200);
        $page = $this->intParam('page', 1, 1, PHP_INT_MAX);
        $offset = ($page - 1) * $perPage;

        $archiveRaw = queryParam('archive', '0');
        $archive = ($archiveRaw === '1') ? 1 : 0;
        $payload = queryParam('payload', '');
        $payloadStr = is_string($payload) ? trim($payload) : '';
        $clientId = queryParam('client_id', '');
        $search = queryParam('search', '');

        $viewAll = Auth::can('session.viewall');
        $catchall = $viewAll && $payloadStr === Payload::CATCHALL_FILTER;

        $opts = array(
            'archive'     => $archive,
            'payload'     => $catchall ? '' : $payloadStr,
            'catchall'    => $catchall,
            'client_id'   => is_string($clientId) ? trim($clientId) : '',
            'search'      => is_string($search) ? trim($search) : '',
            'seen_within' => $this->validRecency(queryParam('seen_within', null)),
            'seen_before' => $this->validRecency(queryParam('seen_before', null)),
            'limit'       => $perPage,
            'offset'      => $offset,
        );

        if (!$viewAll) {
            $opts['identifiers'] = Payload::accessibleIdentifiers(Auth::id());
        }

        $result = Session::listGroups($opts);
        $rows = array();
        foreach ($result['rows'] as $row) {
            $rows[] = $this->summaryRow($row);
        }

        $total = (int) $result['total'];

        return array(
            'data'       => $rows,
            'pagination' => $this->pagination($total, $page, $perPage),
        );
    }

    /**
     * Count the groups matching the current query filter.
     *
     * @return array
     */
    public function doCount()
    {
        $archiveRaw = queryParam('archive', '0');
        $archive = ($archiveRaw === '1') ? 1 : 0;
        $payload = queryParam('payload', '');
        $payloadStr = is_string($payload) ? trim($payload) : '';
        $clientId = queryParam('client_id', '');
        $search = queryParam('search', '');

        $viewAll = Auth::can('session.viewall');
        $catchall = $viewAll && $payloadStr === Payload::CATCHALL_FILTER;

        $opts = array(
            'archive'     => $archive,
            'payload'     => $catchall ? '' : $payloadStr,
            'catchall'    => $catchall,
            'client_id'   => is_string($clientId) ? trim($clientId) : '',
            'search'      => is_string($search) ? trim($search) : '',
            'seen_within' => $this->validRecency(queryParam('seen_within', null)),
            'seen_before' => $this->validRecency(queryParam('seen_before', null)),
        );

        if (!$viewAll) {
            $opts['identifiers'] = Payload::accessibleIdentifiers(Auth::id());
        }

        return array('count' => Session::countGroups($opts));
    }

    /**
     * Full detail for a session group (latest row + decompressed blob).
     *
     * @param int $id
     * @throws HttpException
     */
    public function doGet($id)
    {
        $this->cap('session.view');
        $id = $this->validId($id, 'session');
        $group = $this->resolveGroup($id);
        $this->accessForGroup($group['payload'], 'view');

        $latest = Session::findLatest($group['client_id'], $group['origin'], $group['payload']);
        if (!$latest) {
            throw new HttpException(404, 'Session not found');
        }
        $data = Session::dataFor((int) $latest['id']);

        return array(
            'id'             => (int) $latest['id'],
            'client_id'      => $latest['client_id'],
            'payload'        => $latest['payload'],
            'uri'            => $latest['uri'],
            'ip'             => $latest['ip'],
            'referer'        => $latest['referer'],
            'useragent'      => $latest['useragent'],
            'browser'        => $this->parseBrowser((string) $latest['useragent']),
            'cookies'        => isset($latest['cookies']) ? $latest['cookies'] : '',
            'origin'         => $latest['origin'],
            'archive'        => (int) $latest['archive'],
            'created_at'     => isset($latest['created_at']) ? (int) $latest['created_at'] : null,
            'last'           => isset($latest['updated_at']) ? (int) $latest['updated_at'] : null,
            'requests'       => Session::requestCount($group['client_id'], $group['origin'], $group['payload']),
            'dom'            => $data['dom'],
            'localstorage'   => $data['localstorage'],
            'sessionstorage' => $data['sessionstorage'],
            'console'        => Session::consoleOutput($group['client_id'], $group['origin'], $group['payload']),
        );
    }

    /**
     * The group's request/navigation history (light rows).
     *
     * @param int $id
     */
    public function doRequests($id)
    {
        $this->cap('session.view');
        $id = $this->validId($id, 'session');
        $group = $this->resolveGroup($id);
        $this->accessForGroup($group['payload'], 'view');

        $perPage = $this->intParam('per_page', 50, 1, 200);
        $page = $this->intParam('page', 1, 1, PHP_INT_MAX);
        $offset = ($page - 1) * $perPage;
        $search = queryParam('search', '');
        $search = is_string($search) ? trim($search) : '';

        $result = Session::requestsPage($group['client_id'], $group['origin'], array(
            'limit'  => $perPage,
            'offset' => $offset,
            'search' => $search,
        ), $group['payload']);

        $out = array();
        foreach ($result['rows'] as $row) {
            $summary = $this->summaryRow($row);
            $summary['shorturi'] = substr((string) $row['uri'], 0, 50);
            $out[] = $summary;
        }

        $total = (int) $result['total'];

        return array(
            'data'       => $out,
            'pagination' => $this->pagination($total, $page, $perPage),
        );
    }

    /**
     * One request row with its full decompressed blob.
     *
     * @param string $requestId
     * @throws HHttpException
     */
    public function doRequest($requestId)
    {
        $this->cap('session.view');
        $requestId = $this->validId($requestId, 'request');
        $row = Session::find($requestId);
        if (!$row) {
            throw new HttpException(404, 'Request not found');
        }
        $this->accessForGroup($row['payload'], 'view');

        $data = Session::dataFor($requestId);

        return array(
            'id'             => (int) $row['id'],
            'client_id'      => $row['client_id'],
            'payload'        => $row['payload'],
            'uri'            => $row['uri'],
            'ip'             => $row['ip'],
            'referer'        => $row['referer'],
            'useragent'      => $row['useragent'],
            'browser'        => $this->parseBrowser((string) $row['useragent']),
            'cookies'        => isset($row['cookies']) ? $row['cookies'] : '',
            'origin'         => $row['origin'],
            'archive'        => (int) $row['archive'],
            'created_at'     => isset($row['created_at']) ? (int) $row['created_at'] : null,
            'last'           => isset($row['updated_at']) ? (int) $row['updated_at'] : null,
            'dom'            => $data['dom'],
            'localstorage'   => $data['localstorage'],
            'sessionstorage' => $data['sessionstorage'],
            'console'        => $data['console'],
        );
    }

    /**
     * Toggle the group's archive flag (needs EDIT access).
     *
     * @param int $id
     * @return array
     */
    public function doArchive($id)
    {
        $this->cap('session.view');
        $id = $this->validId($id, 'session');
        $group = $this->resolveGroup($id);
        $this->accessForGroup($group['payload'], 'edit');

        $flag = Session::toggleArchive($group['client_id'], $group['origin'], $group['payload']);
        return array('id' => $id, 'archive' => (int) $flag);
    }

    /**
     * Delete the whole group + its blobs + its console rows (needs EDIT access).
     *
     * @param int $id
     * @return array
     */
    public function doDelete($id)
    {
        $this->cap('session.view');
        $id = $this->validId($id, 'session');
        $group = $this->resolveGroup($id);
        $this->accessForGroup($group['payload'], 'edit');

        $removed = Session::deleteGroup($group['client_id'], $group['origin'], $group['payload']);
        return array('deleted' => true, 'id' => $id, 'removed' => $removed);
    }

    /**
     * Apply a bulk state change (archive/unarchive/delete) over an id array. Each.
     *
     * @param string $body
     * @throws HttpException
     */
    public function doBulk($body)
    {
        $this->cap('session.view');
        $action = isset($body['action']) ? (string) $body['action'] : '';
        if (!in_array($action, array('archive', 'unarchive', 'delete'), true)) {
            throw new HttpException(422, 'Invalid bulk action');
        }

        $hasIds = array_key_exists('ids', $body) && $body['ids'] !== null;
        $hasScope = array_key_exists('scope', $body) && $body['scope'] !== null;
        $matched = null;
        if ($hasIds && $hasScope) {
            throw new HttpException(422, 'provide either ids or scope, not both');
        } elseif ($hasIds) {
            $mode = 'ids';
            $ids = $this->validBulkIds($body['ids']);
        } elseif ($hasScope) {
            $mode = 'scope';
            $resolved = $this->resolveScopeIds($body['scope']);
            $ids = $resolved['ids'];
            $matched = $resolved['matched'];
        } else {
            throw new HttpException(422, 'ids or scope is required');
        }

        $applied = 0;
        $skipped = array();
        foreach ($ids as $id) {
            try {
                $group = $this->resolveGroup($id);
                $this->accessForGroup($group['payload'], 'edit');
            } catch (Exception $e) {
                $skipped[] = array('id' => $id, 'reason' => $this->skipReason($e));
                continue;
            }

            if ($action === 'delete') {
                Session::deleteGroup($group['client_id'], $group['origin'], $group['payload']);
            } else {
                Session::setArchiveGroup($group['client_id'], $group['origin'], $action === 'archive' ? 1 : 0, $group['payload']);
            }
            $applied++;
        }

        $out = array(
            'action'    => $action,
            'mode'      => $mode,
            'requested' => $mode === 'scope' ? (int) $matched : count($ids),
            'applied'   => $applied,
            'skipped'   => $skipped,
        );
        if ($mode === 'scope') {
            $out['matched'] = (int) $matched;
        }
        return $out;
    }

    /**
     * Resolve a scope descriptor to the representative ids of every matching.
     *
     * @param string $scope
     * @throws HttpException
     * @return array
     */
    public function resolveScopeIds($scope)
    {
        $opts = $this->validScope($scope);

        if (Auth::can('session.viewall')) {
            if (isset($opts['payload']) && $opts['payload'] === Payload::CATCHALL_FILTER) {
                $opts['catchall'] = true;
                $opts['payload'] = '';
            }
        } else {
            $opts['identifiers'] = Payload::accessibleIdentifiers(Auth::id());
        }

        $matched = Session::countGroups($opts);
        if ($matched > self::SCOPE_MAX) {
            throw new HttpException(422, 'scope too large (' . $matched . ' groups) - narrow the filter');
        }

        $opts['limit'] = self::SCOPE_MAX;
        $ids = Session::groupRepresentativeIds($opts);

        return array('ids' => $ids, 'matched' => $matched);
    }

    /**
     * Strictly validate a recency window (seconds). null/'' -> null.
     *
     * @param string $raw
     * @throws HttpException
     * @return string|null
     */
    private function validRecency($raw)
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (!ctype_digit((string) $raw)) {
            throw new HttpException(422, 'recency must be a positive integer');
        }
        $v = (int) $raw;
        if ($v < 1 || $v > 2592000) {
            throw new HttpException(422, 'recency out of range (1..2592000)');
        }
        return $v;
    }

    /**
     * Normalize a scope object into a countGroups/groupRepresentativeIds $opts:
     *
     * @param string $raw
     * @throws HHttpException
     */
    private function validScope($raw)
    {
        if (!is_array($raw)) {
            throw new HttpException(422, 'scope must be an object');
        }

        $archiveRaw = isset($raw['archive']) ? $raw['archive'] : 0;
        $archive = ((int) $archiveRaw === 1 || $archiveRaw === '1') ? 1 : 0;
        $payload = isset($raw['payload']) ? $raw['payload'] : '';
        $clientId = isset($raw['client_id']) ? $raw['client_id'] : '';
        $search = isset($raw['search']) ? $raw['search'] : '';

        return array(
            'archive'     => $archive,
            'payload'     => is_string($payload) ? trim($payload) : '',
            'client_id'   => is_string($clientId) ? trim($clientId) : '',
            'search'      => is_string($search) ? trim($search) : '',
            'seen_within' => $this->validRecency(isset($raw['seen_within']) ? $raw['seen_within'] : null),
            'seen_before' => $this->validRecency(isset($raw['seen_before']) ? $raw['seen_before'] : null),
        );
    }

    /**
     * The distinct payload identifiers that have sessions, scoped for non-admins.
     */
    public function doPayloads()
    {
        $opts = array();
        if (!Auth::can('session.viewall')) {
            $opts['identifiers'] = Payload::accessibleIdentifiers(Auth::id());
        }
        return Session::distinctPayloads($opts);
    }

    /**
     * Resolve a group from a representative session row id. Returns.
     *
     * @param int $id
     * @throws HHttpException
     */
    public function resolveGroup($id)
    {
        $row = Session::find((int) $id);
        if (!$row) {
            throw new HttpException(404, 'Session not found');
        }
        return array(
            'client_id' => (string) $row['client_id'],
            'origin'    => (string) $row['origin'],
            'payload'   => (string) $row['payload'],
        );
    }

    /**
     * Enforce access to a session group via its payload identifier. session.viewall.
     *
     * @param string $payloadIdentifier
     * @param string $type
     * @throws HHttpException
     */
    public function accessForGroup($payloadIdentifier, $type)
    {
        if (Auth::can('session.viewall')) {
            return;
        }

        $payload = ($payloadIdentifier !== '') ? Payload::findBy('identifier', $payloadIdentifier) : null;
        if (!$payload) {

            throw new HttpException(403, 'Forbidden');
        }

        Payload::ensureAccess(Auth::id(), (int) $payload['id'], $type, 'payloads');
    }

    /**
     * Shape a raw light session row into a summary (never a blob column).
     *
     * @param array $row
     */
    private function summaryRow($row)
    {
        return array(
            'id'         => (int) $row['id'],
            'client_id'  => isset($row['client_id']) ? $row['client_id'] : '',
            'payload'    => isset($row['payload']) ? $row['payload'] : '',
            'uri'        => isset($row['uri']) ? $row['uri'] : '',
            'ip'         => isset($row['ip']) ? $row['ip'] : '',
            'origin'     => isset($row['origin']) ? $row['origin'] : '',
            'useragent'  => isset($row['useragent']) ? $row['useragent'] : '',
            'browser'    => $this->parseBrowser((string) (isset($row['useragent']) ? $row['useragent'] : '')),
            'archive'    => (int) (isset($row['archive']) ? $row['archive'] : 0),
            'created_at' => isset($row['created_at']) ? (int) $row['created_at'] : null,
            'last'       => isset($row['updated_at']) ? (int) $row['updated_at'] : null,
            'requests'   => isset($row['requests']) ? (int) $row['requests'] : null,
        );
    }

    /**
     * A conservative browser label from a user-agent string (display only).
     *
     * @param string $userAgent
     */
    private function parseBrowser($userAgent)
    {
        $parts = userAgentParts($userAgent);
        return $parts['browser'];
    }
}
