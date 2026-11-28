<?php
class PayloadsController extends Controller
{
    /**
     * Web-reachable actions.
     */
    public static $routable = array('index', 'get', 'update', 'delete', 'addpage', 'deletepage', 'addfilter', 'deletefilter', 'reassign', 'datacount');

        /**
         * GET lists, POST creates payloads.
         *
     * @return array
         */
    public function index()
    {
        $this->begin(array('GET', 'POST'));

        if (isPOST()) {
            $data = $this->doCreate($this->input());
            Log::event('payloads', Auth::id(), 'Created payload ' . (isset($data['identifier']) ? $data['identifier'] : '#' . (isset($data['id']) ? $data['id'] : '?')), USER_IP);
            return $this->respond($data, array(), 201);
        }

        $result = $this->doList();
        $this->respond($result['data'], array('pagination' => $result['pagination']));
    }

    /**
     * GET /api/payloads/get/{id}.
     *
     * @param int $id
     */
    public function get($id)
    {
        $this->begin('GET');
        $this->respond($this->doGet($id));
    }

    /**
     * POST /api/payloads/update/{id}.
     *
     * @param int $id
     */
    public function update($id)
    {
        $this->begin('POST');

        $this->respond($this->doUpdate($id, $this->input()));
    }

    /**
     * DELETE /api/payloads/delete/{id}; with ?data=1 its reports and sessions are deleted too.
     *
     * @param int $id
     */
    public function delete($id)
    {
        $this->begin('DELETE');

        $data = $this->doDelete($id, queryParam('data', '0') === '1');
        $what = 'Deleted payload #' . (isset($data['id']) ? $data['id'] : $id);
        if (!empty($data['reports']) || !empty($data['sessions'])) {
            $what .= ' with ' . (int) $data['reports'] . ' reports and ' . (int) $data['sessions'] . ' sessions';
        }
        Log::event('payloads', Auth::id(), $what, USER_IP);
        $this->respond($data);
    }

    /**
     * GET /api/payloads/datacount/{id} - reports and sessions still on a payload, and whether the actor may delete them.
     *
     * @param int $id
     */
    public function datacount($id)
    {
        $this->begin('GET');

        $this->respond($this->doDataCount($id));
    }

    /**
     * POST /api/payloads/addpage/{id}.
     *
     * @param int $id
     */
    public function addpage($id)
    {
        $this->begin('POST');

        $this->respond($this->doAddPage($id, $this->input()), array(), 201);
    }

    /**
     * DELETE /api/payloads/deletepage/{id}/{pageId}.
     *
     * @param int $id
     * @param int $pageId
     */
    public function deletepage($id, $pageId)
    {
        $this->begin('DELETE');

        $this->respond($this->doDeletePage($id, $pageId));
    }

    /**
     * POST /api/payloads/addfilter/{id}.
     *
     * @param int $id
     */
    public function addfilter($id)
    {
        $this->begin('POST');

        $this->respond($this->doAddFilter($id, $this->input()), array(), 201);
    }

    /**
     * DELETE /api/payloads/deletefilter/{id}/{filterId}.
     *
     * @param int $id
     * @param int $filterId
     */
    public function deletefilter($id, $filterId)
    {
        $this->begin('DELETE');

        $this->respond($this->doDeleteFilter($id, $filterId));
    }

    /**
     * POST /api/payloads/reassign/{id} - move a payload to another owner (payload.viewall).
     *
     * @param int $id
     */
    public function reassign($id)
    {
        $this->begin('POST');

        $data = $this->doReassign($id, $this->input());
        Log::event('payloads', Auth::id(), 'Reassigned payload #' . $id . ' to user #' . $data['user_id'], USER_IP);
        $this->respond($data);
    }

