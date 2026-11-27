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
     * Pull the next queued command for a client/origin/payload: the oldest `executed = 0`.
     *
     * @param string $clientId
     * @param string $origin
     * @param string|null $payload
     * @return string
     */
    public static function getNext($clientId, $origin, $payload = null)
    {
        $q = self::query()
            ->select('id, command')
            ->where('client_id', substr((string) $clientId, 0, 50))
            ->where('origin', substr((string) $origin, 0, self::LIMIT_ORIGIN))
            ->where('executed', 0);
        self::scopePayload($q, $payload);
        $row = $q->orderBy('id', 'ASC')->first();

        if (!$row) {
            return '';
        }

        self::query()->where('id', (int) $row['id'])->update(array('executed' => 1));

        return (string) $row['command'];
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
