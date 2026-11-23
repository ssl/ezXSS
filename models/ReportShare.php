<?php
class ReportShare extends Model
{
    protected static $table = 'report_shares';
    protected static $primaryKey = 'id';

    /**
     * Slug alphabet + length - 25 chars from [0-9a-z] ≈ 129 bits of entropy.
     */
    const SLUG_ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyz';
    const SLUG_LENGTH = 25;

    /**
     * Create a share row and return its slug + stored metadata.
     *
     * @param int $reportId
     * @param array $columns
     * @param int $expiresTs
     * @return array
     */
    public static function createShare($reportId, array $columns, $expiresTs = null)
    {
        $slug = self::mintSlug();
        $expiresAt = ($expiresTs !== null) ? gmdate('Y-m-d H:i:s', (int) $expiresTs) : null;

        self::query()->insert(array(
            'slug'            => $slug,
            'report_id'       => (int) $reportId,
            'allowed_columns' => json_encode(array_values($columns)),
            'expires_at'      => $expiresAt,
        ));

        return array(
            'slug'            => $slug,
            'report_id'       => (int) $reportId,
            'allowed_columns' => array_values($columns),
            'expires_at'      => $expiresAt,
        );
    }

    /**
     * Mint a 25-char lowercase slug, each char drawn from the alphabet via random_int().
     */
    public static function mintSlug()
    {
        $alphabet = self::SLUG_ALPHABET;
        $max = strlen($alphabet) - 1;
        $slug = '';
        for ($i = 0; $i < self::SLUG_LENGTH; $i++) {
            $slug .= $alphabet[random_int(0, $max)];
        }
        return $slug;
    }

    /**
     * Whether a string has the exact shape of a mintable slug.
     *
     * @param string $slug
     */
    public static function isValidSlug($slug)
    {
        return is_string($slug)
            && preg_match('/^[0-9a-z]{' . self::SLUG_LENGTH . '}$/', $slug) === 1;
    }

    /**
     * List the shares for a report (safe fields, including the slug).
     *
     * @param int $reportId
     */
    public static function listForReport($reportId)
    {
        $rows = self::query()->where('report_id', (int) $reportId)->orderBy('id', 'DESC')->get();
        $out = array();
        foreach ($rows as $row) {
            $out[] = self::present($row);
        }
        return $out;
    }

    /**
     * Find a share by its public slug, or null.
     *
     * @param string $slug
     */
    public static function findBySlug($slug)
    {
        $row = self::query()->where('slug', (string) $slug)->first();
        return $row ? $row : null;
    }

    /**
     * Whether a share row has expired (expires_at, stored as UTC, in the past).
     *
     * @param array $share
     * @return bool
     */
    public static function isExpired($share)
    {
        if (empty($share['expires_at'])) {
            return false;
        }
        $ts = strtotime(((string) $share['expires_at']) . ' UTC');
        return $ts !== false && $ts <= time();
    }

    /**
     * Decode a share's allowed column list to an array of strings.
     *
     * @param array $share
     * @return array
     */
    public static function columnsOf($share)
    {
        $cols = isset($share['allowed_columns']) ? json_decode((string) $share['allowed_columns'], true) : array();
        if (!is_array($cols)) {
            return array();
        }

        $clean = array();
        foreach ($cols as $c) {
            $c = (string) $c;
            if (in_array($c, Report::SHAREABLE_COLUMNS, true)
                || (strpos($c, 'extra:') === 0 && strlen($c) > 6)) {
                $clean[] = $c;
            }
        }
        return $clean;
    }

    /**
     * Delete all shares for a report.
     *
     * @param int $reportId
     */
    public static function deleteForReport($reportId)
    {
        self::query()->where('report_id', (int) $reportId)->delete();
    }

    /**
     * A public-safe presentation of a share row.
     *
     * @param array $row
     * @return array
     */
    private static function present($row)
    {
        return array(
            'id'              => (int) $row['id'],
            'slug'            => $row['slug'],
            'report_id'       => (int) $row['report_id'],
            'allowed_columns' => self::columnsOf($row),
            'expires_at'      => $row['expires_at'],
            'created_at'      => isset($row['created_at']) ? $row['created_at'] : null,
        );
    }
}
