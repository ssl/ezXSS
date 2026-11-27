<?php
abstract class Model
{
    protected static $table;
    protected static $primaryKey = 'id';

    /**
     * Start a new query.
     *
     * @return QueryBuilder
     */
    public static function query()
    {
        return new QueryBuilder(static::$table, static::$primaryKey);
    }

    /**
     * Find all records.
     */
    public static function all()
    {
        return self::query()->get();
    }

    /**
     * Find a record by primary key.
     *
     * @param int $id
     */
    public static function find($id)
    {
        return self::query()->where(static::$primaryKey, $id)->first();
    }

    /**
     * Create a new record.
     *
     * @param array $data
     * @return array
     */
    public static function create($data)
    {
        return self::query()->insert($data);
    }

    /**
     * Update a record by primary key.
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public static function update($id, $data)
    {
        return self::query()->where(static::$primaryKey, $id)->update($data);
    }

    /**
     * Delete a record by primary key.
     *
     * @param int $id
     */
    public static function delete($id)
    {
        return self::query()->where(static::$primaryKey, $id)->delete();
    }

    /**
     * Find one record by a specific field.
     *
     * @param string $field
     * @param string $value
     */
    public static function findBy($field, $value)
    {
        return self::query()->where($field, $value)->first();
    }

    /**
     * Translate a database constraint error into a friendly message.
     *
     * @param string $exception
     * @return string
     */
    public static function parseConstraintError($exception)
    {
        if ($exception instanceof PDOException &&
            ($exception->getCode() == 23000 ||
                (isset($exception->errorInfo) && $exception->errorInfo[1] == 1062)) &&
            strpos($exception->getMessage(), '1062 Duplicate entry') !== false) {
            return 'This record already exists.';
        }

        if ($exception instanceof PDOException &&
            ($exception->getCode() == 23000 ||
                (isset($exception->errorInfo) && $exception->errorInfo[1] == 1452)) &&
            strpos($exception->getMessage(), '1452 Cannot add or update a child row') !== false) {
            return 'This record does not exist.';
        }

        return $exception->getMessage();
    }

    /**
     * Validate identifiers, operators and clause fragments before they touch SQL.
     *
     * @param string $value
     * @param string $type
     * @throws InvalidArgumentException
     */
    public static function isSafe($value, $type = 'identifier')
    {
        switch ($type) {
            case 'identifier':
                if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $value)) {
                    throw new InvalidArgumentException('Invalid identifier');
                }
                break;
            case 'extended':
                if (!preg_match('/^[a-zA-Z0-9_\-\.,\s*]+$/', $value)) {
                    throw new InvalidArgumentException('Invalid extended');
                }
                break;
            case 'operator':
                if (!in_array($value, array('=', '!=', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS NULL', 'IS NOT NULL'), true)) {
                    throw new InvalidArgumentException('Invalid operator');
                }
                break;
            case 'type':
                if (!in_array($value, array('INNER', 'LEFT', 'RIGHT'), true)) {
                    throw new InvalidArgumentException('Invalid join type');
                }
                break;
            case 'direction':
                if (!in_array($value, array('ASC', 'DESC'), true)) {
                    throw new InvalidArgumentException('Invalid direction');
                }
                break;
            default:
                throw new InvalidArgumentException('Invalid type');
        }
    }
}

class Compression
{
    /**
     * Whether blob compression is enabled globally.
     */
    public static function enabled()
    {
        return Setting::enabled('compress');
    }

    /**
     * Whether a stored screenshot value is an on-disk filename rather than inline base64.
     *
     * @param string $value
     * @return bool
     */
    public static function isStoredScreenshotFile($value)
    {
        return (bool) preg_match('/^[0-9a-f]{52}$/', (string) $value);
    }

    /**
     * Compress a plain text blob; '' passes through.
     *
     * @param string $value
     * @return string
     */
    public static function pack($value)
    {
        $value = (string) $value;
        return $value !== '' ? base64_encode(gzdeflate($value, 9)) : '';
    }

    /**
     * Decompress a plain text blob; empty passes through.
     *
     * @param string $value
     * @return string
     */
    public static function unpack($value)
    {
        return !empty($value) ? gzinflate(base64_decode($value)) : '';
    }

    /**
     * Compress a JSON blob; '{}' and '' pass through.
     *
     * @param string $value
     * @return string
     */
    public static function packJson($value)
    {
        $value = (string) $value;
        if ($value === '{}') {
            return '{}';
        }
        return $value !== '' ? base64_encode(gzdeflate($value, 9)) : '';
    }

    /**
     * Decompress a JSON blob; '{}' and empty pass through.
     *
     * @param string $value
     * @return string
     */
    public static function unpackJson($value)
    {
        if ($value === '{}') {
            return '{}';
        }
        return !empty($value) ? gzinflate(base64_decode($value)) : '';
    }

    /**
     * Compress an inline base64 screenshot; stored-file names and '' pass through.
     *
     * @param string $value
     * @return string
     */
    public static function packScreenshot($value)
    {
        $value = (string) $value;
        if ($value === '' || self::isStoredScreenshotFile($value)) {
            return $value;
        }
        return base64_encode(gzdeflate(base64_decode($value), 9));
    }

    /**
     * Decompress an inline base64 screenshot; stored-file names and empty pass through.
     *
     * @param string $value
     * @return string
     */
    public static function unpackScreenshot($value)
    {
        if (empty($value) || self::isStoredScreenshotFile($value)) {
            return $value;
        }
        return base64_encode(gzinflate(base64_decode($value)));
    }
}
