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
     * Queue a console command for a client/origin. Returns the new row id.
     *
     * @param string $clientId
     * @param string $origin
     * @param string $command
     * @return int
     */
    public static function add($clientId, $origin, $command)
    {
        return (int) self::query()->insert(array(
            'client_id' => substr((string) $clientId, 0, 50),
            'origin'    => substr((string) $origin, 0, self::LIMIT_ORIGIN),
            'command'   => (string) $command,
            'executed'  => 0,
        ));
    }

    /**
     * Pull the next queued command for a client/origin: the oldest `executed = 0`.
     *
     * @param string $clientId
     * @param string $origin
     * @return string
     */
    public static function getNext($clientId, $origin)
    {
        $row = self::query()
            ->select('id, command')
            ->where('client_id', substr((string) $clientId, 0, 50))
            ->where('origin', substr((string) $origin, 0, self::LIMIT_ORIGIN))
            ->where('executed', 0)
            ->orderBy('id', 'ASC')
            ->first();

        if (!$row) {
            return '';
        }

        self::query()->where('id', (int) $row['id'])->update(array('executed' => 1));

        return (string) $row['command'];
    }

    /**
     * List all queued and already-sent commands for a client/origin, oldest first.
     *
     * @param string $clientId
     * @param string $origin
     */
    public static function listFor($clientId, $origin)
    {
        return self::query()
            ->where('client_id', substr((string) $clientId, 0, 50))
            ->where('origin', substr((string) $origin, 0, self::LIMIT_ORIGIN))
            ->orderBy('id', 'ASC')
            ->get();
    }

    /**
     * Whether unexecuted commands exist for a client/origin (without consuming them).
     *
     * @param string $clientId
     * @param string $origin
     * @return bool
     */
    public static function hasPending($clientId, $origin)
    {
        return (bool) self::query()
            ->where('client_id', substr((string) $clientId, 0, 50))
            ->where('origin', substr((string) $origin, 0, self::LIMIT_ORIGIN))
            ->where('executed', 0)
            ->first();
    }

    /**
     * Delete every queued/sent command for a client/origin.
     *
     * @param string $clientId
     * @param string $origin
     */
    public static function deleteFor($clientId, $origin)
    {
        self::query()
            ->where('client_id', substr((string) $clientId, 0, 50))
            ->where('origin', substr((string) $origin, 0, self::LIMIT_ORIGIN))
            ->delete();
    }
}
