<?php
class User extends Model
{
    protected static $table = 'users';
    protected static $primaryKey = 'id';

    /**
     * Fields that must never leave the API.
     */
    private static $hidden = array('password', 'twofa_secret');

    /**
     * Find a user by username (exact match).
     *
     * @param string $username
     * @return array|null
     */
    public static function findByUsername($username)
    {
        if (!is_string($username) || $username === '') {
            return null;
        }
        $row = self::query()->where('username', $username)->first();
        return $row ?: null;
    }

    /**
     * Return a user row with all sensitive fields removed.
     *
     * @param array $user
     * @return array
     */
    public static function safe($user)
    {
        if (!is_array($user)) {
            return array();
        }

        $enabled = isset($user['twofa_secret']) && $user['twofa_secret'] !== null && $user['twofa_secret'] !== '';
        foreach (self::$hidden as $field) {
            unset($user[$field]);
        }
        $user['twofa_enabled'] = $enabled;

        if (array_key_exists('banned', $user)) {
            $user['banned'] = (int) $user['banned'];
        }

        return $user;
    }

    /**
     * Whether a user has 2FA enabled.
     *
     * @param array $user
     * @return bool
     */
    public static function has2fa($user)
    {
        return is_array($user)
            && isset($user['twofa_secret'])
            && $user['twofa_secret'] !== null
            && $user['twofa_secret'] !== '';
    }

    /**
     * Whether a user account is banned (disabled).
     *
     * @param array $user
     * @return bool
     */
    public static function isBanned($user)
    {
        return is_array($user) && !empty($user['banned']);
    }

    /**
     * Store (enable) a TOTP secret for a user.
     *
     * @param int $id
     * @param string $secret
     */
    public static function setTwofaSecret($id, $secret)
    {
        return self::update($id, array('twofa_secret' => $secret));
    }

    /**
     * Clear (disable) a user's TOTP secret.
     *
     * @param int $id
     */
    public static function clearTwofaSecret($id)
    {
        return self::update($id, array('twofa_secret' => null));
    }

    /**
     * Count users with the admin role.
     *
     * @return int
     */
    public static function countAdmins()
    {
        return (int) self::query()->where('role', 'admin')->count();
    }

    /**
     * Create a user with a hashed password and an optional permissions override.
     *
     * @param string $username
     * @param string $password
     * @param string $role
     * @param array $permissions
     * @return int
     */
    public static function createUser($username, $password, $role = 'user', $permissions = null)
    {
        $data = array(
            'username'    => $username,
            'password'    => password_hash($password, PASSWORD_BCRYPT, array('cost' => 12)),
            'role'        => $role,
            'permissions' => ($permissions !== null && $permissions !== array()) ? json_encode($permissions) : null,

            'notepad'     => 'Welcome to ezXSS 5',
        );
        return (int) self::query()->insert($data);
    }

    /**
     * Grant (or update) a user's access to a resource. user_id 0 = global.
     *
     * @param int $id
     * @param string $forTable
     * @param int $forId
     * @param string $view
     * @param bool $edit
     * @throws HttpException
     */
    public static function giveAccess($id, $forTable, $forId, $view = 1, $edit = 0)
    {

        if ($forTable === 'payloads' && (int) $id !== 0) {
            $payload = self::query()->from('payloads')->where('id', $forId)->first();
            if ($payload && isset($payload['user_id']) && (int) $payload['user_id'] === (int) $id) {
                throw new HttpException(422, 'Cannot grant payload access to its owner');
            }
        }

        $existing = self::query()
            ->from('user_access')
            ->where('user_id', $id)
            ->where('for_table', $forTable)
            ->where('for_id', $forId)
            ->first();

        if ($existing) {
            return self::query()
                ->from('user_access')
                ->where('user_id', $id)
                ->where('for_table', $forTable)
                ->where('for_id', $forId)
                ->update(array('view' => $view, 'edit' => $edit));
        }

        return self::query()
            ->from('user_access')
            ->insert(array(
                'user_id'   => $id,
                'for_table' => $forTable,
                'for_id'    => $forId,
                'view'      => $view,
                'edit'      => $edit,
            ));
    }

    /**
     * Revoke a user's access to a resource. user_id 0 = remove global access.
     *
     * @param int $id
     * @param string $forTable
     * @param int $forId
     */
    public static function revokeAccess($id, $forTable, $forId)
    {
        return self::query()
            ->from('user_access')
            ->where('user_id', $id)
            ->where('for_table', $forTable)
            ->where('for_id', $forId)
            ->delete();
    }

    /**
     * List the non-global users who have access to a resource (safe fields).
     *
     * @param string $forTable
     * @param int $forId
     * @return bool
     */
    public static function allSharedAccess($forTable, $forId)
    {
        $rows = self::query()
            ->select('u.id, u.username, ua.view, ua.edit')
            ->from('user_access ua')
            ->join('users u', 'ua.user_id', '=', 'u.id')
            ->where('ua.for_table', $forTable)
            ->where('ua.for_id', $forId)
            ->where('ua.user_id', '!=', 0)
            ->orderBy('u.username')
            ->get();

        return is_array($rows) ? $rows : array();
    }

    /**
     * Whether a resource is globally shared (user_id = 0, view = 1).
     *
     * @param string $forTable
     * @param int $forId
     * @return bool
     */
    public static function isGlobalAccess($forTable, $forId)
    {
        $access = self::query()
            ->from('user_access')
            ->where('user_id', 0)
            ->where('for_table', $forTable)
            ->where('for_id', $forId)
            ->first();

        return $access && (int) $access['view'] === 1;
    }

    /**
     * Remove every user_access row owned by a user (used on account deletion).
     *
     * @param int $userId
     */
    public static function purgeAccessFor($userId)
    {
        return self::query()
            ->from('user_access')
            ->where('user_id', (int) $userId)
            ->delete();
    }
}