        /**
         * List payloads the actor owns or can access; payload.viewall lists all. Paginated.
         *
         * @return array
         */
    public function doList()
    {
        $all = queryParam('all', null) !== null;

        if (Auth::can('payload.viewall')) {

            $userId = null;
            $rawUser = queryParam('user', null);
            if ($rawUser !== null && ctype_digit((string) $rawUser)) {
                $userId = (int) $rawUser;
            }

            if ($all) {
                $q = Payload::query()->orderBy('id', 'ASC');
                if ($userId !== null) {
                    $q->where('user_id', $userId);
                }
                return array('data' => $this->summarize($q->get()), 'pagination' => null);
            }

            $perPage = $this->intParam('per_page', 25, 1, 200);
            $page = $this->intParam('page', 1, 1, PHP_INT_MAX);
            $offset = ($page - 1) * $perPage;

            $countQuery = Payload::query();
            $rowsQuery = Payload::query();
            if ($userId !== null) {
                $countQuery->where('user_id', $userId);
                $rowsQuery->where('user_id', $userId);
            }

            $total = (int) $countQuery->count();
            $rows = $rowsQuery->orderBy('id', 'ASC')->limit($perPage, $offset)->get();

            return array(
                'data'       => $this->summarize($rows),
                'pagination' => $this->pagination($total, $page, $perPage),
            );
        }

        $scoped = array_values(Payload::listWithAccess(Auth::id(), 'payloads'));

        usort($scoped, function ($a, $b) {
            return (int) $a['id'] - (int) $b['id'];
        });
        $total = count($scoped);

        if ($all) {
            return array('data' => $this->summarize($scoped), 'pagination' => null);
        }

        $perPage = $this->intParam('per_page', 25, 1, 200);
        $page = $this->intParam('page', 1, 1, PHP_INT_MAX);
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($scoped, $offset, $perPage);

        return array(
            'data'       => $this->summarize($slice),
            'pagination' => $this->pagination($total, $page, $perPage),
        );
    }

    /**
     * Map raw payload rows through Payload::summary().
     *
     * @param array $rows
     */
    private function summarize($rows)
    {
        $out = array();
        foreach ($rows as $row) {
            $out[] = Payload::summary($row);
        }
        return $out;
    }

    /**
     * Create a payload (payload.create); payload.viewall may set the owner and skips the panel-domain and domain-conflict checks.
     *
     * @param string $body
     * @throws HttpException
     */
    public function doCreate($body)
    {
        $this->cap('payload.create');

        Hook::fire('payload.before_create', Auth::user());

        $raw = '';
        if (isset($body['payload']) && is_string($body['payload'])) {
            $raw = $body['payload'];
        } elseif (isset($body['identifier']) && is_string($body['identifier'])) {
            $raw = $body['identifier'];
        }
        $identifier = strtolower(trim($raw));

        $this->validateIdentifier($identifier);

        $ownerId = $this->resolveOwner($body);

        if (!Payload::isAvailable($identifier)) {
            throw new HttpException(422, 'Payload domain is already in use');
        }

        if (!Permissions::can(Auth::user(), 'payload.viewall')) {
            $idHost = $identifier;
            $slash = strpos($idHost, '/');
            if ($slash !== false) {
                $idHost = substr($idHost, 0, $slash);
            }
            $blocked = Security::realPanelHosts();
            if (defined('HOST') && HOST !== '' && !in_array(HOST, $blocked, true)) {
                $blocked[] = HOST;
            }
            if (in_array($idHost, $blocked, true)) {
                throw new HttpException(403, 'Only an administrator can create a payload on the panel domain');
            }
        }

        if (!Permissions::can(Auth::user(), 'payload.viewall')) {
            if (!Payload::isDomainAvailable($identifier, $ownerId)) {
                throw new HttpException(422, 'Payload domain conflicts with an existing payload');
            }
        }

        $id = Payload::createOwned($ownerId, $identifier);

        return Payload::fullConfig($id);
    }

