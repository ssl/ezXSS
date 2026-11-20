<?php
class Role extends Model
{
    protected static $table = 'roles';
    protected static $primaryKey = 'id';

    const BASE = 'user';

    /**
     * The granted-cap list for a role, or null when the role has no row.
     *
     * @param string $name
     * @return array|null
     */
    public static function grantedFor($name)
    {
        try {
            $row = self::query()->where('name', (string) $name)->first();
        } catch (Throwable $e) {
            return null;
        }
        if (!$row) {
            return null;
        }
        return self::decodePerms(isset($row['permissions']) ? $row['permissions'] : null);
    }

    /**
     * All stored roles as name => granted-cap list (empty on a missing table).
     *
     * @return array
     */
    public static function allRoles()
    {
        try {
            $rows = self::all();
        } catch (Throwable $e) {
            return array();
        }
        $out = array();
        foreach ($rows as $row) {
            $out[$row['name']] = self::decodePerms(isset($row['permissions']) ? $row['permissions'] : null);
        }
        return $out;
    }

    public static function exists($name)
    {
        try {
            return (bool) self::query()->where('name', (string) $name)->first();
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Create or update a role from a granted-cap list.
     *
     * @param string $name
     * @param array $granted
     */
    public static function upsert($name, array $granted)
    {
        $json = json_encode(array_values(array_unique($granted)));
        $existing = self::query()->where('name', (string) $name)->first();
        if ($existing) {
            self::query()->where('name', (string) $name)->update(array('permissions' => $json));
        } else {
            self::query()->insert(array('name' => (string) $name, 'permissions' => $json));
        }
    }

    public static function remove($name)
    {
        self::query()->where('name', (string) $name)->delete();
    }

    private static function decodePerms($p)
    {
        if (is_array($p)) {
            return $p;
        }
        if (is_string($p) && $p !== '') {
            $decoded = json_decode($p, true);
            return is_array($decoded) ? $decoded : array();
        }
        return array();
    }
}
