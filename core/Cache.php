<?php
class Cache
{
    /**
     * Get a cached value or run the callback when missing/expired.
     *
     * @param string $name
     * @param callable $callback
     * @param int $ttl
     */
    public static function remember($name, $callback, $ttl = 600)
    {
        $stmt = Database::getInstance()->prepare("SELECT value, timestamp FROM cache WHERE name = ?");
        $stmt->execute(array($name));
        $cached = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cached && (time() - $cached['timestamp']) < $ttl) {
            return json_decode($cached['value'], true);
        }

        $value = $callback();
        self::set($name, $value);
        return $value;
    }

    /**
     * Store a cache value.
     *
     * @param string $name
     * @param string $value
     */
    public static function set($name, $value)
    {
        $stmt = Database::getInstance()->prepare("
            INSERT INTO cache (name, value, timestamp)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
            value = VALUES(value),
            timestamp = VALUES(timestamp)
        ");

        return $stmt->execute(array($name, json_encode($value), time()));
    }

    /**
     * Atomic UPSERT increment; concurrent callers each get a distinct value.
     *
     * @param string $name
     * @return int
     */
    public static function increment($name)
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO cache (name, value, timestamp)
            VALUES (?, '1', ?)
            ON DUPLICATE KEY UPDATE value = value + 1, timestamp = VALUES(timestamp)
        ");
        $stmt->execute(array($name, time()));

        $read = $db->prepare("SELECT value FROM cache WHERE name = ?");
        $read->execute(array($name));
        $value = $read->fetchColumn();

        return ($value === false) ? 1 : (int) $value;
    }

    /**
     * Get a cache value.
     *
     * @param string $name
     */
    public static function get($name)
    {
        $stmt = Database::getInstance()->prepare("SELECT value FROM cache WHERE name = ?");
        $stmt->execute(array($name));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? json_decode($result['value'], true) : null;
    }

    /**
     * Delete a cache entry.
     *
     * @param string $name
     */
    public static function forget($name)
    {
        $stmt = Database::getInstance()->prepare("DELETE FROM cache WHERE name = ?");
        return $stmt->execute(array($name));
    }
}