    /**
     * Resolve the owner id for a create: without payload.viewall it is always the actor.
     *
     * @param string $body
     * @throws HttpException
     * @return int
     */
    private function resolveOwner($body)
    {
        if (!Auth::can('payload.viewall')) {
            return Auth::id();
        }

        if (!isset($body['user']) || $body['user'] === '' || $body['user'] === null) {
            return Auth::id();
        }

        $rawUser = $body['user'];
        if (!ctype_digit((string) $rawUser) || (int) $rawUser <= 0) {
            throw new HttpException(422, 'Invalid user id');
        }

        $target = User::find((int) $rawUser);
        if (!$target) {
            throw new HttpException(404, 'User not found');
        }

        return (int) $target['id'];
    }

    /**
     * Reassign an existing payload to another owner (payload.viewall + payload.create), refusing a domain collision for the target user.
     *
     * @param int $id
     * @param string $body
     * @throws HttpException
     * @return array
     */
    public function doReassign($id, $body)
    {

        if (!Permissions::can(Auth::user(), 'payload.viewall')) {
            throw new HttpException(403, 'You do not have permission to do that');
        }
        $this->cap('payload.create');

        $id = $this->validId($id, 'payload');
        $payload = $this->ensurePayload($id);

        $rawUser = isset($body['user']) ? $body['user'] : null;
        if ($rawUser === null || $rawUser === '' || !ctype_digit((string) $rawUser) || (int) $rawUser <= 0) {
            throw new HttpException(422, 'Invalid user id');
        }
        $target = User::find((int) $rawUser);
        if (!$target) {
            throw new HttpException(404, 'User not found');
        }
        $targetUserId = (int) $target['id'];

        if (Payload::wouldCollideForOwner($payload['identifier'], $targetUserId, (int) $payload['id'])) {
            throw new HttpException(409, 'Target user already has a conflicting payload on this domain');
        }

        Payload::update($id, array(
            'user_id'    => $targetUserId,
        ));

        return array(
            'id'         => $id,
            'identifier' => $payload['identifier'],
            'user_id'    => $targetUserId,
            'owner'      => $targetUserId,
        );
    }

    /**
     * Full config for one payload (view access).
     *
     * @param int $id
     */
    public function doGet($id)
    {
        $id = $this->validId($id, 'payload');
        $this->ensurePayload($id);
        $this->access($id, 'view');

        return Payload::fullConfig($id);
    }

    /**
     * Update editable config (edit access). Enforces the global persistent/beacon/spider switches and the behaviour capability of each changed field.
     *
     * @param int $id
     * @param string $body
     * @throws HttpException
     */
    public function doUpdate($id, $body)
    {
        $id = $this->validId($id, 'payload');
        $payload = $this->ensurePayload($id);
        $this->access($id, 'edit');

        $changes = array();

        if (array_key_exists('customjs', $body)) {
            $changes['customjs'] = (string) $body['customjs'];
        }
        if (array_key_exists('customjs2', $body)) {
            $changes['customjs2'] = (string) $body['customjs2'];
        }

        if (array_key_exists('extensions', $body)) {
            $changes['extensions'] = $this->normalizeExtensions($body['extensions']);
        }

        if (array_key_exists('persistent', $body)) {
            $persistent = $this->asBool($body['persistent']);
            if ($persistent && !Setting::enabled('persistent')) {
                throw new HttpException(422, 'Persistent mode is globally disabled by the ezXSS admin');
            }
            $changes['persistent'] = $persistent ? 1 : 0;
        }

        if (array_key_exists('beacon', $body)) {
            $beacon = $this->asBool($body['beacon']);
            if ($beacon && !Setting::enabled('beacon_fallback')) {
                throw new HttpException(422, 'CSP-bypass beacons are globally disabled by the ezXSS admin');
            }
            $changes['beacon'] = $beacon ? 1 : 0;
        }

        if (array_key_exists('spider', $body)) {
            $spider = (string) $body['spider'];
            if (!in_array($spider, array('0', '1', '2'), true)) {
                throw new HttpException(422, 'Invalid spider mode. Use 0, 1 or 2');
            }
            if ($spider !== '0' && !Setting::enabled('spider')) {
                throw new HttpException(422, 'Spidering is globally disabled by the ezXSS admin');
            }
            $changes['spider'] = (int) $spider;
        }

        $behaviourOf = array('customjs' => 'customjs', 'customjs2' => 'customjs', 'extensions' => 'extensions',
            'persistent' => 'persistent', 'beacon' => 'beacon', 'spider' => 'spider');
        foreach ($changes as $field => $value) {
            $current = isset($payload[$field]) ? (string) $payload[$field] : '';
            if ((string) $value !== $current) {
                $this->behaviour($behaviourOf[$field]);
            }
        }

        if (!empty($changes)) {
            Payload::update($id, $changes);
        }

        if (array_key_exists('collect', $body) && is_array($body['collect'])) {
            $this->applyCollect($id, $body['collect']);
        }

        return Payload::fullConfig($id);
    }

