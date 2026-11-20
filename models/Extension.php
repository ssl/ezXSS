<?php
class Extension extends Model
{
    protected static $table = 'extensions';
    protected static $primaryKey = 'id';

    const NAME_MIN = 2;
    const NAME_MAX = 35;

    /**
     * All extensions, optionally without the `code` column.
     *
     * @param int $withCode
     */
    public static function allExtensions($withCode = true)
    {
        $rows = self::query()->orderBy('id', 'ASC')->get();
        if (!$withCode) {
            foreach ($rows as $i => $row) {
                unset($rows[$i]['code']);
            }
        }
        return $rows;
    }

    /**
     * All enabled extensions.
     */
    public static function allEnabled()
    {
        return self::query()->where('enabled', 1)->orderBy('id', 'ASC')->get();
    }

    /**
     * Find an extension by its remote source key (e.g. 'repo/{user}/{repo}/{file}' or 'gist/{id}'), or null.
     *
     * @param string $source
     */
    public static function getBySource($source)
    {
        $row = self::query()->where('source', (string) $source)->first();
        return $row ?: null;
    }

    /**
     * Create an extension after validating its metadata. Returns the new id.
     *
     * @param array $fields
     * @return int
     */
    public static function add(array $fields)
    {
        $name        = isset($fields['name']) ? (string) $fields['name'] : '';
        $description = isset($fields['description']) ? (string) $fields['description'] : '';
        $version     = isset($fields['version']) ? (string) $fields['version'] : '';
        $author      = isset($fields['author']) ? (string) $fields['author'] : '';
        $source      = isset($fields['source']) ? (string) $fields['source'] : 'custom';
        $code        = isset($fields['code']) ? (string) $fields['code'] : '';
        $enabled     = isset($fields['enabled']) ? ($fields['enabled'] ? 1 : 0) : 1;

        self::validate($name, $description, $version, $author);

        return (int) self::create(array(
            'name'        => $name,
            'description' => $description,
            'version'     => $version,
            'author'      => $author,
            'source'      => $source,
            'code'        => $code,
            'enabled'     => $enabled,
        ));
    }

    /**
     * Update an extension's editable fields. Validates metadata.
     *
     * @param int $id
     * @param array $fields
     * @throws HttpException
     * @return bool
     */
    public static function edit($id, array $fields)
    {
        $current = self::find((int) $id);
        if (!$current) {
            throw new HttpException(404, 'Extension not found');
        }

        $data = array();
        foreach (array('name', 'description', 'version', 'author', 'code') as $key) {
            if (array_key_exists($key, $fields)) {
                $data[$key] = (string) $fields[$key];
            }
        }
        if (array_key_exists('enabled', $fields)) {
            $data['enabled'] = $fields['enabled'] ? 1 : 0;
        }

        if (empty($data)) {
            return true;
        }

        self::validate(
            isset($data['name']) ? $data['name'] : $current['name'],
            isset($data['description']) ? $data['description'] : $current['description'],
            isset($data['version']) ? $data['version'] : $current['version'],
            isset($data['author']) ? $data['author'] : $current['author']
        );

        self::query()->where('id', (int) $id)->update($data);
        return true;
    }

    /**
     * Toggle the enabled flag (returns the new state).
     *
     * @param int $id
     * @throws HttpException
     */
    public static function toggle($id)
    {
        $row = self::find((int) $id);
        if (!$row) {
            throw new HttpException(404, 'Extension not found');
        }
        $new = ((int) $row['enabled'] === 1) ? 0 : 1;
        self::query()->where('id', (int) $id)->update(array('enabled' => $new));
        return $new;
    }

    /**
     * Validate extension metadata.
     *
     * @param string $name
     * @param string $description
     * @param string $version
     * @param string $author
     * @throws HttpException
     */
    private static function validate($name, $description, $version, $author)
    {
        if (strlen($name) < self::NAME_MIN || strlen($name) > self::NAME_MAX) {
            throw new HttpException(422, 'Name must be between 2 and 35 characters');
        }
        if (strlen($description) > 250) {
            throw new HttpException(422, 'Description must be less than 250 characters');
        }
        if (strlen($author) > 50) {
            throw new HttpException(422, 'Author must be less than 50 characters');
        }
        if (strlen($version) > 15) {
            throw new HttpException(422, 'Version must be less than 15 characters');
        }
    }

}
