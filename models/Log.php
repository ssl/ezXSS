<?php
class Log extends Model
{
    protected static $table = 'logs';
    protected static $primaryKey = 'id';

    /**
     * The granular audit categories (match the `log_<cat>` settings).
     */
    const CATEGORIES = array('logins', 'reports', 'payloads', 'users', 'settings', 'alerts');

    /**
     * A page of log rows, newest first. $limit is clamped to 1..200.
     *
     * @param int $limit
     * @param int $offset
     * @param array $filters
     */
    public static function listPaged($limit, $offset, $filters = array())
    {
        $limit = (int) $limit;
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 200) {
            $limit = 200;
        }
        $offset = max(0, (int) $offset);

        $q = self::applyFilters(self::query(), $filters)
            ->select(array('id', 'user_id', 'type', 'description', 'ip', 'created_at'))
            ->orderBy('id', 'DESC')
            ->limit($limit, $offset);

        return $q->get();
    }

    /**
     * Number of log rows matching the given filters (same keys as listPaged).
     *
     * @param array $filters
     * @return int
     */
    public static function countFiltered($filters = array())
    {
        return (int) self::applyFilters(self::query(), $filters)->count();
    }

    /**
     * Write a category-gated audit row, only when the `log_<type>` setting is enabled.
     *
     * @param string $type
     * @param int $userId
     * @param string $description
     * @param string $ip
     * @return int
     */
    public static function event($type, $userId, $description, $ip)
    {
        try {
            $type = (string) $type;
            if ($type === '' || !in_array($type, self::CATEGORIES, true)) {
                return 0;
            }
            if (!Setting::enabled('log_' . $type)) {
                return 0;
            }
            return (int) self::query()->insert(array(
                'user_id'     => (int) $userId,
                'type'        => $type,
                'description' => (string) $description,
                'ip'          => (string) $ip,
                'created_at'  => time(),
            ));
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Apply the listPaged/countFiltered filter map to a QueryBuilder.
     *
     * @param string $q
     * @param array $filters
     */
    private static function applyFilters($q, $filters)
    {
        if (!is_array($filters)) {
            return $q;
        }

        if (array_key_exists('user_id', $filters) && $filters['user_id'] !== null && $filters['user_id'] !== '') {
            $q->where('user_id', (int) $filters['user_id']);
        }
        if (!empty($filters['type'])) {
            $q->where('type', (string) $filters['type']);
        }
        if (isset($filters['from']) && $filters['from'] !== '') {
            $q->where('created_at', '>=', (int) $filters['from']);
        }
        if (isset($filters['to']) && $filters['to'] !== '') {
            $q->where('created_at', '<=', (int) $filters['to']);
        }
        if (isset($filters['search']) && trim((string) $filters['search']) !== '') {
            $q->where('description', 'LIKE', '%' . QueryBuilder::escapeLike(trim((string) $filters['search'])) . '%');
        }

        return $q;
    }
}