    /**
     * Delete a payload (payload.delete + delete access); pages/filters/collect/sharing cascade, reports and sessions only with $withData.
     *
     * @param int $id
     * @param bool $withData
     * @return array
     */
    public function doDelete($id, $withData = false)
    {
        $this->cap('payload.delete');
        $id = $this->validId($id, 'payload');
        $payload = $this->ensurePayload($id);
        $this->access($id, 'delete');

        $reports = 0;
        $sessions = 0;
        if ($withData) {
            @set_time_limit(0);
            $may = $this->dataRights($payload);
            if (!$may['reports'] || !$may['sessions']) {
                throw new HttpException(403, 'You do not have permission to delete the reports and sessions of this payload');
            }
            foreach (Report::idsOnPayload($payload['identifier']) as $reportId) {
                Report::deleteFull($reportId);
                $reports++;
            }
            foreach (Session::groupsOnPayload($payload['identifier']) as $group) {
                Session::deleteGroup($group['client_id'], $group['origin'], Session::normalizePayloadIdentifier($payload['identifier']));
                $sessions++;
            }
        }

        Payload::deleteCascade($id);

        return array('deleted' => true, 'id' => $id, 'reports' => $reports, 'sessions' => $sessions);
    }

    /**
     * Count the reports and sessions still on a payload before it is deleted.
     *
     * @param int $id
     * @return array
     */
    public function doDataCount($id)
    {
        $this->cap('payload.delete');
        $id = $this->validId($id, 'payload');
        $payload = $this->ensurePayload($id);
        $this->access($id, 'delete');

        $may = $this->dataRights($payload);
        return array(
            'reports'    => count(Report::idsOnPayload($payload['identifier'])),
            'sessions'   => count(Session::groupsOnPayload($payload['identifier'])),
            'can_delete' => $may['reports'] && $may['sessions'],
        );
    }

    /**
     * Whether the actor may delete the reports and the sessions of a payload: owner, or the matching viewall cap.
     *
     * @param array $payload
     * @return array
     */
    private function dataRights(array $payload)
    {
        $owner = (int) $payload['user_id'] === (int) Auth::id();
        return array(
            'reports'  => Auth::can('report.view') && ($owner || Auth::can('report.viewall')),
            'sessions' => Auth::can('session.view') && ($owner || Auth::can('session.viewall')),
        );
    }

    /**
     * Add a page to a payload. Path must start with "/" and not contain "~".
     *
     * @param int $id
     * @param string $body
     * @throws HttpException
     * @return array
     */
    public function doAddPage($id, $body)
    {
        $id = $this->validId($id, 'payload');
        $this->ensurePayload($id);
        $this->access($id, 'edit');
        $this->behaviour('pages');

        $path = isset($body['path']) && is_string($body['path']) ? trim($body['path']) : '';

        if ($path === '' || substr($path, 0, 1) !== '/') {
            throw new HttpException(422, 'Path needs to start with a "/"');
        }
        if (strpos($path, '~') !== false || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            throw new HttpException(422, 'This does not look like a valid path');
        }
        if (strlen($path) > 1000) {
            throw new HttpException(422, 'Path is too long');
        }

        $pageId = (int) Payload::query()->from('payload_pages')->insert(array(
            'payload_id' => $id,
            'path'       => $path,
        ));

        return array('id' => $pageId, 'payload_id' => $id, 'path' => $path);
    }

