<?php
class Console extends Model
{
    protected static $table = 'console';
    protected static $primaryKey = 'id';

    /**
     * console.origin column width (sessions.origin is 255).
     */
    const LIMIT_ORIGIN = 500;

    /**
     * console.payload column width.
     */
    const LIMIT_PAYLOAD = 255;

    /**
     * Narrow a query to one payload when given (null = every payload of the client/origin).
     *
     * @param QueryBuilder $q
     * @param string|null $payload
     * @return QueryBuilder
     */
    private static function scopePayload($q, $payload)
    {
        if ($payload !== null) {
            $q->where('payload', substr((string) $payload, 0, self::LIMIT_PAYLOAD));
        }
        return $q;
    }

    /**
     * Queue a console command for a client/origin/payload. Returns the new row id.
     *
     * @param string $clientId
     * @param string $origin
     * @param string $payload
     * @param string $command
     * @return int
     */
    public static function add($clientId, $origin, $payload, $command)
    {
        return (int) self::query()->insert(array(
            'client_id' => substr((string) $clientId, 0, 50),
            'origin'    => substr((string) $origin, 0, self::LIMIT_ORIGIN),
            'payload'   => substr((string) $payload, 0, self::LIMIT_PAYLOAD),
            'command'   => (string) $command,
            'executed'  => 0,
        ));
    }

    /**
     * Pull the next queued command for a client/origin/payload: the oldest `executed = 0`, claimed
     * atomically with a compare-and-set so two concurrent pings never run the same command twice.
     *
     * @param string $clientId
     * @param string $origin
     * @param string|null $payload
     * @return string
     */
    public static function getNext($clientId, $origin, $payload = null)
    {
        $db = Database::getInstance();
        $clientId = substr((string) $clientId, 0, 50);
        $origin = substr((string) $origin, 0, self::LIMIT_ORIGIN);

        $where = '`client_id` = ? AND `origin` = ? AND `executed` = 0';
        $params = array($clientId, $origin);
        if ($payload !== null) {
            $where .= ' AND `payload` = ?';
            $params[] = substr((string) $payload, 0, self::LIMIT_PAYLOAD);
        }

        $select = $db->prepare("SELECT `id`, `command` FROM `console` WHERE {$where} ORDER BY `id` ASC LIMIT 1");
        $claim = $db->prepare("UPDATE `console` SET `executed` = 1 WHERE `id` = ? AND `executed` = 0");

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $select->execute($params);
            $row = $select->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return '';
            }

            $claim->execute(array((int) $row['id']));
            if ($claim->rowCount() === 1) {
                return (string) $row['command'];
            }
        }

        return '';
    }

    /**
     * List all queued and already-sent commands for a client/origin/payload, oldest first.
     *
     * @param string $clientId
     * @param string $origin
     * @param string|null $payload
     */
    public static function listFor($clientId, $origin, $payload = null)
    {
        $q = self::query()
            ->where('client_id', substr((string) $clientId, 0, 50))
            ->where('origin', substr((string) $origin, 0, self::LIMIT_ORIGIN));
        self::scopePayload($q, $payload);
        return $q->orderBy('id', 'ASC')->get();
    }

    /**
     * Whether unexecuted commands exist for a client/origin/payload (without consuming them).
     *
     * @param string $clientId
     * @param string $origin
     * @param string|null $payload
     * @return bool
     */
    public static function hasPending($clientId, $origin, $payload = null)
    {
        $q = self::query()
            ->where('client_id', substr((string) $clientId, 0, 50))
            ->where('origin', substr((string) $origin, 0, self::LIMIT_ORIGIN))
            ->where('executed', 0);
        self::scopePayload($q, $payload);
        return (bool) $q->first();
    }

    /**
     * Delete every queued/sent command for a client/origin (one payload, or all when null).
     *
     * @param string $clientId
     * @param string $origin
     * @param string|null $payload
     */
    public static function deleteFor($clientId, $origin, $payload = null)
    {
        $q = self::query()
            ->where('client_id', substr((string) $clientId, 0, 50))
            ->where('origin', substr((string) $origin, 0, self::LIMIT_ORIGIN));
        self::scopePayload($q, $payload);
        $q->delete();
    }
}
