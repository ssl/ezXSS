<?php
class Payload extends Model
{
    use Shareable;

    protected static $table = 'payloads';
    protected static $primaryKey = 'id';

    /**
     * The sentinel filter value selecting reports/sessions matching no stored payload.
     */
    const CATCHALL_FILTER = '__catchall__';

    /**
     * SQL predicate: a table's `payload` column resolves to no existing payload identifier.
     *
     * @param string $table
     * @return string
     */
    public static function catchAllNotExistsSql($table)
    {
        Model::isSafe($table);
        $col = '`' . $table . '`.`payload`';
        $norm = "TRIM(BOTH '/' FROM SUBSTRING_INDEX(SUBSTRING_INDEX(" . $col . ", '?', 1), '://', -1))";
        return "NOT EXISTS (SELECT 1 FROM `payloads` `p` WHERE `p`.`identifier` = SUBSTRING_INDEX(" . $norm . ", '/', 1)"
            . " OR `p`.`identifier` = SUBSTRING_INDEX(" . $norm . ", '/', 2))";
    }

    /**
     * The fixed set of collectable options. Column = 'collect_' . $option in payload_collect and in the global settings.
     */
    const COLLECT_OPTIONS = array(
        'uri', 'ip', 'referer', 'useragent', 'cookies',
        'localstorage', 'sessionstorage', 'dom', 'screenshot', 'csp',
    );

    /**
     * Whether an identifier is not yet used by any payload (exact match).
     *
     * @param string $identifier
     * @return bool
     */
    public static function isAvailable($identifier)
    {
        return !self::findBy('identifier', $identifier);
    }