    /**
     * Remove a page from a payload.
     *
     * @param int $id
     * @param int $pageId
     * @throws HttpException
     * @return array
     */
    public function doDeletePage($id, $pageId)
    {
        $id = $this->validId($id, 'payload');
        $pageId = $this->validId($pageId, 'page');
        $this->ensurePayload($id);
        $this->access($id, 'edit');
        $this->behaviour('pages');

        $page = Payload::query()->from('payload_pages')
            ->where('id', $pageId)->where('payload_id', $id)->first();
        if (!$page) {
            throw new HttpException(404, 'Page not found');
        }

        Payload::query()->from('payload_pages')->where('id', $pageId)->where('payload_id', $id)->delete();

        return array('deleted' => true, 'id' => $pageId);
    }

    /**
     * Add an allow/deny domain filter. Domain supports wildcards.
     *
     * @param int $id
     * @param string $body
     * @throws HttpException
     * @return array
     */
    public function doAddFilter($id, $body)
    {
        $id = $this->validId($id, 'payload');
        $this->ensurePayload($id);
        $this->access($id, 'edit');
        $this->behaviour('filters');

        $domain = isset($body['domain']) && is_string($body['domain']) ? trim($body['domain']) : '';
        $type = isset($body['type']) ? (string) $body['type'] : '';

        if (!in_array($type, array('allow', 'deny'), true)) {
            throw new HttpException(422, 'Filter type must be allow or deny');
        }

        if (!preg_match('/^(?!.*\*\*)(?:[a-zA-Z\d*](?:[a-zA-Z\d\-*]{0,61}[a-zA-Z\d*])?\.)*[a-zA-Z\d*](?:[a-zA-Z\d\-*]{0,61}[a-zA-Z\d*])?$/', $domain)) {
            throw new HttpException(422, 'This does not look like a valid domain');
        }

        $filterId = (int) Payload::query()->from('payload_filters')->insert(array(
            'payload_id' => $id,
            'domain'     => $domain,
            'type'       => $type,
        ));

        return array('id' => $filterId, 'payload_id' => $id, 'domain' => $domain, 'type' => $type);
    }

    /**
     * Remove a filter from a payload.
     *
     * @param int $id
     * @param int $filterId
     * @throws HttpException
     * @return array
     */
    public function doDeleteFilter($id, $filterId)
    {
        $id = $this->validId($id, 'payload');
        $filterId = $this->validId($filterId, 'filter');
        $this->ensurePayload($id);
        $this->access($id, 'edit');
        $this->behaviour('filters');

        $filter = Payload::query()->from('payload_filters')
            ->where('id', $filterId)->where('payload_id', $id)->first();
        if (!$filter) {
            throw new HttpException(404, 'Filter not found');
        }

        Payload::query()->from('payload_filters')->where('id', $filterId)->where('payload_id', $id)->delete();

        return array('deleted' => true, 'id' => $filterId);
    }

        /**
         * Apply collect toggles: an option is enabled only when its global setting is enabled too.
         *
         * @param int $payloadId
         * @param array $requested
         */
    private function applyCollect($payloadId, $requested)
    {
        $changes = array();
        foreach (Payload::COLLECT_OPTIONS as $opt) {
            $wants = !empty($requested[$opt]);
            $globallyOn = Setting::enabled('collect_' . $opt);
            $changes['collect_' . $opt] = ($wants && $globallyOn) ? 1 : 0;
        }

        $exists = Payload::query()->from('payload_collect')->where('payload_id', (int) $payloadId)->first();
        if ($exists) {
            Payload::query()->from('payload_collect')->where('payload_id', (int) $payloadId)->update($changes);
        } else {
            $changes['payload_id'] = (int) $payloadId;
            Payload::query()->from('payload_collect')->insert($changes);
        }
    }

