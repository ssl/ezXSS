<?php
class Migrator
{
    /**
     * The schema file applied on install/update.
     */
    const SCHEMA_FILE = 'schema.sql';

    private $db;
    private $dir;

    public function __construct($dir = null, $db = null)
    {
        $this->dir = $dir !== null ? $dir : (defined('FRAMEWORK_ROOT') ? FRAMEWORK_ROOT : __DIR__ . '/..');
        $this->db = $db;
    }

    /**
     * Resolve the PDO connection lazily.
     */
    private function db()
    {
        if ($this->db === null) {
            $this->db = Database::getInstance();
        }
        return $this->db;
    }

    /**
     * Apply all pending migrations. Returns the list of filenames applied now.
     *
     * @throws HttpException
     */
    public function migrate()
    {
        $this->ensureMigrationsTable();

        $applied = $this->appliedFilenames();
        $files = $this->migrationFiles();
        if (empty($files)) {
            throw new HttpException(500, 'Schema file ' . self::SCHEMA_FILE . ' is missing from the deployment');
        }
        $ran = array();

        foreach ($files as $path) {
            $filename = basename($path);
            if (in_array($filename, $applied, true)) {
                continue;
            }

            $sql = file_get_contents($path);
            if ($sql === false) {
                throw new HttpException(500, "Unable to read migration {$filename}");
            }

            $statements = self::splitStatements($sql);

            try {
                foreach ($statements as $statement) {
                    $this->db()->exec($statement);
                }
                $stmt = $this->db()->prepare("INSERT INTO `migrations` (`filename`) VALUES (?)");
                $stmt->execute(array($filename));
            } catch (Exception $e) {

                error_log('ezXSS: migration ' . $filename . ' failed: ' . $e->getMessage());
                throw new HttpException(500, "Migration {$filename} failed");
            }

            $ran[] = $filename;
        }

        return $ran;
    }

    /**
     * Static convenience wrapper.
     *
     * @param string $dir
     * @param object $db
     */
    public static function run($dir = null, $db = null)
    {
        $migrator = new self($dir, $db);
        return $migrator->migrate();
    }

    /**
     * Whether the schema has been initialised (migrations table present).
     */
    public function isInstalled()
    {
        return $this->tableExists('migrations');
    }

    /**
     * Basenames of migration files that have NOT yet been applied (in order).
     */
    public function pending()
    {
        $files = $this->migrationFiles();
        if (!$this->isInstalled()) {
            return array_map('basename', $files);
        }
        $applied = $this->appliedFilenames();
        $pending = array();
        foreach ($files as $path) {
            $filename = basename($path);
            if (!in_array($filename, $applied, true)) {
                $pending[] = $filename;
            }
        }
        return $pending;
    }

    /**
     * The canonical schema file, when present.
     */
    public function migrationFiles()
    {
        $path = $this->dir . '/' . self::SCHEMA_FILE;
        return is_file($path) ? array($path) : array();
    }

    /**
     * Create the migrations table if it does not yet exist.
     */
    private function ensureMigrationsTable()
    {
        $this->db()->exec(
            "CREATE TABLE IF NOT EXISTS `migrations` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `filename`   VARCHAR(191) NOT NULL,
                `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_migrations_filename` (`filename`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * Already-applied migration filenames.
     *
     * @return bool
     */
    private function appliedFilenames()
    {
        $rows = $this->db()->query("SELECT `filename` FROM `migrations`")->fetchAll(PDO::FETCH_COLUMN);
        return is_array($rows) ? $rows : array();
    }

    /**
     * Check whether a table exists in the current database.
     *
     * @param string $table
     * @return int
     */
    private function tableExists($table)
    {
        $stmt = $this->db()->prepare(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?"
        );
        $stmt->execute(array($table));
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Split a SQL script into individual statements (comment- and quote-aware).
     *
     * @param string $sql
     */
    public static function splitStatements($sql)
    {
        $statements = array();
        $buffer = '';
        $len = strlen($sql);
        $inSingle = false;
        $inDouble = false;
        $inLineComment = false;
        $inBlockComment = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $next = ($i + 1 < $len) ? $sql[$i + 1] : '';

            if ($inLineComment) {
                if ($ch === "\n") {
                    $inLineComment = false;
                    $buffer .= $ch;
                }
                continue;
            }

            if ($inBlockComment) {
                if ($ch === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i++;
                }
                continue;
            }

            if (!$inSingle && !$inDouble) {

                if ($ch === '-' && $next === '-') {
                    $inLineComment = true;
                    $i++;
                    continue;
                }
                if ($ch === '#') {
                    $inLineComment = true;
                    continue;
                }
                if ($ch === '/' && $next === '*') {
                    $inBlockComment = true;
                    $i++;
                    continue;
                }
            }

            if ($ch === "'" && !$inDouble) {

                if ($inSingle && $next === "'") {
                    $buffer .= "''";
                    $i++;
                    continue;
                }
                $inSingle = !$inSingle;
                $buffer .= $ch;
                continue;
            }

            if ($ch === '"' && !$inSingle) {
                if ($inDouble && $next === '"') {
                    $buffer .= '""';
                    $i++;
                    continue;
                }
                $inDouble = !$inDouble;
                $buffer .= $ch;
                continue;
            }

            if ($ch === ';' && !$inSingle && !$inDouble) {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $ch;
        }

        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }
}
