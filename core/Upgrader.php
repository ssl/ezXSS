<?php
class Upgrader
{
    /**
     * The migrations-table marker that makes a completed upgrade a no-op.
     */
    const MARKER = 'upgrade_4.3_to_5.0';

    /**
     * Legacy numeric rank that maps to the 5.0 admin role (all others -> user).
     */
    const LEGACY_ADMIN_RANK = 7;

    /**
     * Whether a legacy schema is present.
     *
     * @return bool
     */
    public static function isLegacy()
    {
        try {
            if (self::columnExists('users', 'rank')) {
                return true;
            }
            if (self::columnExists('payloads', 'pages') || self::columnExists('payloads', 'blacklist')) {
                return true;
            }
            $version = self::rawSetting('version');
            if ($version !== null && strpos((string) $version, '4') === 0) {
                return true;
            }
        } catch (Throwable $e) {
            return false;
        }
        return false;
    }

    /**
     * Whether the instance needs an update: a legacy schema, a stored.
     *
     * @param bool $legacy
     * @return bool
     */
    public static function needsUpgrade($legacy = null)
    {
        try {
            if ($legacy === null ? self::isLegacy() : $legacy) {
                return true;
            }
            $stored = self::rawSetting('version');
            $code = (string) Config::get('VERSION', '5.0');
            if ($stored !== null && (string) $stored !== $code) {
                return true;
            }
            if (count(self::pendingMigrations()) > 0) {
                return true;
            }
        } catch (Throwable $e) {
            return false;
        }
        return false;
    }

    /**
     * Basenames of migration files not yet applied.
     *
     * @return array
     */
    public static function pendingMigrations()
    {
        try {
            $migrator = new Migrator();
            return $migrator->pending();
        } catch (Throwable $e) {
            return array();
        }
    }

    /**
     * The stored settings.version string, or null when unavailable.
     */
    public static function storedVersion()
    {
        return self::rawSetting('version');
    }

    /**
     * Whether the data migration has already been recorded.
     *
     * @return bool
     */
    public static function isUpgraded()
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT COUNT(*) FROM `migrations` WHERE `filename` = ?");
            $stmt->execute(array(self::MARKER));
            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Run the idempotent in-place upgrade and return a summary.
     *
     * @return array
     */
    public static function run()
    {
        $db = Database::getInstance();

        if (self::isUpgraded()) {
            return array(
                'upgraded'    => false,
                'already_done' => true,
                'message'     => 'Database already migrated to 5.0.',
                'version'     => (string) Config::get('VERSION', '5.0'),
            );
        }

        $migrated = Migrator::run();

        self::transformReports($db);
        self::transformReportsData($db);
        self::transformSessions($db);
        self::transformSessionsData($db);
        self::transformPayloads($db);
        self::transformUsers($db);
        self::transformLogs($db);
        self::transformConsole($db);
        self::transformExtensions($db);
        self::transformAlerts($db);
        self::transformSettings($db);

        $ins = $db->prepare("INSERT IGNORE INTO `settings` (`setting`, `value`) VALUES (?, ?)");
        foreach (InstallController::defaultSettings() as $setting => $value) {
            $ins->execute(array($setting, (string) $value));
        }
        $version = (string) Config::get('VERSION', '5.0');
        self::upsertSetting($db, 'version', $version);

        $stmt = $db->prepare("INSERT IGNORE INTO `migrations` (`filename`) VALUES (?)");
        $stmt->execute(array(self::MARKER));

        return array(
            'upgraded'  => true,
            'message'   => 'Migrated legacy ezXSS data into the 5.0 schema in place.',
            'migrated'  => $migrated,
            'version'   => $version,
        );
    }

