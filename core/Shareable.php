<?php
trait Shareable
{
    /**
     * Per-resource access check.
     *
     * @param int $userId
     * @param int $forId
     * @param string $accessType
     * @param string $forTable
     * @return bool
     */
    public static function hasAccess($userId, $forId, $accessType = 'view', $forTable = null)
    {
        if (!$forTable) {
            $forTable = static::$table;
        }

        $forItem = self::query()
            ->from($forTable)
            ->where('id', $forId)
            ->first();

        if (!$forItem) {
            return false;
        }

        if (self::isOwner($forItem, $userId)) {
            return true;
        }

        $type = strtolower((string) $accessType);
        if (strpos($type, 'delete') !== false || strpos($type, 'share') !== false) {

            return false;
        }

        if ($type === 'view') {
            $access = self::query()
                ->from('user_access')
                ->where('user_id', $userId)
                ->where('for_table', $forTable)
                ->where('for_id', $forId)
                ->where('view', 1)
                ->first();

            if (!$access) {

                $access = self::query()
                    ->from('user_access')
                    ->where('user_id', 0)
                    ->where('for_table', $forTable)
                    ->where('for_id', $forId)
                    ->where('view', 1)
                    ->first();
            }
        } else {
            $access = self::query()
                ->from('user_access')
                ->where('user_id', $userId)
                ->where('for_table', $forTable)
                ->where('for_id', $forId)
                ->where('edit', 1)
                ->first();
        }

        return !empty($access);
    }

    /**
     * Throw 403 unless the user has the requested access.
     *
     * @param int $userId
     * @param int $forId
     * @param string $accessType
     * @param string $forTable
     * @throws HttpException
     */
    public static function ensureAccess($userId, $forId, $accessType = 'view', $forTable = null)
    {
        if (!self::hasAccess($userId, $forId, $accessType, $forTable)) {
            throw new HttpException(403, 'Forbidden');
        }
    }

    /**
     * List all rows of a table the user may view: rows they own plus rows shared with them or globally.
     *
     * @param int $userId
     * @param string $forTable
     */
    public static function listWithAccess($userId, $forTable = null)
    {
        if (!$forTable) {
            $forTable = static::$table;
        }

        $accessRows = self::query()
            ->from('user_access')
            ->select('for_id')
            ->whereRaw('(`user_id` = ? OR `user_id` = 0)', array((int) $userId))
            ->where('for_table', $forTable)
            ->where('view', 1)
            ->get();

        $sharedIds = array();
        foreach ($accessRows as $row) {
            $sharedIds[(int) $row['for_id']] = true;
        }

        $query = self::query()->from($forTable);

        if (empty($sharedIds)) {
            $query->where('user_id', (int) $userId);
        } else {
            $placeholders = implode(',', array_fill(0, count($sharedIds), '?'));
            $params = array_keys($sharedIds);
            array_unshift($params, (int) $userId);
            $query->whereRaw('(`user_id` = ? OR `id` IN (' . $placeholders . '))', $params);
        }

        $rows = $query->get();

        $returnItems = array();
        $seen = array();
        foreach ($rows as $item) {
            $id = (int) $item['id'];
            if (!isset($seen[$id])) {
                $returnItems[] = $item;
                $seen[$id] = true;
            }
        }

        return $returnItems;
    }

    /**
     * Whether $userId owns a resource row (ownership is carried by user_id).
     *
     * @param array $row
     * @param int $userId
     * @return bool
     */
    protected static function isOwner($row, $userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return false;
        }
        return isset($row['user_id']) && (int) $row['user_id'] === $userId;
    }
}
