<?php
class UsersController extends Controller
{
    /**
     * Web-reachable actions.
     */
    public static $routable = array('index', 'get', 'update', 'roles', 'delete');

    private static $builtInRoles = array('admin', 'user');

    /**
     * GET lists, POST creates users.
     *
     * @return array
     */
    public function index()
    {
        $this->begin(array('GET', 'POST'));

        if (isPOST()) {
            $data = $this->doCreate($this->input());
            Log::event('users', Auth::id(), 'Created user ' . (isset($data['username']) ? $data['username'] : '#' . (isset($data['id']) ? $data['id'] : '?')), USER_IP);
            return $this->respond($data, array(), 201);
        }

        $result = $this->doList();
        $this->respond($result['data'], array('pagination' => $result['pagination']));
    }

    /**
     * GET /api/users/get/{id}.
     *
     * @param int $id
     */
    public function get($id)
    {
        $this->begin('GET');
        $this->respond($this->doGet($id));
    }

    /**
     * POST /api/users/update/{id}.
     *
     * @param int $id
     */
    public function update($id)
    {
        $this->begin('POST');

        $body = $this->input();
        $data = $this->doUpdate($id, $body);

        if (array_key_exists('banned', $body)) {
            $banned = isset($data['banned']) ? ((int) $data['banned'] === 1) : $this->asBool($body['banned']);
            Log::event('users', Auth::id(), ($banned ? 'Banned' : 'Unbanned') . ' user #' . (int) $id, USER_IP);
        }
        $this->respond($data);
    }

    /**
     * GET/POST/DELETE /api/users/roles[/{name}] - manage editable roles. Needs.
     *
     * @param string $name
     * @throws HttpException
     */
    public function roles($name = null)
    {
        $this->begin();
        $this->cap('user.roles');

        $method = $this->method();
        $name = ($name !== null && $name !== '') ? $this->normalizeRoleName((string) $name) : null;

        if ($method === 'DELETE') {
            if ($name === null) {
                throw new HttpException(400, 'Role name required.');
            }
            $this->deleteRole($name);
            return $this->respond($this->roleList());
        }

        if (isPOST()) {
            $this->doUpsertRole($name, $this->input());
            return $this->respond($this->roleList());
        }

        if ($method !== 'GET') {
            return $this->error('Method not allowed.', 405);
        }
        return $this->respond($this->roleList());
    }

    /**
     * Create ($name null) or edit an editable role. A non-admin may only grant.
     *
     * @param string $name
     * @param array $body
     * @throws HttpException
     */
    public function doUpsertRole($name, array $body)
    {
        $granted = $this->grantedFromBody($body);

        if ($name === null) {
            $newName = $this->normalizeRoleName(isset($body['name']) ? (string) $body['name'] : '');
            if ($newName === '') {
                throw new HttpException(422, 'A role name is required');
            }
            if ($newName === 'admin' || Role::exists($newName)) {
                throw new HttpException(409, 'A role with that name already exists');
            }
            Role::upsert($newName, $granted);
            Log::event('users', Auth::id(), 'Created role ' . $newName, USER_IP);
            return $newName;
        }

        if ($name === 'admin') {
            throw new HttpException(422, 'The admin role cannot be edited');
        }
        if (!Role::exists($name)) {
            throw new HttpException(404, 'Role not found');
        }
        Role::upsert($name, $granted);
        Log::event('users', Auth::id(), 'Edited role ' . $name, USER_IP);
        return $name;
    }

    /**
     * Shape every known role with its resolved capability map.
     *
     * @return array
     */
    private function roleList()
    {
        $rows = array(
            array('name' => 'admin', 'capabilities' => Permissions::roleCaps('admin'), 'builtin' => true, 'locked' => true, 'deletable' => false),
            array('name' => Role::BASE, 'capabilities' => Permissions::roleCaps(Role::BASE), 'builtin' => true, 'locked' => false, 'deletable' => false),
        );
        foreach (Role::allRoles() as $rname => $granted) {
            if ($rname === 'admin' || $rname === Role::BASE) {
                continue;
            }
            $rows[] = array('name' => $rname, 'capabilities' => Permissions::roleCaps($rname), 'builtin' => false, 'locked' => false, 'deletable' => true);
        }
        return array('roles' => $rows);
    }

