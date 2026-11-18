<?php
class AlertConfig extends Model
{
    protected static $table = 'alerts';
    protected static $primaryKey = 'id';

    /**
     * method name => method id.
     */
    const METHODS = array(
        'mail'     => 1,
        'telegram' => 2,
        'slack'    => 3,
        'discord'  => 4,
    );

    /**
     * method id => method name (reverse of METHODS).
     * @param string $methodId
     */
    public static function methodName($methodId)
    {
        $flip = array_flip(self::METHODS);
        return isset($flip[(int) $methodId]) ? $flip[(int) $methodId] : null;
    }

    /**
     * Fetch a single (user, method) row, or null when unset.
     *
     * @param int $userId
     * @param string $methodId
     */
    public static function getRow($userId, $methodId)
    {
        return self::query()
            ->where('user_id', (int) $userId)
            ->where('method_id', (int) $methodId)
            ->first() ?: null;
    }

    /**
     * Read a single field of a (user, method) row, with a default when unset.
     *
     * @param int $userId
     * @param string $methodId
     * @param string $field
     * @return bool
     */
    public static function getField($userId, $methodId, $field = 'value1')
    {
        $row = self::getRow($userId, $methodId);
        if (!$row) {
            return $field === 'enabled' ? false : '';
        }
        return isset($row[$field]) ? $row[$field] : ($field === 'enabled' ? false : '');
    }

    /**
     * Upsert a (user, method) alert destination.
     *
     * @param int $userId
     * @param string $methodId
     * @param bool $enabled
     * @param string $value1
     * @param string $value2
     * @return bool
     */
    public static function setRow($userId, $methodId, $enabled, $value1 = '', $value2 = '')
    {
        $data = array(
            'enabled' => $enabled ? 1 : 0,
            'value1'  => (string) $value1,
            'value2'  => (string) $value2,
        );

        $existing = self::getRow($userId, $methodId);
        if ($existing) {
            self::query()
                ->where('user_id', (int) $userId)
                ->where('method_id', (int) $methodId)
                ->update($data);
            return true;
        }

        $data['user_id'] = (int) $userId;
        $data['method_id'] = (int) $methodId;
        self::create($data);
        return true;
    }

    /**
     * All enabled rows for a method, scoped to the owning user plus the global (user_id 0) rows.
     *
     * @param string $methodId
     * @param int $userId
     */
    public static function recipientsFor($methodId, $userId = 0)
    {
        $userId = (int) $userId;
        $rows = self::query()
            ->where('method_id', (int) $methodId)
            ->where('enabled', 1)
            ->orderBy('id', 'ASC')
            ->get();

        $ownerBanned = false;
        if ($userId !== 0) {
            $owner = User::find($userId);
            $ownerBanned = ($owner && User::isBanned($owner));
        }

        $out = array();
        foreach ($rows as $row) {
            $owner = (int) $row['user_id'];
            if ($owner === 0) {
                $out[] = $row;
            } elseif ($owner === $userId && !$ownerBanned) {
                $out[] = $row;
            }
        }
        return $out;
    }

    /**
     * All enabled rows for a method owned exactly by the given user, without the global (user_id 0) rows.
     *
     * @param string $methodId
     * @param int $userId
     * @return array
     */
    public static function ownRecipientsFor($methodId, $userId)
    {
        $userId = (int) $userId;

        if ($userId !== 0) {
            $owner = User::find($userId);
            if ($owner && User::isBanned($owner)) {
                return array();
            }
        }

        return self::query()
            ->where('method_id', (int) $methodId)
            ->where('user_id', $userId)
            ->where('enabled', 1)
            ->orderBy('id', 'ASC')
            ->get();
    }
}
