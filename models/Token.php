<?php
class Token extends Model
{
    protected static $table = 'tokens';
    protected static $primaryKey = 'id';

    /**
     * Raw token length in bytes (hex-encoded -> 64 chars).
     */
    const RAW_BYTES = 32;

    /**
     * Hash a raw token for storage / lookup.
     *
     * @param string $raw
     */
    public static function hash($raw)
    {
        return hash('sha256', (string) $raw);
    }

    /**
     * Issue a new token. Stores only the hash; returns the raw token once.
     *
     * @param int $userId
     * @param string $type
     * @param string $name
     * @param int $ttl
     * @throws InvalidArgumentException
     * @return array
     */
    public static function issue($userId, $type = 'session', $name = null, $ttl = null)
    {
        if (!in_array($type, array('session', 'apikey'), true)) {
            throw new InvalidArgumentException('Invalid token type');
        }

        $raw = bin2hex(random_bytes(self::RAW_BYTES));
        $expiresAt = ($ttl !== null) ? date('Y-m-d H:i:s', time() + (int) $ttl) : null;

        $data = array(
            'user_id'    => (int) $userId,
            'token_hash' => self::hash($raw),
            'type'       => $type,
            'name'       => $name,
            'expires_at' => $expiresAt,
        );

        $id = self::query()->insert($data);

        return array(
            'raw'        => $raw,
            'id'         => (int) $id,
            'type'       => $type,
            'name'       => $name,
            'expires_at' => $expiresAt,
        );
    }

    /**
     * Look up a valid (non-expired) token by its raw value (expiry evaluated in PHP).
     *
     * @param string $raw
     * @return array|null
     */
    public static function findValidByRaw($raw)
    {
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $row = self::query()->where('token_hash', self::hash($raw))->first();
        if (!$row) {
            return null;
        }

        if (!empty($row['expires_at'])) {
            $exp = strtotime($row['expires_at']);
            if ($exp !== false && $exp <= time()) {
                return null;
            }
        }

        return $row;
    }

    /**
     * Update last_used_at = now for a token id.
     *
     * @param int $id
     */
    public static function touch($id)
    {
        return self::query()
            ->where('id', (int) $id)
            ->update(array('last_used_at' => date('Y-m-d H:i:s')));
    }

    /**
     * Revoke a single token by id (optionally constrained to an owner).
     *
     * @param int $id
     * @param int $userId
     */
    public static function revokeById($id, $userId = null)
    {
        $query = self::query()->where('id', (int) $id);
        if ($userId !== null) {
            $query->where('user_id', (int) $userId);
        }
        $stmt = Database::getInstance()->prepare($query->toDeleteSql());
        $stmt->execute($query->getBindings());
        return $stmt->rowCount() > 0;
    }

    /**
     * Revoke every token for a user, optionally only of one type.
     *
     * @param int $userId
     * @param string $type
     */
    public static function revokeAllForUser($userId, $type = null)
    {
        $query = self::query()->where('user_id', (int) $userId);
        if ($type !== null) {
            $query->where('type', $type);
        }
        return $query->delete();
    }

    /**
     * Revoke a user's session tokens, optionally keeping one (the caller's current token).
     *
     * @param int $userId
     * @param int $exceptId
     */
    public static function revokeSessionsExcept($userId, $exceptId = 0)
    {
        $query = self::query()->where('user_id', (int) $userId)->where('type', 'session');
        if ((int) $exceptId > 0) {
            $query->where('id', '!=', (int) $exceptId);
        }
        return $query->delete();
    }

    /**
     * List a user's tokens of a given type with safe fields only (never the hash or raw token).
     *
     * @param int $userId
     * @param string $type
     * @return bool
     */
    public static function listForUser($userId, $type = 'apikey')
    {
        $rows = self::query()
            ->select(array('id', 'name', 'type', 'last_used_at', 'expires_at', 'created_at'))
            ->where('user_id', (int) $userId)
            ->where('type', $type)
            ->orderBy('created_at', 'DESC')
            ->get();

        return is_array($rows) ? $rows : array();
    }

    /**
     * Delete expired tokens (housekeeping).
     */
    public static function purgeExpired()
    {
        $stmt = Database::getInstance()->prepare(
            "DELETE FROM `tokens` WHERE `expires_at` IS NOT NULL AND `expires_at` <= ?"
        );
        return $stmt->execute(array(date('Y-m-d H:i:s')));
    }
}
