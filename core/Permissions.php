<?php
class Permissions
{
    /**
     * The complete, fixed capability set.
     */
    const CAPABILITIES = array(
        'payload.create',
        'payload.delete',
        'payload.share',
        'payload.viewall',
        'payload.behaviour',
        'payload.behaviour.persistent',
        'payload.behaviour.spider',
        'payload.behaviour.beacon',
        'payload.behaviour.customjs',
        'payload.behaviour.pages',
        'payload.behaviour.filters',
        'payload.behaviour.extensions',
        'report.view',
        'report.viewall',
        'report.share',
        'session.view',
        'session.viewall',
        'session.interact',
        'user.manage',
        'user.roles',
        'settings.manage',
        'apikey.manage',
        'log.view',
    );

    /**
     * The advanced-behaviour child capabilities under payload.behaviour.
     */
    const BEHAVIOUR_CAPS = array(
        'persistent' => 'payload.behaviour.persistent',
        'spider'     => 'payload.behaviour.spider',
        'beacon'     => 'payload.behaviour.beacon',
        'customjs'   => 'payload.behaviour.customjs',
        'pages'      => 'payload.behaviour.pages',
        'filters'    => 'payload.behaviour.filters',
        'extensions' => 'payload.behaviour.extensions',
    );

    /**
     * Role -> capability presets. 'admin' is the special "all" marker. Users get.
     */
    private static $presets = array(
        'admin'  => '*',
        'user'   => array('payload.create', 'payload.delete', 'report.view', 'report.share',
            'session.view', 'session.interact', 'apikey.manage',
            'payload.behaviour', 'payload.behaviour.persistent', 'payload.behaviour.spider', 'payload.behaviour.beacon',
            'payload.behaviour.customjs', 'payload.behaviour.pages', 'payload.behaviour.filters', 'payload.behaviour.extensions'),
    );

    /**
     * All known capabilities.
     */
    public static function capabilities()
    {
        return self::CAPABILITIES;
    }

    /**
     * The preset capability map (cap => bool) for a role, before per-user.
     *
     * @param string $role
     */
    public static function roleCaps($role)
    {

        if ($role === 'admin') {
            $caps = array();
            foreach (self::CAPABILITIES as $cap) {
                $caps[$cap] = true;
            }
            return $caps;
        }

        $granted = Role::grantedFor($role);
        if ($granted === null) {
            $granted = self::storedRolePreset($role);
        }
        if ($granted === null) {
            $preset = isset(self::$presets[$role]) ? self::$presets[$role] : array();
            $granted = ($preset === '*') ? self::CAPABILITIES : (array) $preset;
        }
        $caps = array();
        foreach (self::CAPABILITIES as $cap) {
            $caps[$cap] = in_array($cap, $granted, true);
        }
        return $caps;
    }

    /**
     * The editable default set for a role, as a granted-cap list, or null.
     *
     * @param string $role
     * @return array|null
     */
    public static function storedRolePreset($role)
    {
        if ($role === 'admin') {
            return null;
        }
        try {
            $raw = Setting::get('role_preset_' . $role, null);
        } catch (Throwable $e) {

            return null;
        }
        if ($raw === null || $raw === '') {
            return null;
        }
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return null;
        }
        $clean = array();
        foreach ($decoded as $cap) {
            if (in_array($cap, self::CAPABILITIES, true)) {
                $clean[] = $cap;
            }
        }
        return $clean;
    }

    /**
     * Keep only known capabilities from a granted-cap list.
     *
     * @param array $granted
     */
    public static function sanitizeGranted(array $granted)
    {
        $clean = array();
        foreach ($granted as $cap) {
            if (in_array($cap, self::CAPABILITIES, true)) {
                $clean[] = $cap;
            }
        }
        return array_values(array_unique($clean));
    }

    /**
     * Whether a user holds a capability (role preset + per-user overrides).
     *
     * @param array $user
     * @param string $cap
     * @return bool
     */
    public static function can($user, $cap)
    {
        if (!is_array($user) || !in_array($cap, self::CAPABILITIES, true)) {
            return false;
        }

        $role = isset($user['role']) ? $user['role'] : '';

        $caps = self::roleCaps($role);
        $allowed = isset($caps[$cap]) ? $caps[$cap] : false;

        $overrides = self::decodeOverrides(isset($user['permissions']) ? $user['permissions'] : null);
        if (array_key_exists($cap, $overrides)) {
            $allowed = (bool) $overrides[$cap];
        }

        return $allowed;
    }

    /**
     * Whether a user may use a specific advanced payload behaviour:
     *
     * @param array $user
     * @param string $key
     * @return bool
     */
    public static function canBehaviour($user, $key)
    {
        if (!isset(self::BEHAVIOUR_CAPS[$key])) {
            return false;
        }
        return self::can($user, 'payload.behaviour') && self::can($user, self::BEHAVIOUR_CAPS[$key]);
    }

    /**
     * The fully resolved capability map for a user (preset + overrides).
     *
     * @param array $user
     */
    public static function effective($user)
    {
        $role = (is_array($user) && isset($user['role'])) ? $user['role'] : '';

        $caps = self::roleCaps($role);
        $overrides = self::decodeOverrides((is_array($user) && isset($user['permissions'])) ? $user['permissions'] : null);
        foreach ($overrides as $cap => $val) {
            if (in_array($cap, self::CAPABILITIES, true)) {
                $caps[$cap] = (bool) $val;
            }
        }
        return $caps;
    }

    /**
     * Validate + normalize a client-supplied permissions object into a clean.
     *
     * @param array $permissions
     */
    public static function sanitizeOverrides($permissions)
    {
        $decoded = self::decodeOverrides($permissions);
        $clean = array();
        foreach ($decoded as $cap => $val) {
            if (in_array($cap, self::CAPABILITIES, true)) {
                $clean[$cap] = (bool) $val;
            }
        }
        return $clean;
    }

    /**
     * Decode a permissions value (array or JSON string) into an assoc array.
     *
     * @param array $permissions
     * @return array
     */
    private static function decodeOverrides($permissions)
    {
        if (is_array($permissions)) {
            return $permissions;
        }
        if (is_string($permissions) && $permissions !== '') {
            $decoded = json_decode($permissions, true);
            return is_array($decoded) ? $decoded : array();
        }
        return array();
    }

    /**
     * Whether $actor effectively holds every capability in a granted-cap list.
     *
     * @param array $actor
     * @param array $caps
     * @return bool
     */
    public static function holdsAll($actor, array $caps)
    {
        foreach ($caps as $cap) {
            if (!self::can($actor, $cap)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Whether every capability $sub effectively holds is also held by $super.
     *
     * @param string $sub
     * @param bool $super
     * @return bool
     */
    public static function capsSubset($sub, $super)
    {
        foreach (self::effective($sub) as $cap => $on) {
            if ($on && !self::can($super, $cap)) {
                return false;
            }
        }
        return true;
    }
}