    /**
     * Whether an identifier is free to use (no conflict with an existing payload).
     *
     * @param string $identifier
     * @param int $userId
     * @return bool
     */
    public static function isDomainAvailable($identifier, $userId)
    {
        $payloads = self::all();

        $newParts = explode('/', $identifier, 2);
        $newDomain = $newParts[0];
        $newPath = isset($newParts[1]) ? $newParts[1] : '';

        foreach ($payloads as $existing) {
            $existingParts = explode('/', $existing['identifier'], 2);
            $existingDomain = $existingParts[0];
            $existingPath = isset($existingParts[1]) ? $existingParts[1] : '';

            if ($identifier === $existing['identifier'] ||
                ($newDomain === $existingDomain && (
                    (int) $existing['user_id'] !== (int) $userId ||
                    $newPath === $existingPath ||
                    ($newPath === '' && $existingPath !== '')
                ))
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the identifier would collide with a payload the target user already owns.
     *
     * @param string $identifier
     * @param int $targetUserId
     * @param int $excludePayloadId
     * @return bool
     */
    public static function wouldCollideForOwner($identifier, $targetUserId, $excludePayloadId = 0)
    {
        $payloads = self::all();

        $newParts = explode('/', $identifier, 2);
        $newDomain = $newParts[0];
        $newPath = isset($newParts[1]) ? $newParts[1] : '';

        foreach ($payloads as $existing) {
            if ((int) $existing['user_id'] !== (int) $targetUserId) {
                continue;
            }
            if ((int) $existing['id'] === (int) $excludePayloadId) {
                continue;
            }

            $existingParts = explode('/', $existing['identifier'], 2);
            $existingDomain = $existingParts[0];
            $existingPath = isset($existingParts[1]) ? $existingParts[1] : '';

            if ($newDomain === $existingDomain && (
                    $newPath === $existingPath ||
                    ($newPath === '' && $existingPath !== '')
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a payload owned by $userId, seeding payload_collect from the global defaults.
     *
     * @param int $userId
     * @param string $identifier
     */
    public static function createOwned($userId, $identifier)
    {
        $id = (int) self::query()->insert(array(
            'identifier' => $identifier,
            'user_id'    => (int) $userId,
        ));

        self::initCollect($id);

        return $id;
    }

    /**
     * Insert the payload_collect row seeded from the global collect defaults.
     *
     * @param int $payloadId
     */
    public static function initCollect($payloadId)
    {
        $row = array('payload_id' => (int) $payloadId);
        foreach (self::COLLECT_OPTIONS as $opt) {
            $row['collect_' . $opt] = Setting::enabled('collect_' . $opt) ? 1 : 0;
        }
        self::query()->from('payload_collect')->insert($row);
    }

    /**
     * The payload_collect toggles for a payload as an option => bool map (all-off when missing).
     *
     * @param int $payloadId
     */
    public static function collect($payloadId)
    {
        $row = self::query()->from('payload_collect')->where('payload_id', (int) $payloadId)->first();
        $out = array();
        foreach (self::COLLECT_OPTIONS as $opt) {
            $out[$opt] = ($row && isset($row['collect_' . $opt])) ? ((int) $row['collect_' . $opt] === 1) : false;
        }
        return $out;
    }

    /**
     * All extra pages for a payload.
     *
     * @param int $payloadId
     */
    public static function pages($payloadId)
    {
        return self::query()->from('payload_pages')->where('payload_id', (int) $payloadId)->orderBy('id')->get();
    }

    /**
     * All filters for a payload, optionally of a single type (allow|deny).
     *
     * @param int $payloadId
     * @param string $type
     */
    public static function filters($payloadId, $type = null)
    {
        $q = self::query()->from('payload_filters')->where('payload_id', (int) $payloadId);
        if ($type !== null) {
            $q->where('type', $type);
        }
        return $q->orderBy('id')->get();
    }

    /**
     * Assemble the full config for a payload (row + collect + pages + filters + extensions), or null.
     *
     * @param int $payloadId
     * @return array|null
     */
    public static function fullConfig($payloadId)
    {
        $payload = self::find($payloadId);
        if (!$payload) {
            return null;
        }

        $allow = array();
        $deny = array();
        foreach (self::filters($payloadId) as $f) {
            if ($f['type'] === 'allow') {
                $allow[] = array('id' => (int) $f['id'], 'domain' => $f['domain']);
            } else {
                $deny[] = array('id' => (int) $f['id'], 'domain' => $f['domain']);
            }
        }

        $pages = array();
        foreach (self::pages($payloadId) as $p) {
            $pages[] = array('id' => (int) $p['id'], 'path' => $p['path']);
        }

        $extensionIds = self::extensionIds($payload);

        $collectGates = array();
        foreach (self::COLLECT_OPTIONS as $opt) {
            $collectGates[$opt] = Setting::enabled('collect_' . $opt);
        }
        $gates = array(
            'persistent' => Setting::enabled('persistent'),
            'spider'     => Setting::enabled('spider'),
            'beacon'     => Setting::enabled('beacon_fallback'),
            'customjs'   => (string) Setting::get('allow_customjs', '1') !== '0',
            'pages'      => (string) Setting::get('allow_pages', '1') !== '0',
            'filters'    => (string) Setting::get('allow_filters', '1') !== '0',
            'extensions' => (string) Setting::get('allow_extensions', '1') !== '0',
            'collect'    => $collectGates,
        );

        return array(
            'id'          => (int) $payload['id'],
            'identifier'  => $payload['identifier'],
            'owner'       => (int) $payload['user_id'],
            'user_id'     => (int) $payload['user_id'],
            'persistent'  => (int) $payload['persistent'] === 1,
            'spider'      => (int) $payload['spider'],
            'beacon'      => (int) (isset($payload['beacon']) ? $payload['beacon'] : 0) === 1,
            'customjs'    => $payload['customjs'],
            'customjs2'   => $payload['customjs2'],
            'extensions'  => $extensionIds,
            'collect'     => self::collect($payloadId),
            'pages'       => $pages,
            'filters'     => array('allow' => $allow, 'deny' => $deny),
            'gates'       => $gates,
            'created_at'  => $payload['created_at'],
        );
    }

    /**
     * Compact summary row for list views.
     *
     * @param array $payload
     */
    public static function summary($payload)
    {
        $id = (int) $payload['id'];
        return array(
            'id'           => $id,
            'identifier'   => $payload['identifier'],
            'owner'        => (int) $payload['user_id'],
            'user_id'      => (int) $payload['user_id'],
            'persistent'   => (int) $payload['persistent'] === 1,
            'spider'       => (int) $payload['spider'],
            'pages_count'  => (int) self::query()->from('payload_pages')->where('payload_id', $id)->count(),
            'filters_count' => (int) self::query()->from('payload_filters')->where('payload_id', $id)->count(),
            'created_at'   => $payload['created_at'],
        );
    }

    /**
     * The selected extension ids on a payload as an int array.
     *
     * @param array $payload
     * @return array
     */
    public static function extensionIds($payload)
    {
        if (empty($payload['extensions'])) {
            return array();
        }
        $ids = array();
        foreach (explode(',', $payload['extensions']) as $part) {
            $part = trim($part);
            if ($part !== '' && ctype_digit($part)) {
                $ids[] = (int) $part;
            }
        }
        return $ids;
    }

    /**
     * Delete a payload and cascade-remove its sub-rows (pages, filters, collect) and sharing rows.
     *
     * @param int $payload
     */
    public static function deleteCascade($payloadId)
    {
        $payloadId = (int) $payloadId;
        self::query()->from('payload_pages')->where('payload_id', $payloadId)->delete();
        self::query()->from('payload_filters')->where('payload_id', $payloadId)->delete();
        self::query()->from('payload_collect')->where('payload_id', $payloadId)->delete();
        self::query()->from('user_access')->where('for_table', 'payloads')->where('for_id', $payloadId)->delete();
        self::delete($payloadId);
    }

    /**
     * Resolve a payload for an identifier/URL: full domain/path, then bare domain.
     *
     * @param string $raw
     * @return array|null
     */
    public static function resolveByIdentifier($raw)
    {
        $raw = PayloadBuilder::normalizeIdentifier($raw);

        if ($raw !== '') {
            $parts = explode('/', $raw);
            $domain = $parts[0];
            $withPath = (isset($parts[1]) && $parts[1] !== '') ? $domain . '/' . $parts[1] : $domain;

            if ($withPath !== $domain) {
                $byPath = self::findBy('identifier', $withPath);
                if ($byPath) {
                    return $byPath;
                }
            }

            $byDomain = self::findBy('identifier', $domain);
            if ($byDomain) {
                return $byDomain;
            }
        }

        return null;
    }

    /**
     * Whether a payload row exists for the identifier/URL (same lookup as resolveByIdentifier).
     *
     * @param string $raw
     * @return bool
     */
    public static function identifierExists($raw)
    {
        return (bool) self::resolveByIdentifier($raw);
    }

    /**
     * Distinct payload identifiers a user can access (own plus global grants).
     *
     * @param int $userId
     */
    public static function accessibleIdentifiers($userId)
    {
        $rows = self::listWithAccess((int) $userId, 'payloads');
        $idents = array();
        foreach ($rows as $row) {
            if (isset($row['identifier']) && $row['identifier'] !== '') {
                $idents[(string) $row['identifier']] = (string) $row['identifier'];
            }
        }
        return array_values($idents);
    }
}