    /**
     * Normalize and validate an extensions selection (array or CSV of ids).
     *
     * @param array $input
     * @throws HttpException
     * @return string
     */
    private function normalizeExtensions($input)
    {
        $ids = array();
        if (is_array($input)) {
            $ids = $input;
        } elseif (is_string($input) && $input !== '') {
            $ids = explode(',', $input);
        }

        $clean = array();
        foreach ($ids as $val) {
            $val = trim((string) $val);
            if ($val !== '' && ctype_digit($val)) {
                $clean[(int) $val] = (int) $val;
            }
        }

        if (empty($clean)) {
            return '';
        }

        $enabled = Extension::allEnabled();
        $validIds = array();
        foreach ($enabled as $ext) {
            $validIds[(int) $ext['id']] = true;
        }

        foreach ($clean as $extId) {
            if (!isset($validIds[$extId])) {
                throw new HttpException(422, 'Invalid extension ID');
            }
        }

        return implode(',', array_values($clean));
    }

    /**
     * Validate a payload domain identifier (lowercased, trimmed).
     *
     * @param string $identifier
     * @throws HHttpException
     */
    private function validateIdentifier($identifier)
    {
        if ($identifier === '') {
            throw new HttpException(422, 'Payload domain cannot be empty');
        }
        if (preg_match('/\s/', $identifier) ||
            preg_match('/[<>"\'\\\\\|\*\?#%&=]/', $identifier) ||
            preg_match('/[\(\)\[\]\{\}@!]/', $identifier) ||
            preg_match('/[\x00-\x1F\x7F]/', $identifier)) {
            throw new HttpException(422, 'Payload domain contains invalid characters');
        }
        if (strpos($identifier, 'http://') === 0 || strpos($identifier, 'https://') === 0) {
            throw new HttpException(422, 'Payload must not include a scheme (http:// or https://)');
        }
        if (substr($identifier, -1) === '/') {
            throw new HttpException(422, 'Payload must not end with a trailing slash');
        }
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9]?(\.[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9]?)*(\.[a-zA-Z]{2,})(\/.*)?$/', $identifier)) {
            throw new HttpException(422, 'Payload domain is in invalid format');
        }
        if (strlen($identifier) > 500) {
            throw new HttpException(422, 'Payload domain is too long');
        }
        $parts = explode('/', $identifier);
        if (isset($parts[1]) && strtolower($parts[1]) === Config::panelPath()) {
            throw new HttpException(422, 'Payload path /' . Config::panelPath() . ' is the panel URL');
        }
    }

    /**
     * Throw 403 unless the actor holds the payload behaviour capability.
     *
     * @param string $key
     * @throws HttpException
     */
    private function behaviour($key)
    {
        if (!Permissions::canBehaviour(Auth::user(), $key)) {
            throw new HttpException(403, 'You do not have permission to change that');
        }
    }

    /**
     * Load a payload or throw 404.
     *
     * @param int $id
     * @throws HttpException
     */
    private function ensurePayload($id)
    {
        $payload = Payload::find($id);
        if (!$payload) {
            throw new HttpException(404, 'Payload not found');
        }
        return $payload;
    }

    /**
     * Ensure the actor may access a payload. payload.viewall bypasses; others go through Payload::ensureAccess().
     *
     * @param int $id
     * @param string $type
     */
    private function access($id, $type)
    {
        if (Auth::can('payload.viewall')) {
            return;
        }
        Payload::ensureAccess(Auth::id(), $id, $type, 'payloads');
    }
}
