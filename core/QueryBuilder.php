<?php
class QueryBuilder
{
    private $table;
    private $primaryKey;
    private $select = '*';
    private $where = array();
    private $whereParams = array();
    private $orderBy = '';
    private $limit = '';
    private $joins = array();
    private $groupBy = '';
    private $originalTable;

    public function __construct($table, $primaryKey)
    {
        if (!empty($table)) {
            Model::isSafe($table);
        }
        Model::isSafe($primaryKey);

        $this->table = $table;
        $this->originalTable = $table;
        $this->primaryKey = $primaryKey;
    }

    /**
     * Select specific fields.
     *
     * @param array $fields
     * @return static
     */
    public function select($fields)
    {
        $this->select = is_array($fields) ? implode(', ', $fields) : $fields;
        return $this;
    }

    /**
     * Add a WHERE clause (defaults operator to "=").
     *
     * @param string $field
     * @param string $operator
     * @param string $value
     * @return static
     */
    public function where($field, $operator, $value = null)
    {
        if ($value === null && func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        Model::isSafe($field);
        Model::isSafe($operator, 'operator');

        if ($value === null && $operator === '=') {
            $this->where[] = "$field IS NULL";
        } elseif ($value === null && $operator === '!=') {
            $this->where[] = "$field IS NOT NULL";
        } else {
            $this->where[] = "$field $operator ?";
            $this->whereParams[] = $value;
        }
        return $this;
    }

    /**
     * Add a raw WHERE fragment (caller-built, parameterized). Values bind in order.
     *
     * @param string $sql
     * @param array $params
     * @return static
     */
    public function whereRaw($sql, array $params = array())
    {
        $this->where[] = (string) $sql;
        foreach ($params as $param) {
            $this->whereParams[] = $param;
        }
        return $this;
    }

    /**
     * Change the FROM table (supports "table alias").
     *
     * @param string $table
     * @return static
     */
    public function from($table)
    {
        Model::isSafe($table, 'extended');
        $this->table = $table;
        $parts = preg_split('/\s+/', trim($table));
        $this->originalTable = $parts[0];
        return $this;
    }

    /**
     * Add a JOIN clause.
     *
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     * @param string $type
     * @return static
     */
    public function join($table, $first, $operator, $second, $type = 'INNER')
    {
        Model::isSafe($table, 'extended');
        Model::isSafe($first);
        Model::isSafe($operator, 'operator');
        Model::isSafe($second);
        Model::isSafe($type, 'type');

        $this->joins[] = "$type JOIN $table ON $first $operator $second";
        return $this;
    }

    /**
     * Add an ORDER BY clause.
     *
     * @param string $field
     * @param string $direction
     * @return static
     */
    public function orderBy($field, $direction = 'ASC')
    {
        Model::isSafe($field, 'extended');
        Model::isSafe($direction, 'direction');
        $this->orderBy = "ORDER BY $field $direction";
        return $this;
    }

    /**
     * Add a GROUP BY clause.
     *
     * @param string $field
     * @return static
     */
    public function groupBy($field)
    {
        Model::isSafe($field, 'extended');
        $this->groupBy = "GROUP BY $field";
        return $this;
    }

    /**
     * Add a LIMIT (and optional OFFSET) clause.
     *
     * @param int $limit
     * @param int $offset
     * @throws InvalidArgumentException
     * @return static
     */
    public function limit($limit, $offset = null)
    {
        if (!is_numeric($limit) || ($offset !== null && !is_numeric($offset))) {
            throw new InvalidArgumentException('Limit and offset must be numeric');
        }
        if ($offset !== null) {
            $this->limit = "LIMIT $offset, $limit";
        } else {
            $this->limit = "LIMIT $limit";
        }
        return $this;
    }

    /**
     * Execute and return all rows.
     */
    public function get()
    {
        $stmt = Database::getInstance()->prepare($this->toSelectSql());
        $stmt->execute($this->whereParams);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Execute and return the first row.
     */
    public function first()
    {
        $this->limit(1);
        $stmt = Database::getInstance()->prepare($this->toSelectSql());
        $stmt->execute($this->whereParams);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Execute and return the row count.
     *
     * @return int
     */
    public function count()
    {
        $stmt = Database::getInstance()->prepare($this->toCountSql());
        $stmt->execute($this->whereParams);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $result['count'];
    }

    /**
     * Insert a record, returning the new id.
     *
     * @param array $data
     * @throws InvalidArgumentException
     */
    public function insert($data)
    {
        $sql = $this->toInsertSql($data);
        try {
            $stmt = Database::getInstance()->prepare($sql);
            $stmt->execute(array_values($data));
            return Database::getInstance()->lastInsertId();
        } catch (PDOException $e) {
            throw new InvalidArgumentException(Model::parseConstraintError($e));
        }
    }

    /**
     * Update matching records.
     *
     * @param array $data
     * @throws InvalidArgumentException
     */
    public function update($data)
    {
        $sql = $this->toUpdateSql($data);
        $params = array_merge(array_values($data), $this->whereParams);
        try {
            $stmt = Database::getInstance()->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            throw new InvalidArgumentException(Model::parseConstraintError($e));
        }
    }

    /**
     * Delete matching records.
     */
    public function delete()
    {
        $stmt = Database::getInstance()->prepare($this->toDeleteSql());
        return $stmt->execute($this->whereParams);
    }

    /**
     * Bound WHERE parameters (in order). Useful for testing.
     */
    public function getBindings()
    {
        return $this->whereParams;
    }

    /**
     * Build the SELECT SQL string.
     */
    public function toSelectSql()
    {
        $sql = "SELECT {$this->select} FROM {$this->table}";

        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        $sql = $this->buildWhere($sql);

        if (!empty($this->groupBy)) {
            $sql .= ' ' . $this->groupBy;
        }
        if (!empty($this->orderBy)) {
            $sql .= ' ' . $this->orderBy;
        }
        if (!empty($this->limit)) {
            $sql .= ' ' . $this->limit;
        }

        return $sql;
    }

    /**
     * Build the COUNT SQL string. Grouped queries count the number of groups.
     *
     * @return string
     */
    public function toCountSql()
    {
        if (!empty($this->groupBy)) {
            $inner = "SELECT 1 FROM {$this->table}";
            if (!empty($this->joins)) {
                $inner .= ' ' . implode(' ', $this->joins);
            }
            $inner = $this->buildWhere($inner);
            $inner .= ' ' . $this->groupBy;
            return "SELECT COUNT(*) as count FROM ({$inner}) as ezcount";
        }

        $original = $this->select;
        $this->select = 'COUNT(*) as count';
        $sql = "SELECT {$this->select} FROM {$this->table}";

        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        $sql = $this->buildWhere($sql);

        $this->select = $original;
        return $sql;
    }

    /**
     * Build the INSERT SQL string (validates field identifiers).
     *
     * @param array $data
     * @return string
     */
    public function toInsertSql($data)
    {
        $fields = array_keys($data);
        foreach ($fields as $field) {
            Model::isSafe($field);
        }
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        return "INSERT INTO `{$this->table}` (`" . implode('`, `', $fields) . "`) VALUES ($placeholders)";
    }

    /**
     * Build the UPDATE SQL string (validates field identifiers).
     *
     * @param array $data
     */
    public function toUpdateSql($data)
    {
        $fields = array_keys($data);
        foreach ($fields as $field) {
            Model::isSafe($field);
        }
        $sql = "UPDATE `{$this->table}` SET `" . implode('` = ?, `', $fields) . '` = ?';
        return $this->buildWhere($sql);
    }

    /**
     * Build the DELETE SQL string.
     */
    public function toDeleteSql()
    {
        return $this->buildWhere("DELETE FROM `{$this->table}`");
    }

    /**
     * Append the WHERE clause to a SQL string.
     *
     * @param string $sql
     */
    private function buildWhere($sql)
    {
        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }
        return $sql;
    }

    /**
     * Escape LIKE wildcards so a user's %/_ match literally.
     *
     * @param string $term
     * @return string
     */
    public static function escapeLike($term)
    {
        return str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), (string) $term);
    }

    /**
     * Clamp limit/offset from an options map to a sane page window.
     *
     * @param array $opts
     * @param int $default
     * @param int $max
     * @return array
     */
    public static function page(array $opts, $default = 25, $max = 200)
    {
        $limit = isset($opts['limit']) ? (int) $opts['limit'] : (int) $default;
        $limit = max(1, min((int) $max, $limit));
        $offset = isset($opts['offset']) ? max(0, (int) $opts['offset']) : 0;
        return array('limit' => $limit, 'offset' => $offset);
    }
}
