<?php
class Setting extends Model
{
    protected static $table = 'settings';
    protected static $primaryKey = 'id';

    /**
     * Read a single setting value (string) or a default when absent.
     *
     * @param string $setting
     * @param string $default
     */
    public static function get($setting, $default = null)
    {
        $row = self::query()->where('setting', $setting)->first();
        return $row ? $row['value'] : $default;
    }

    /**
     * Whether a boolean-style setting is enabled (stored value === '1').
     *
     * @param string $setting
     * @return string
     */
    public static function enabled($setting)
    {
        return (string) self::get($setting, '0') === '1';
    }

    /**
     * A short, stable per-install id that namespaces the client-id storage key in the payload, so
     * two ezXSS installs hitting one origin never overwrite each other's id. Made once, then stored.
     *
     * @return string
     */
    public static function clientKey()
    {
        $key = (string) self::get('client_key', '');
        if (!preg_match('/^[a-f0-9]{8,32}$/', $key)) {
            $key = bin2hex(random_bytes(4));
            self::put('client_key', $key);
        }
        return $key;
    }

    /**
     * Upsert a single setting in one statement (unique index on `setting`).
     *
     * @param string $setting
     * @param string $value
     */
    public static function put($setting, $value)
    {
        $stmt = Database::getInstance()->prepare(
            'INSERT INTO `settings` (`setting`, `value`) VALUES (?, ?)'
            . ' ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );
        $stmt->execute(array((string) $setting, (string) $value));
    }

    /**
     * Upsert many settings from a setting => value map in one multi-row statement.
     *
     * @param array $pairs
     */
    public static function putMany(array $pairs)
    {
        if (empty($pairs)) {
            return;
        }

        $placeholders = array();
        $params = array();
        foreach ($pairs as $setting => $value) {
            $placeholders[] = '(?, ?)';
            $params[] = (string) $setting;
            $params[] = (string) $value;
        }

        $stmt = Database::getInstance()->prepare(
            'INSERT INTO `settings` (`setting`, `value`) VALUES ' . implode(', ', $placeholders)
            . ' ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );
        $stmt->execute($params);
    }
}
