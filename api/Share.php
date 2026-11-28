<?php
class ShareController extends Controller
{
    /**
     * Web-reachable actions.
     */
    public static $routable = array('get', 'set');

    /**
     * Tables that may be shared.
     */
    private static $shareable = array('payloads', 'reports');

    /**
     * GET /api/share/get/{table}/{id}.
     *
     * @param string $table
     * @param int $id
     */
    public function get($table, $id)
    {
        $this->begin('GET');

        $this->ensureOwnerOrAdmin($table, $id);
        $this->respond($this->state($table, (int) $id));
    }

    /**
     * POST /api/share/set/{table}/{id}.
     *
     * @param string $table
     * @param int $id
     * @throws HttpException
     */
    public function set($table, $id)
    {
        $this->begin('POST');

        $row = $this->ensureOwnerOrAdmin($table, $id);
        $id = (int) $id;
        $ownerUid = isset($row['user_id']) ? (int) $row['user_id'] : 0;
        if ($table === 'reports') {
            $payload = Report::payloadOf($id);
            $ownerUid = $payload ? (int) $payload['user_id'] : 0;
        }

        $body = $this->input();
        $action = isset($body['action']) ? (string) $body['action'] : '';

        if ($table === 'payloads' && ($action === 'make_global' || $action === 'remove_global')) {
            throw new HttpException(422, 'Global sharing is not available for payloads');
        }

        switch ($action) {
            case 'add_user':
                $user = $this->findTargetUser($body);
                if ($table === 'payloads' && (int) $user['id'] === $ownerUid) {
                    throw new HttpException(422, 'Cannot share a payload with its owner');
                }
                User::giveAccess($user['id'], $table, $id, 1, 0);
                break;

            case 'remove_user':
                $user = $this->findTargetUser($body);
                User::revokeAccess($user['id'], $table, $id);
                break;

            case 'change_access':
                $user = $this->findTargetUser($body);
                if ($table === 'payloads' && (int) $user['id'] === $ownerUid) {
                    throw new HttpException(422, 'Cannot share a payload with its owner');
                }
                $accessType = isset($body['access_type']) ? (string) $body['access_type'] : '';
                if (!in_array($accessType, array('view', 'edit'), true)) {
                    throw new HttpException(422, 'access_type must be view or edit');
                }
                if ($accessType === 'edit') {
                    User::giveAccess($user['id'], $table, $id, 1, 1);
                } else {
                    User::giveAccess($user['id'], $table, $id, 1, 0);
                }
                break;

            case 'make_global':
                User::giveAccess(0, $table, $id, 1, 0);
                break;

            case 'remove_global':
                User::revokeAccess(0, $table, $id);
                break;

            default:
                throw new HttpException(422, 'Unknown action');
        }

        $this->respond($this->state($table, $id));
    }

    /**
     * The current sharing state for a resource.
     *
     * @param string $table
     * @param int $id
     * @return array
     */
    private function state($table, $id)
    {
        return array(
            'table'        => $table,
            'id'           => (int) $id,
            'shared_users' => User::allSharedAccess($table, $id),
            'global'       => User::isGlobalAccess($table, $id),
        );
    }

    /**
     * Resolve the target user from an add/remove/change body (by username).
     *
     * @param string $body
     * @throws HttpException
     */
    private function findTargetUser($body)
    {
        $username = isset($body['username']) && is_string($body['username']) ? trim($body['username']) : '';
        if ($username === '') {
            throw new HttpException(422, 'A username is required');
        }
        $user = User::findByUsername($username);
        if (!$user) {
            throw new HttpException(404, 'User not found');
        }
        return $user;
    }

    /**
     * Load the resource row (validating the table allow-list and id) and ensure.
     *
     * @param string $table
     * @param int $id
     * @throws HttpException
     */
    private function ensureOwnerOrAdmin($table, $id)
    {
        if (!in_array($table, self::$shareable, true)) {
            throw new HttpException(404, 'Unknown or non-shareable resource');
        }
        Model::isSafe($table);

        if (!ctype_digit((string) $id) || (int) $id <= 0) {
            throw new HttpException(422, 'Invalid resource id');
        }
        $id = (int) $id;

        $row = User::query()->from($table)->where('id', $id)->first();
        if (!$row) {
            throw new HttpException(404, 'Resource not found');
        }

        $actorId = Auth::id();
        $ownerUid = isset($row['user_id']) ? (int) $row['user_id'] : 0;
        if ($table === 'reports') {
            $payload = Report::payloadOf($id);
            $ownerUid = $payload ? (int) $payload['user_id'] : 0;
        }

        if ($table === 'payloads') {
            if (Auth::can('payload.viewall')) {
                return $row;
            }
            if (Auth::can('payload.share') && $actorId > 0 && $actorId === $ownerUid) {
                return $row;
            }
            throw new HttpException(403, 'You do not have permission to manage this resource');
        }

        if (Auth::can('report.viewall')) {
            return $row;
        }
        if (Auth::can('report.share') && $actorId > 0 && $actorId === $ownerUid) {
            return $row;
        }

        throw new HttpException(403, 'You do not have permission to manage this resource');
    }
}