    private function deleteRole($name)
    {
        if ($name === 'admin' || $name === Role::BASE) {
            throw new HttpException(422, 'This role cannot be deleted');
        }
        if (!Role::exists($name)) {
            throw new HttpException(404, 'Role not found');
        }

        $this->assertCanAssignRole(Role::BASE);

        User::query()->where('role', $name)->update(array('role' => Role::BASE));
        Role::remove($name);
        Log::event('users', Auth::id(), 'Deleted role ' . $name . ' (users reassigned to ' . Role::BASE . ')', USER_IP);
    }

    /**
     * Slugify + validate a role name (2-30 chars of a-z 0-9 - _).
     *
     * @param string $raw
     * @throws HttpException
     */
    private function normalizeRoleName($raw)
    {
        $slug = strtolower(trim((string) $raw));
        $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug);
        $slug = trim($slug, '-');
        if ($slug !== '' && (strlen($slug) < 2 || strlen($slug) > 30)) {
            throw new HttpException(422, 'Role name must be 2-30 characters (letters, numbers, - or _)');
        }
        return $slug;
    }

    /**
     * Granted-cap list from a { permissions: {cap: bool} } body, capped.
     *
     * @param array $body
     */
    private function grantedFromBody(array $body)
    {
        $caps = isset($body['permissions']) && is_array($body['permissions']) ? $body['permissions'] : array();
        if (!empty($caps) && array_keys($caps) === range(0, count($caps) - 1)) {
            throw new HttpException(422, 'permissions must be an object of capability => true/false');
        }
        $granted = array();
        foreach ($caps as $cap => $on) {
            if ($on) {
                $granted[] = $cap;
            }
        }
        $granted = Permissions::sanitizeGranted($granted);

        $own = Permissions::effective(Auth::user());
        $allowed = array();
        foreach ($granted as $cap) {
            if (!empty($own[$cap])) {
                $allowed[] = $cap;
            }
        }
        $granted = $allowed;

        return $granted;
    }

    /**
     * DELETE /api/users/delete/{id}.
     *
     * @param int $id
     */
    public function delete($id)
    {
        $this->begin('DELETE');

        $data = $this->doDelete($id);
        Log::event('users', Auth::id(), 'Deleted user #' . (isset($data['id']) ? $data['id'] : $id), USER_IP);
        $this->respond($data);
    }

    /**
     * List users (safe fields). Admin only. Paginated (id ASC) by default.
     *
     * @return array
     */
    public function doList()
    {
        $this->cap('user.manage');

        $search = queryParam('search', '');
        $search = is_string($search) ? trim($search) : '';

        if (queryParam('all', '0') === '1' || queryParam('all', '0') === 1) {
            $rows = User::query()->orderBy('id', 'ASC')->get();
            return array(
                'data'       => $this->safeRows($rows),
                'pagination' => null,
            );
        }

        $perPage = $this->intParam('per_page', 25, 1, 200);
        $page = $this->intParam('page', 1, 1, PHP_INT_MAX);
        $offset = ($page - 1) * $perPage;

        $countQuery = User::query();
        $rowsQuery = User::query();
        if ($search !== '') {

            $like = '%' . addcslashes($search, '\\%_') . '%';
            $countQuery->where('username', 'LIKE', $like);
            $rowsQuery->where('username', 'LIKE', $like);
        }

        $total = (int) $countQuery->count();
        $rows = $rowsQuery->orderBy('id', 'ASC')->limit($perPage, $offset)->get();

        return array(
            'data'       => $this->safeRows($rows),
            'pagination' => $this->pagination($total, $page, $perPage),
        );
    }

    /**
     * Map raw user rows through User::safe() (strips the password hash + 2FA secret).
     *
     * @param array $rows
     */
    private function safeRows($rows)
    {
        $out = array();
        foreach ($rows as $row) {
            $out[] = User::safe($row);
        }
        return $out;
    }

    /**
     * Create a user. Admin only.
     *
     * @param string $body
     * @throws HttpException
     */
    public function doCreate($body)
    {
        $this->cap('user.manage');

        $username = isset($body['username']) && is_string($body['username']) ? trim($body['username']) : '';
        $password = isset($body['password']) && is_string($body['password']) ? $body['password'] : '';
        $role = isset($body['role']) ? (string) $body['role'] : 'user';

        $this->validateCredentials($username, $password);
        $this->validateRole($role);
        $this->assertCanAssignRole($role);

        $permissions = null;
        if (array_key_exists('permissions', $body)) {
            $this->cap('user.roles');
            $permissions = $this->grantableOverrides($body['permissions']);
        }

        if (User::findByUsername($username)) {
            throw new HttpException(409, 'A user with that username already exists');
        }
        try {
            $id = User::createUser($username, $password, $role, $permissions);
        } catch (Exception $e) {

            if (User::findByUsername($username)) {
                throw new HttpException(409, 'A user with that username already exists');
            }
            throw $e;
        }

        return User::safe(User::find($id));
    }

    /**
     * Read one user (safe fields). Admin, or the user themselves.
     *
     * @param int $id
     * @throws HttpException
     */
    public function doGet($id)
    {
        $id = (int) $id;
        $isSelf = Auth::id() === $id;

        if (!$isSelf && !Auth::can('user.manage')) {
            throw new HttpException(403, 'You do not have permission to do that');
        }

        $user = User::find($id);
        if (!$user) {
            throw new HttpException(404, 'User not found');
        }

        $safe = User::safe($user);
        $safe['capabilities'] = Permissions::effective($user);
        return $safe;
    }

    /**
     * Update a user. Admin may change role/permissions and reset the password.
     *
     * @param int $id
     * @param string $body
     * @throws HttpException
     */
    public function doUpdate($id, $body)
    {
        $actor = Auth::user();
        $id = (int) $id;

        $isSelf = Auth::id() === $id;
        $canManage = Auth::can('user.manage');

        if (!$canManage && !$isSelf) {
            throw new HttpException(403, 'You do not have permission to do that');
        }

        $target = User::find($id);
        if (!$target) {
            throw new HttpException(404, 'User not found');
        }

        if (!$isSelf && !Permissions::capsSubset($target, $actor)) {
            throw new HttpException(403, 'You cannot manage a user with capabilities beyond your own');
        }

        $changes = array();

        if (array_key_exists('username', $body)) {
            if (!$canManage) {
                throw new HttpException(403, 'You do not have permission to change usernames');
            }
            $username = is_string($body['username']) ? trim($body['username']) : '';
            if ($username !== (string) $target['username']) {
                $this->validateUsernameRule($username);
                $existing = User::findByUsername($username);
                if ($existing && (int) $existing['id'] !== $id) {
                    throw new HttpException(409, 'A user with that username already exists');
                }
                $changes['username'] = $username;
            }
        }

        if (array_key_exists('role', $body)) {
            if (!$canManage) {
                throw new HttpException(403, 'You do not have permission to change roles');
            }
            if ($isSelf) {
                throw new HttpException(403, 'You cannot change your own role');
            }
            $role = (string) $body['role'];
            $this->validateRole($role);
            $this->assertCanAssignRole($role);
            if ($target['role'] === 'admin' && $role !== 'admin' && User::countAdmins() <= 1) {
                throw new HttpException(409, 'You cannot demote the last admin');
            }
            $changes['role'] = $role;
        }

        if (array_key_exists('permissions', $body)) {
            if (!$canManage) {
                throw new HttpException(403, 'You do not have permission to change capabilities');
            }
            if ($isSelf) {
                throw new HttpException(403, 'You cannot change your own capabilities');
            }
            $this->cap('user.roles');
            $overrides = $this->grantableOverrides($body['permissions']);
            $changes['permissions'] = ($overrides !== array()) ? json_encode($overrides) : null;
        }

        $banChange = null;
        if (array_key_exists('banned', $body)) {
            if (!$canManage) {
                throw new HttpException(403, 'You do not have permission to ban users');
            }
            $banned = $this->asBool($body['banned']) ? 1 : 0;
            if ($banned === 1) {
                if ($isSelf) {
                    throw new HttpException(409, 'You cannot ban your own account');
                }
                if ($target['role'] === 'admin' && User::countAdmins() <= 1) {
                    throw new HttpException(409, 'You cannot ban the last admin');
                }
            }
            if ((int) (isset($target['banned']) ? $target['banned'] : 0) !== $banned) {
                $changes['banned'] = $banned;
                $banChange = $banned;
            }
        }

        if (isset($body['password']) && $body['password'] !== '') {
            $password = (string) $body['password'];
            $this->validatePasswordRule($password);

            if ($isSelf) {

                $current = isset($body['current_password']) ? (string) $body['current_password'] : '';
                if ($current === '' || !password_verify($current, $actor['password'])) {
                    throw new HttpException(422, 'Your current password is incorrect');
                }
            }

            $changes['password'] = password_hash($password, PASSWORD_BCRYPT, array('cost' => 12));
        }

        if (array_key_exists('github', $body)) {
            $github = is_string($body['github']) ? trim($body['github']) : '';
            if ($github !== '' && !preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9]|-(?=[A-Za-z0-9])){0,38}$/', $github)) {
                throw new HttpException(422, 'Enter a valid GitHub username');
            }
            $newGithub = $github === '' ? null : $github;
            $curGithub = isset($target['github']) ? $target['github'] : null;
            if ($newGithub !== $curGithub) {
                $changes['github'] = $newGithub;
            }
        }

        if ($changes === array()) {
            throw new HttpException(422, 'Nothing to update');
        }

        User::update($id, $changes);

        if ($banChange === 1) {
            Token::revokeAllForUser($id);
        } elseif (array_key_exists('password', $changes)) {
            $exceptId = ($isSelf && Auth::token()) ? (int) Auth::token()['id'] : 0;
            Token::revokeSessionsExcept($id, $exceptId);
        }

        return User::safe(User::find($id));
    }

    /**
     * Delete a user. Admin only. Cannot delete yourself or the last admin.
     *
     * @param int $id
     * @throws HttpException
     * @return array
     */
    public function doDelete($id)
    {
        $this->cap('user.manage');
        $actor = Auth::user();

        $id = $this->validId($id, 'user');

        if (Auth::id() === $id) {
            throw new HttpException(409, 'You cannot delete your own account');
        }

        $target = User::find($id);
        if (!$target) {
            throw new HttpException(404, 'User not found');
        }

        if (!Permissions::capsSubset($target, $actor)) {
            throw new HttpException(403, 'You cannot delete a user with capabilities beyond your own');
        }
        if ($target['role'] === 'admin' && User::countAdmins() <= 1) {
            throw new HttpException(409, 'You cannot delete the last admin');
        }

        Payload::query()->from('payloads')->where('user_id', $id)->update(array('user_id' => Auth::id()));

        Token::revokeAllForUser($id);
        User::purgeAccessFor($id);
        User::delete($id);

        return array('deleted' => true, 'id' => $id);
    }

    /**
     * Throw 403 unless the actor holds every capability the given role grants.
     *
     * @param string $role
     * @throws HttpException
     */
    private function assertCanAssignRole($role)
    {
        foreach (Permissions::roleCaps($role) as $cap => $on) {
            if ($on && !Auth::can($cap)) {
                throw new HttpException(403, 'You can only assign a role whose capabilities you hold yourself');
            }
        }
    }

    /**
     * Sanitize a per-user permissions override, keeping only grantable capabilities.
     *
     * @param string $raw
     * @throws HttpException
     */
    private function grantableOverrides($raw)
    {
        $clean = Permissions::sanitizeOverrides($raw);
        foreach ($clean as $cap => $val) {
            if ($val && !Auth::can($cap)) {
                throw new HttpException(403, 'You can only grant capabilities you hold yourself');
            }
        }
        return $clean;
    }

    /**
     * Validate a role against the preset set.
     *
     * @param string $role
     * @throws HttpException
     */
    private function validateRole($role)
    {

        if (in_array($role, self::$builtInRoles, true) || Role::exists($role)) {
            return;
        }
        throw new HttpException(422, 'Unknown role');
    }

    private function validateCredentials($username, $password)
    {
        try {
            InstallController::validateUsername($username);
            InstallController::validatePassword($password);
        } catch (Exception $e) {
            throw new HttpException(422, $e->getMessage());
        }
    }

    private function validateUsernameRule($username)
    {
        try {
            InstallController::validateUsername($username);
        } catch (Exception $e) {
            throw new HttpException(422, $e->getMessage());
        }
    }

    private function validatePasswordRule($password)
    {
        try {
            InstallController::validatePassword($password);
        } catch (Exception $e) {
            throw new HttpException(422, $e->getMessage());
        }
    }
}