    /**
     * reports (big): user-agent -> useragent, time -> created_at, drop shareid
     * @param object $db
     */
    private static function transformReports($db)
    {
        if (!self::tableExists('reports')) {
            return;
        }
        if (self::columnExists('reports', 'user-agent') && !self::columnExists('reports', 'useragent')) {
            $db->exec("ALTER TABLE `reports` CHANGE `user-agent` `useragent` VARCHAR(500) DEFAULT NULL");
        }
        if (self::columnExists('reports', 'time') && !self::columnExists('reports', 'created_at')) {
            $db->exec("ALTER TABLE `reports` CHANGE `time` `created_at` INT UNSIGNED DEFAULT NULL");
        }
        if (self::columnExists('reports', 'shareid')) {
            $db->exec("ALTER TABLE `reports` DROP COLUMN `shareid`");
        }
        if (!self::columnExists('reports', 'meta')) {
            $db->exec("ALTER TABLE `reports` ADD COLUMN `meta` JSON DEFAULT NULL");
        }
        $db->exec("ALTER TABLE `reports` MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
        $db->exec("ALTER TABLE `reports` MODIFY `archive` TINYINT(1) NOT NULL DEFAULT 0");
        self::ensureIndex($db, 'reports', 'idx_reports_created', '`created_at`');
    }

    /**
     * reports_data (big): reportid -> report_id as a metadata-only rename (the
     * @param object $db
     */
    private static function transformReportsData($db)
    {
        if (!self::tableExists('reports_data')) {
            return;
        }
        if (self::columnExists('reports_data', 'reportid') && !self::columnExists('reports_data', 'report_id')) {

            $db->exec("ALTER TABLE `reports_data` CHANGE `reportid` `report_id` INT UNSIGNED NOT NULL");
        }

        $db->exec("ALTER TABLE `reports_data` MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
        if (!self::indexExists('reports_data', 'reportid')) {
            self::ensureIndex($db, 'reports_data', 'idx_reports_data_report', '`report_id`');
        }
    }

    /**
     * sessions (big): clientid -> client_id, time -> created_at, user-agent ->
     * @param object $db
     */
    private static function transformSessions($db)
    {
        if (!self::tableExists('sessions')) {
            return;
        }
        if (self::columnExists('sessions', 'clientid') && !self::columnExists('sessions', 'client_id')) {
            $db->exec("ALTER TABLE `sessions` CHANGE `clientid` `client_id` VARCHAR(50) NOT NULL");
        }
        if (self::columnExists('sessions', 'time') && !self::columnExists('sessions', 'created_at')) {
            $db->exec("ALTER TABLE `sessions` CHANGE `time` `created_at` INT UNSIGNED DEFAULT NULL");
        }
        if (self::columnExists('sessions', 'user-agent') && !self::columnExists('sessions', 'useragent')) {
            $db->exec("ALTER TABLE `sessions` CHANGE `user-agent` `useragent` VARCHAR(500) DEFAULT NULL");
        }
        if (self::columnExists('sessions', 'origin')) {
            $db->exec("ALTER TABLE `sessions` MODIFY `origin` VARCHAR(500) DEFAULT NULL");
        }
        if (!self::columnExists('sessions', 'updated_at')) {
            $db->exec("ALTER TABLE `sessions` ADD COLUMN `updated_at` INT UNSIGNED NULL AFTER `created_at`");
            $db->exec("UPDATE `sessions` SET `updated_at` = `created_at` WHERE `updated_at` IS NULL");
        }

        foreach (array('dom', 'localstorage', 'sessionstorage', 'console') as $dead) {
            if (self::columnExists('sessions', $dead)) {
                $db->exec("ALTER TABLE `sessions` DROP COLUMN `{$dead}`");
            }
        }

        if (self::columnExists('sessions', 'payload')) {
            $distinct = $db->query("SELECT DISTINCT `payload` FROM `sessions`")->fetchAll(PDO::FETCH_COLUMN);
            $upd = $db->prepare("UPDATE `sessions` SET `payload` = ? WHERE `payload` = ?");
            foreach ($distinct as $old) {
                if ($old === null) {
                    continue;
                }
                $new = PayloadBuilder::normalizeIdentifier($old);
                if ((string) $new !== (string) $old) {
                    $upd->execute(array($new, $old));
                }
            }
        }

        $db->exec("ALTER TABLE `sessions` MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
        $db->exec("ALTER TABLE `sessions` MODIFY `archive` TINYINT(1) NOT NULL DEFAULT 0");
        self::ensureIndex($db, 'sessions', 'idx_sessions_updated', '`updated_at`');
    }

    /**
     * sessions_data (big): sessionid -> session_id as a metadata-only rename
     * @param object $db
     */
    private static function transformSessionsData($db)
    {
        if (!self::tableExists('sessions_data')) {
            return;
        }
        if (self::columnExists('sessions_data', 'sessionid') && !self::columnExists('sessions_data', 'session_id')) {

            $db->exec("ALTER TABLE `sessions_data` CHANGE `sessionid` `session_id` INT UNSIGNED NOT NULL");
        }
        $db->exec("ALTER TABLE `sessions_data` MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
        if (!self::indexExists('sessions_data', 'sessionid')) {
            self::ensureIndex($db, 'sessions_data', 'idx_sessions_data_session', '`session_id`');
        }
    }

    /**
     * payloads (tiny): payload -> identifier; add the 5.0-only columns; a PHP
     * @param object $db
     */
    private static function transformPayloads($db)
    {
        if (!self::tableExists('payloads')) {
            return;
        }
        if (self::columnExists('payloads', 'payload') && !self::columnExists('payloads', 'identifier')) {
            $db->exec("ALTER TABLE `payloads` CHANGE `payload` `identifier` VARCHAR(500) NOT NULL");
        }
        if (!self::columnExists('payloads', 'type')) {
            $db->exec("ALTER TABLE `payloads` ADD COLUMN `type` VARCHAR(32) NOT NULL DEFAULT 'default'");
        }
        if (!self::columnExists('payloads', 'beacon')) {
            $db->exec("ALTER TABLE `payloads` ADD COLUMN `beacon` TINYINT(1) NOT NULL DEFAULT 0 AFTER `spider`");
        }
        if (!self::columnExists('payloads', 'csp_filter')) {
            $db->exec("ALTER TABLE `payloads` ADD COLUMN `csp_filter` VARCHAR(255) DEFAULT NULL");
        }
        if (!self::columnExists('payloads', 'meta')) {
            $db->exec("ALTER TABLE `payloads` ADD COLUMN `meta` JSON DEFAULT NULL");
        }
        if (!self::columnExists('payloads', 'created_at')) {
            $db->exec("ALTER TABLE `payloads` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
        }

        $hasCollect = self::tableExists('payload_collect');
        if ($hasCollect && !self::columnExists('payload_collect', 'collect_csp')) {
            $db->exec("ALTER TABLE `payload_collect` ADD COLUMN `collect_csp` TINYINT(1) NOT NULL DEFAULT 1");
        }
        $rows = $db->query("SELECT * FROM `payloads`")->fetchAll(PDO::FETCH_ASSOC);

        $insPage = $db->prepare("INSERT INTO `payload_pages` (`payload_id`, `path`) VALUES (?, ?)");
        $delPages = $db->prepare("DELETE FROM `payload_pages` WHERE `payload_id` = ?");
        $insFilter = $db->prepare("INSERT INTO `payload_filters` (`payload_id`, `domain`, `type`) VALUES (?, ?, ?)");
        $delFilters = $db->prepare("DELETE FROM `payload_filters` WHERE `payload_id` = ?");
        $insCollect = $db->prepare(
            "INSERT IGNORE INTO `payload_collect`
                (`payload_id`, `collect_uri`, `collect_ip`, `collect_referer`, `collect_useragent`,
                 `collect_cookies`, `collect_localstorage`, `collect_sessionstorage`, `collect_dom`,
                 `collect_screenshot`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        foreach ($rows as $r) {
            $id = (int) $r['id'];

            $delPages->execute(array($id));
            foreach (self::splitTilde(isset($r['pages']) ? $r['pages'] : '') as $path) {
                $insPage->execute(array($id, $path));
            }
            $delFilters->execute(array($id));
            foreach (self::splitTilde(isset($r['blacklist']) ? $r['blacklist'] : '') as $domain) {
                $insFilter->execute(array($id, $domain, 'deny'));
            }
            foreach (self::splitTilde(isset($r['whitelist']) ? $r['whitelist'] : '') as $domain) {
                $insFilter->execute(array($id, $domain, 'allow'));
            }

            if ($hasCollect) {
                $insCollect->execute(array(
                    $id,
                    self::flag($r, 'collect_uri', 1),
                    self::flag($r, 'collect_ip', 1),
                    self::flag($r, 'collect_referer', 1),
                    self::flag($r, 'collect_user-agent', 1),
                    self::flag($r, 'collect_cookies', 1),
                    self::flag($r, 'collect_localstorage', 1),
                    self::flag($r, 'collect_sessionstorage', 1),
                    self::flag($r, 'collect_dom', 1),
                    self::flag($r, 'collect_screenshot', 0),
                ));
            }
        }

        $fallbackId = (int) $db->query("SELECT `id` FROM `payloads` WHERE `identifier` = 'Fallback (default)' LIMIT 1")->fetchColumn();
        if ($fallbackId > 0) {
            $db->exec("DELETE FROM `payload_collect` WHERE `payload_id` = {$fallbackId}");
            $db->exec("DELETE FROM `payload_pages` WHERE `payload_id` = {$fallbackId}");
            $db->exec("DELETE FROM `payload_filters` WHERE `payload_id` = {$fallbackId}");
            $db->exec("DELETE FROM `user_access` WHERE `for_table` = 'payloads' AND `for_id` = {$fallbackId}");
            $db->exec("DELETE FROM `payloads` WHERE `id` = {$fallbackId}");
        }

        $legacyCols = array(
            'pages', 'blacklist', 'whitelist',
            'collect_uri', 'collect_ip', 'collect_referer', 'collect_user-agent',
            'collect_cookies', 'collect_localstorage', 'collect_sessionstorage',
            'collect_dom', 'collect_origin', 'collect_screenshot',
        );
        foreach ($legacyCols as $col) {
            if (self::columnExists('payloads', $col)) {
                $db->exec("ALTER TABLE `payloads` DROP COLUMN `{$col}`");
            }
        }

        $db->exec("ALTER TABLE `payloads` MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
        $db->exec("ALTER TABLE `payloads` MODIFY `user_id` INT UNSIGNED NOT NULL DEFAULT 0");
        $db->exec("ALTER TABLE `payloads` MODIFY `spider` TINYINT(1) NOT NULL DEFAULT 0");
        self::ensureUniqueIndex($db, 'payloads', 'uq_payloads_identifier', '`identifier`');
        self::ensureIndex($db, 'payloads', 'idx_payloads_user', '`user_id`');
    }

    /**
     * users (tiny): add the 5.0-only columns; widen password; map rank -> role;
     * @param object $db
     */
    private static function transformUsers($db)
    {
        if (!self::tableExists('users')) {
            return;
        }
        if (!self::columnExists('users', 'role')) {
            $db->exec("ALTER TABLE `users` ADD COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'user'");
        }
        if (!self::columnExists('users', 'permissions')) {
            $db->exec("ALTER TABLE `users` ADD COLUMN `permissions` JSON DEFAULT NULL");
        }
        if (!self::columnExists('users', 'twofa_secret')) {
            $db->exec("ALTER TABLE `users` ADD COLUMN `twofa_secret` VARCHAR(64) DEFAULT NULL");
        }
        if (!self::columnExists('users', 'created_at')) {
            $db->exec("ALTER TABLE `users` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
        }
        if (!self::columnExists('users', 'banned')) {
            $db->exec("ALTER TABLE `users` ADD COLUMN `banned` TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (!self::columnExists('users', 'github')) {
            $db->exec("ALTER TABLE `users` ADD COLUMN `github` VARCHAR(39) DEFAULT NULL");
        }
        if (self::columnExists('users', 'password')) {
            $db->exec("ALTER TABLE `users` MODIFY `password` VARCHAR(255) NOT NULL");
        }
        if (self::columnExists('users', 'notepad')) {

            $db->exec("ALTER TABLE `users` MODIFY `notepad` TEXT NULL");
        }
        if (self::columnExists('users', 'rank')) {
            $db->exec(
                "UPDATE `users` SET `role` = CASE
                    WHEN `rank` = " . self::LEGACY_ADMIN_RANK . " THEN 'admin'
                    ELSE 'user' END"
            );

            $db->exec("UPDATE `users` SET `banned` = 1 WHERE `rank` = 0");
        }
        if (self::columnExists('users', 'secret')) {
            $db->exec("UPDATE `users` SET `twofa_secret` = NULLIF(TRIM(`secret`), '')");
        }
        foreach (array('rank', 'secret', 'row1', 'row2') as $col) {
            if (self::columnExists('users', $col)) {
                $db->exec("ALTER TABLE `users` DROP COLUMN `{$col}`");
            }
        }
        $db->exec("ALTER TABLE `users` MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
        self::ensureUniqueIndex($db, 'users', 'uq_users_username', '`username`');
    }

    /**
     * logs (can be large): time -> created_at, add type, ensure type index.
     * @param object $db
     */
    private static function transformLogs($db)
    {
        if (!self::tableExists('logs')) {
            return;
        }
        if (self::columnExists('logs', 'time') && !self::columnExists('logs', 'created_at')) {
            $db->exec("ALTER TABLE `logs` CHANGE `time` `created_at` INT UNSIGNED NOT NULL");
        }
        if (!self::columnExists('logs', 'type')) {
            $db->exec("ALTER TABLE `logs` ADD COLUMN `type` VARCHAR(32) NOT NULL DEFAULT ''");
        }
        $db->exec("ALTER TABLE `logs` MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
        $db->exec("ALTER TABLE `logs` MODIFY `user_id` INT UNSIGNED NOT NULL DEFAULT 0");
        $db->exec("ALTER TABLE `logs` MODIFY `ip` VARCHAR(100) NOT NULL DEFAULT ''");
        self::ensureIndex($db, 'logs', 'idx_logs_type', '`type`');
    }

    /**
     * console: clientid -> client_id, executed decimal -> tinyint, ensure index.
     * @param object $db
     */
    private static function transformConsole($db)
    {
        if (!self::tableExists('console')) {
            return;
        }
        if (self::columnExists('console', 'clientid') && !self::columnExists('console', 'client_id')) {
            $db->exec("ALTER TABLE `console` CHANGE `clientid` `client_id` VARCHAR(50) NOT NULL");
        }
        if (self::columnExists('console', 'executed')) {
            $db->exec("ALTER TABLE `console` MODIFY `executed` TINYINT(1) NOT NULL DEFAULT 0");
        }
        $db->exec("ALTER TABLE `console` MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
        self::ensureIndex($db, 'console', 'idx_console_client', '`client_id`');
    }

    /**
     * extensions: no column change (legacy 4.3 has no `fields`); normalize the
     * @param object $db
     */
    private static function transformExtensions($db)
    {
        if (!self::tableExists('extensions')) {
            return;
        }
        if (self::columnExists('extensions', 'enabled')) {
            $db->exec("ALTER TABLE `extensions` MODIFY `enabled` TINYINT(1) NOT NULL DEFAULT 1");
        }
        $db->exec("ALTER TABLE `extensions` MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
        self::ensureIndex($db, 'extensions', 'idx_extensions_source', '`source`');
        self::ensureIndex($db, 'extensions', 'idx_extensions_enabled', '`enabled`');
    }

    /**
     * alerts: shape already matches 5.0; only ensure the indexes exist.
     * @param object $db
     */
    private static function transformAlerts($db)
    {
        if (!self::tableExists('alerts')) {
            return;
        }
        $db->exec("ALTER TABLE `alerts` MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
        $db->exec("ALTER TABLE `alerts` MODIFY `user_id` INT UNSIGNED NOT NULL DEFAULT 0");
        $db->exec("ALTER TABLE `alerts` MODIFY `method_id` INT UNSIGNED NOT NULL");
        self::ensureIndex($db, 'alerts', 'idx_alerts_user', '`user_id`');
        self::ensureIndex($db, 'alerts', 'idx_alerts_method', '`method_id`');
        self::ensureIndex($db, 'alerts', 'idx_alerts_enabled', '`enabled`');
    }

    /**
     * settings: remap collect_user-agent -> collect_useragent, widen the setting
     * @param object $db
     */
    private static function transformSettings($db)
    {
        if (!self::tableExists('settings')) {
            return;
        }
        $db->exec("UPDATE `settings` SET `setting` = 'collect_useragent' WHERE `setting` = 'collect_user-agent'");
        if (self::columnExists('settings', 'setting')) {
            $db->exec("ALTER TABLE `settings` MODIFY `setting` VARCHAR(191) NOT NULL");
        }
        $db->exec("ALTER TABLE `settings` MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
        self::ensureUniqueIndex($db, 'settings', 'uq_settings_setting', '`setting`');
    }

    /**
     * Split a `~`-joined string into trimmed, non-empty parts.
     *
     * @param string $str
     * @return array
     */
    public static function splitTilde($str)
    {
        if ($str === null) {
            return array();
        }
        $str = (string) $str;
        if (trim($str) === '') {
            return array();
        }
        $out = array();
        foreach (explode('~', $str) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $out[] = $part;
            }
        }
        return $out;
    }

    /**
     * Read a tinyint-style flag column from a legacy row, defaulting when absent.
     *
     * @param array $row
     * @param string $column
     * @param string $default
     * @return bool|int
     */
    private static function flag($row, $column, $default)
    {
        if (!array_key_exists($column, $row) || $row[$column] === null) {
            return (int) $default;
        }
        return ((int) $row[$column]) === 1 ? 1 : 0;
    }

    /**
     * Add a non-unique index when it is missing.
     *
     * @param object $db
     * @param string $table
     * @param int $index
     * @param array
     */
    private static function ensureIndex($db, $table, $index, $columns)
    {
        if (!self::tableExists($table) || self::indexExists($table, $index)) {
            return;
        }
        $db->exec("ALTER TABLE `{$table}` ADD KEY `{$index}` ({$columns})");
    }

    /**
     * Add a unique index when it is missing.
     *
     * @param object $db
     * @param string $table
     * @param int $index
     * @param array
     */
    private static function ensureUniqueIndex($db, $table, $index, $columns)
    {
        if (!self::tableExists($table) || self::indexExists($table, $index)) {
            return;
        }
        $db->exec("ALTER TABLE `{$table}` ADD UNIQUE KEY `{$index}` ({$columns})");
    }

    /**
     * Upsert a single settings row (value wins over any existing).
     *
     * @param object $db
     * @param string $setting
     * @param array
     */
    private static function upsertSetting($db, $setting, $value)
    {
        $stmt = $db->prepare(
            "INSERT INTO `settings` (`setting`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
        );
        $stmt->execute(array($setting, (string) $value));
    }

    /**
     * Read a raw settings value directly (works on legacy and 5.0 shapes).
     *
     * @param string $setting
     * @return string|null
     */
    private static function rawSetting($setting)
    {
        $db = Database::getInstance();
        try {
            $stmt = $db->prepare("SELECT `value` FROM `settings` WHERE `setting` = ? LIMIT 1");
            $stmt->execute(array($setting));
            $value = $stmt->fetchColumn();
            return ($value === false) ? null : (string) $value;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Whether a table exists in the current database.
     *
     * @param string $table
     * @return int
     */
    private static function tableExists($table)
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?"
        );
        $stmt->execute(array($table));
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Whether a column exists on a table in the current database.
     *
     * @param string $table
     * @param string $column
     * @return int
     */
    private static function columnExists($table, $column)
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?"
        );
        $stmt->execute(array($table, $column));
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Whether an index exists on a table in the current database.
     *
     * @param string $table
     * @param int $index
     * @return int
     */
    private static function indexExists($table, $index)
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?"
        );
        $stmt->execute(array($table, $index));
        return (int) $stmt->fetchColumn() > 0;
    }
}
