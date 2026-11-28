<?php
class Upgrader
{
    /**
     * migrations-table id for the one-time legacy-to-5.0 migration; its presence makes it a no-op.
     */
    const MARKER = 'upgrade_to_5.0';

    /**
     * Pre-release name of MARKER, still recognised so an already-upgraded install is not re-run.
     */
    const MARKER_LEGACY = 'upgrade_4.3_to_5.0';

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
            if ($version !== null && preg_match('/^[1234]\./', (string) $version)) {
                return true;
            }
            if (self::tableExists('reports') && !self::tableExists('payloads')) {
                return true;
            }
            // A pre-4.0 run keeps its `password` setting until the very end, so a half-done run stays legacy.
            if (self::rawSetting('password') !== null && !self::isUpgraded()) {
                return true;
            }
        } catch (Throwable $e) {
            return false;
        }
        return false;
    }

    /**
     * Whether the instance needs an update: a legacy schema, or a stored version below 5.0.
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
            $stmt = $db->prepare("SELECT COUNT(*) FROM `migrations` WHERE `filename` IN (?, ?)");
            $stmt->execute(array(self::MARKER, self::MARKER_LEGACY));
            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Columns the 5.0 transforms need that a given 4.x release did not yet have,
     * with the type and default that release would have used. Derived from the
     * 4.0 to 4.3 install files; no indexes or keys. `spider` on payloads arrived
     * in 4.3, `archive` on sessions arrived in 4.3, and 4.0 had no sessions table
     * at all (the 5.0 schema creates it fresh, so no bridge column is needed there).
     *
     * @return array
     */
    private static function legacyShape()
    {
        $v43 = array('customjs2' => 'TEXT', 'extensions' => 'VARCHAR(255) DEFAULT NULL');
        return array(
            '4.0' => array(
                'payloads' => array('spider' => 'INT NOT NULL DEFAULT 0', 'persistent' => "TINYINT(1) NOT NULL DEFAULT '0'") + $v43,
            ),
            '4.1' => array(
                'payloads' => array('spider' => 'INT NOT NULL DEFAULT 0') + $v43,
                'sessions' => array('archive' => 'INT DEFAULT 0'),
            ),
            '4.2' => array(
                'payloads' => array('spider' => 'INT NOT NULL DEFAULT 0') + $v43,
                'sessions' => array('archive' => 'INT DEFAULT 0'),
                'reports_data' => array('extra' => 'LONGTEXT'),
            ),
            '4.3' => array(),
        );
    }

    /**
     * Identify the source release from the columns actually present, not from the
     * stored version (the 4.1 install never bumped it). Runs before the schema is
     * applied, so the legacy tables and their distinctive columns are still intact.
     *
     * @return string
     */
    private static function detectLegacyVersion()
    {
        if (self::columnExists('payloads', 'spider') || self::columnExists('payloads', 'customjs2')) {
            return '4.3';
        }
        if (self::columnExists('payloads', 'persistent')) {
            return self::tableExists('sessions_data') ? '4.2' : '4.1';
        }
        return '4.0';
    }

    /**
     * Normalise a pre-4.0 reports/settings to InnoDB/utf8mb4, add payload/archive, dedupe settings.
     *
     * @param object $db
     */
    private static function prepareLegacyPreV4($db)
    {
        foreach (array('reports', 'settings') as $table) {
            if (!self::tableExists($table)) {
                continue;
            }
            $db->exec("ALTER TABLE `{$table}` ENGINE=InnoDB");
            $utf8 = stripos((string) self::tableCollation($table), 'utf8') === 0;
            if (!$utf8) {
                // 1.x-3.1 stored UTF-8 in latin1 columns; relabel via binary so CONVERT won't mojibake it.
                self::relabelToBinary($db, $table);
                foreach (self::binaryColumns($table) as $column) {
                    self::scrubBinaryColumn($db, $table, $column);
                }
            }
            $db->exec("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            if ($utf8) {
                self::repairDoubleEncoding($db, $table);
            }
        }
        if (self::tableExists('reports')) {
            if (!self::columnExists('reports', 'payload')) {
                $db->exec("ALTER TABLE `reports` ADD COLUMN `payload` VARCHAR(255) DEFAULT NULL");
            }
            if (!self::columnExists('reports', 'archive')) {
                $db->exec("ALTER TABLE `reports` ADD COLUMN `archive` TINYINT(1) NOT NULL DEFAULT 0");
            }
        }
        if (self::tableExists('settings')) {
            if (self::columnExists('settings', 'value')) {
                $db->exec("ALTER TABLE `settings` MODIFY `value` TEXT NOT NULL");
            }
            $db->exec(
                "DELETE `s1` FROM `settings` `s1`
                 INNER JOIN `settings` `s2`
                 ON `s1`.`setting` = `s2`.`setting` AND `s1`.`id` > `s2`.`id`"
            );
        }
    }

    /**
     * Create the single admin from a pre-4.0 install's password/username settings, then drop them.
     *
     * @param object $db
     */
    private static function createLegacyAdmin($db)
    {
        if ((int) $db->query("SELECT COUNT(*) FROM `users`")->fetchColumn() > 0) {
            return;
        }
        $password = self::rawSetting('password');
        if ($password === null || $password === '') {
            return;
        }
        $username = self::rawSetting('username');
        $username = ($username !== null && trim($username) !== '') ? substr(trim($username), 0, 25) : 'admin';

        $stmt = $db->prepare("INSERT INTO `users` (`username`, `password`, `role`) VALUES (?, ?, 'admin')");
        $stmt->execute(array($username, $password));
        $db->exec("DELETE FROM `settings` WHERE `setting` IN ('password', 'username')");
    }

    /**
     * Bridge the source shape up to what the transforms expect: add the columns
     * the detected release lacked, each with that release's default. A column that
     * is already present is left untouched, so the step is safe to repeat.
     *
     * @param object $db
     * @param string $version
     */
    private static function ensureLegacyShape($db, $version)
    {
        $shape = self::legacyShape();
        if (!isset($shape[$version])) {
            return;
        }
        foreach ($shape[$version] as $table => $columns) {
            if (!self::tableExists($table)) {
                continue;
            }
            foreach ($columns as $column => $definition) {
                if (!self::columnExists($table, $column)) {
                    $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
                }
            }
        }
    }

    /**
     * The inline blob columns each source table would move into its *_data table.
     *
     * @return array
     */
    private static function inlineBlobColumns()
    {
        return array(
            'reports'  => array('table' => 'reports_data', 'columns' => array('dom', 'screenshot', 'localstorage', 'sessionstorage')),
            'sessions' => array('table' => 'sessions_data', 'columns' => array('dom', 'localstorage', 'sessionstorage', 'console')),
        );
    }

    /**
     * Whether a table has no rows. A table that does not exist counts as empty.
     *
     * @param string $table
     * @return bool
     */
    private static function tableIsEmpty($table)
    {
        if (!self::tableExists($table)) {
            return true;
        }
        return !(bool) Database::getInstance()->query("SELECT EXISTS(SELECT 1 FROM `{$table}` LIMIT 1)")->fetchColumn();
    }

    /**
     * Whether a source table still holds big data inline that must be moved into its *_data table: the
     * inline `dom` column is present and the table has rows, and the target has not received the data
     * yet (absent or empty). Retry-safe and re-run-safe: after the move the inline columns are gone, and
     * an already-split 4.2/4.3 source has a non-empty target, so both read as "no move" on a re-run.
     *
     * @param string $table
     * @param string $dataTable
     * @return bool
     */
    private static function willMoveInline($table, $dataTable)
    {
        return self::tableExists($table)
            && self::columnExists($table, 'dom')
            && !self::tableIsEmpty($table)
            && self::tableIsEmpty($dataTable);
    }

    /**
     * Bytes of inline blob data a pre-split (4.0/4.1) source would copy into the
     * *_data tables: the LENGTH of each inline column that is still on `reports`
     * and `sessions` and really will be copied. Zero when the source is already
     * split (4.2/4.3), so the caller can tell there is nothing to copy.
     *
     * @return float
     */
    private static function inlineDataBytes()
    {
        $db = Database::getInstance();
        $total = 0.0;
        foreach (self::inlineBlobColumns() as $table => $spec) {
            if (!self::willMoveInline($table, $spec['table'])) {
                continue;
            }
            $parts = array();
            foreach ($spec['columns'] as $column) {
                if (self::columnExists($table, $column)) {
                    $parts[] = "COALESCE(LENGTH(`{$column}`), 0)";
                }
            }
            if (!$parts) {
                continue;
            }
            $total += (float) $db->query(
                "SELECT COALESCE(SUM(" . implode(' + ', $parts) . "), 0) FROM `{$table}`"
            )->fetchColumn();
        }
        return $total;
    }

    /**
     * Refuse the inline-data move when the bytes being copied would not fit on
     * disk, unless ?disablechecks=1 is given. The estimate is the size of the
     * inline blob columns being copied (times 1.2 for overhead), not the whole
     * database, and the check is skipped entirely for an already-split source
     * because then no copy is made.
     *
     * @throws Exception
     */
    private static function assertLegacyDiskSpace()
    {
        if (queryParam('disablechecks') === '1') {
            return;
        }
        $bytes = self::inlineDataBytes();
        if ($bytes <= 0) {
            return;
        }
        $needed = $bytes * 1.2;
        $free = (float) disk_free_space('/');
        if ($free > 0 && $needed > $free) {
            throw new Exception(sprintf(
                'Not enough free disk space to move the inline report/session data: need about %d MB, have %d MB.',
                ceil($needed / 1048576),
                ceil($free / 1048576)
            ));
        }
    }

    /**
     * Move a pre-split (4.0/4.1) source's inline blob columns into its 5.0 *_data table, then drop the
     * inline columns. The copy runs only when the data is not already in the target (willMoveInline),
     * so it is safe to repeat and safe after a failed-then-retried run; the inline data is uncompressed,
     * so `compressed` is 0. On an already-split 4.2/4.3 source nothing is copied and only the vestigial
     * inline columns are dropped. When a copy is needed but the target still carries its legacy id column
     * name (report_id/session_id absent), this returns without dropping, so inline data is never dropped
     * before it has been copied; a re-run after the *_data rename then copies and drops.
     *
     * @param object $db
     * @param string $table
     * @param string $dataTable
     * @param string $idColumn
     * @param array  $columns
     */
    private static function moveInlineBlobs($db, $table, $dataTable, $idColumn, $columns)
    {
        if (!self::tableExists($table) || !self::tableExists($dataTable)) {
            return;
        }
        $present = array();
        foreach ($columns as $column) {
            if (self::columnExists($table, $column)) {
                $present[] = $column;
            }
        }
        if (!$present) {
            return;
        }
        if (self::willMoveInline($table, $dataTable)) {
            if (!self::columnExists($dataTable, $idColumn)) {
                return;
            }
            $cols = '`' . implode('`, `', $present) . '`';
            $db->exec(
                "INSERT INTO `{$dataTable}` (`{$idColumn}`, {$cols}, `compressed`)
                 SELECT `id`, {$cols}, 0 FROM `{$table}`"
            );
        }
        $db->exec("ALTER TABLE `{$table}` DROP COLUMN `" . implode('`, DROP COLUMN `', $present) . '`');
    }

    /**
     * Run the idempotent in-place upgrade of any 1.x-4.x database to 5.0 (manual /api/update).
     *
     * @throws Exception
     * @return array
     */
    public static function run()
    {
        @set_time_limit(0);
        $db = Database::getInstance();

        if (self::isUpgraded()) {
            return array(
                'upgraded'    => false,
                'already_done' => true,
                'message'     => 'Database already migrated to 5.0.',
                'version'     => (string) Config::get('VERSION', '5.0'),
            );
        }

        $legacy = self::isLegacy();
        $legacyVersion = null;
        $preV4 = false;
        if ($legacy) {
            // Pre-4.0 (1.x-3.x) kept the admin in a `password` setting and had no users table.
            // That setting outlives Migrator::run() and is cleared only once the admin is created,
            // so it still identifies a pre-4.0 source on a retry where the 5.0 schema (and its
            // payloads table) already exists, unlike the absence of the payloads table.
            $preV4 = self::rawSetting('password') !== null;
            $legacyVersion = self::detectLegacyVersion();
            self::assertLegacyDiskSpace();
            if ($preV4) {
                self::prepareLegacyPreV4($db);
            }
        }

        $migrated = Migrator::run();

        if ($legacy) {
            self::ensureLegacyShape($db, $legacyVersion);
        }

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
        self::alignWithSchema($db);

        if ($preV4) {
            self::createLegacyAdmin($db);
        }

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
     * Bring the reports table to the 5.0 shape: rename user-agent to useragent and time to created_at,
     * drop shareid, add meta, and move any inline blob data into reports_data.
     *
     * @param object $db
     */
    private static function transformReports($db)
    {
        if (!self::tableExists('reports')) {
            return;
        }
        self::moveInlineBlobs(
            $db,
            'reports',
            'reports_data',
            'report_id',
            array('dom', 'screenshot', 'localstorage', 'sessionstorage')
        );
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
        $db->exec("UPDATE `reports` SET `archive` = 0 WHERE `archive` IS NULL");
        $db->exec("ALTER TABLE `reports` MODIFY `archive` TINYINT(1) NOT NULL DEFAULT 0");
        self::ensureIndex($db, 'reports', 'idx_reports_created', '`created_at`');
    }

    /**
     * Bring the reports_data table to the 5.0 shape: rename reportid to report_id and add its index.
     *
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
     * Bring the sessions table to the 5.0 shape: rename clientid, time and user-agent, add updated_at,
     * and move any inline blob data into sessions_data.
     *
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

        self::moveInlineBlobs(
            $db,
            'sessions',
            'sessions_data',
            'session_id',
            array('dom', 'localstorage', 'sessionstorage', 'console')
        );

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
        $db->exec("UPDATE `sessions` SET `archive` = 0 WHERE `archive` IS NULL");
        $db->exec("ALTER TABLE `sessions` MODIFY `archive` TINYINT(1) NOT NULL DEFAULT 0");
        self::ensureIndex($db, 'sessions', 'idx_sessions_updated', '`updated_at`');
    }

    /**
     * Bring the sessions_data table to the 5.0 shape: rename sessionid to session_id and add its index.
     *
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
     * payloads: payload -> identifier, add 5.0 columns, split pages/filters/collect, drop fallback.
     *
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
        $pageCount = $db->prepare("SELECT COUNT(*) FROM `payload_pages` WHERE `payload_id` = ?");
        $insFilter = $db->prepare("INSERT INTO `payload_filters` (`payload_id`, `domain`, `type`) VALUES (?, ?, ?)");
        $filterCount = $db->prepare("SELECT COUNT(*) FROM `payload_filters` WHERE `payload_id` = ?");
        $insCollect = $db->prepare(
            "INSERT IGNORE INTO `payload_collect`
                (`payload_id`, `collect_uri`, `collect_ip`, `collect_referer`, `collect_useragent`,
                 `collect_cookies`, `collect_localstorage`, `collect_sessionstorage`, `collect_dom`,
                 `collect_screenshot`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $db->beginTransaction();

            $pageCount->execute(array($id));
            if ((int) $pageCount->fetchColumn() === 0) {
                foreach (self::splitTilde(isset($r['pages']) ? $r['pages'] : '') as $path) {
                    $insPage->execute(array($id, $path));
                }
            }
            $filterCount->execute(array($id));
            if ((int) $filterCount->fetchColumn() === 0) {
                foreach (self::splitTilde(isset($r['blacklist']) ? $r['blacklist'] : '') as $domain) {
                    $insFilter->execute(array($id, $domain, 'deny'));
                }
                foreach (self::splitTilde(isset($r['whitelist']) ? $r['whitelist'] : '') as $domain) {
                    $insFilter->execute(array($id, $domain, 'allow'));
                }
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
            $db->commit();
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
     * users: add 5.0 columns, widen password, map rank -> role, ban rank 0, drop legacy columns.
     *
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
     * extensions: normalize the enabled flag and ensure the lookup indexes.
     *
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
     * settings: remap collect_user-agent, widen the setting column, add the unique key.
     *
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
     * The tables in schema.sql with their column definitions, keys and table options.
     *
     * @return array
     */
    private static function schemaTables()
    {
        $sql = (string) @file_get_contents(FRAMEWORK_ROOT . '/' . Migrator::SCHEMA_FILE);
        preg_match_all('/CREATE TABLE IF NOT EXISTS `(\w+)` \((.*?)\n\)\s*([^;]*);/s', $sql, $m, PREG_SET_ORDER);
        $tables = array();
        foreach ($m as $t) {
            $cols = array();
            foreach (preg_split('/\n/', $t[2]) as $line) {
                $line = rtrim(trim($line), ',');
                if (preg_match('/^`(\w+)`\s+(.+)$/', $line, $c)) {
                    $cols[$c[1]] = $c[2];
                }
            }
            $tables[$t[1]] = array('body' => $t[2], 'options' => $t[3], 'columns' => $cols);
        }
        return $tables;
    }

    /**
     * Column and index shape of a table from information_schema, keyed for comparison.
     *
     * @param string $table
     * @return array
     */
    private static function liveShape($table)
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT `COLUMN_NAME`, `COLUMN_TYPE`, `IS_NULLABLE`, `COLUMN_DEFAULT`, `EXTRA`, `COLLATION_NAME`
             FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?"
        );
        $stmt->execute(array($table));
        $cols = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cols[$r['COLUMN_NAME']] = array(
                'sig'      => strtolower($r['COLUMN_TYPE']) . '|' . $r['IS_NULLABLE'] . '|' . var_export($r['COLUMN_DEFAULT'], true)
                    . '|' . strtolower($r['EXTRA']) . '|' . $r['COLLATION_NAME'],
                'nullable' => $r['IS_NULLABLE'] === 'YES',
                'default'  => $r['COLUMN_DEFAULT'],
                'type'     => strtolower($r['COLUMN_TYPE']),
                'collation' => $r['COLLATION_NAME'],
            );
        }
        $stmt = $db->prepare(
            "SELECT `INDEX_NAME`, `NON_UNIQUE`, GROUP_CONCAT(CONCAT('`', `COLUMN_NAME`, '`') ORDER BY `SEQ_IN_INDEX`) AS `cols`
             FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ?
             GROUP BY `INDEX_NAME`, `NON_UNIQUE`"
        );
        $stmt->execute(array($table));
        $idx = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if ($r['INDEX_NAME'] !== 'PRIMARY') {
                $idx[$r['INDEX_NAME']] = ((int) $r['NON_UNIQUE'] ? 'KEY' : 'UNIQUE KEY') . ' (' . $r['cols'] . ')';
            }
        }
        $stmt = $db->prepare(
            "SELECT `ENGINE`, `TABLE_COLLATION` FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?"
        );
        $stmt->execute(array($table));
        $tbl = $stmt->fetch(PDO::FETCH_ASSOC);
        return array('columns' => $cols, 'indexes' => $idx, 'engine' => $tbl['ENGINE'], 'collation' => $tbl['TABLE_COLLATION']);
    }

    /**
     * Replace invalid UTF-8 in the rows of a binary column that have it.
     *
     * @param object $db
     * @param string $table
     * @param string $column
     */
    private static function scrubBinaryColumn($db, $table, $column)
    {
        if (!self::columnExists($table, 'id')) {
            return;
        }
        $rows = $db->query(
            "SELECT `id`, `{$column}` FROM `{$table}`
             WHERE `{$column}` IS NOT NULL AND (CONVERT(`{$column}` USING utf8mb4) IS NULL
             OR `{$column}` <> CONVERT(CONVERT(`{$column}` USING utf8mb4) USING binary))"
        )->fetchAll(PDO::FETCH_NUM);
        $upd = $db->prepare("UPDATE `{$table}` SET `{$column}` = ? WHERE `id` = ?");
        foreach ($rows as $row) {
            $upd->execute(array(self::legacyToUtf8($row[1]), $row[0]));
        }
    }

    /**
     * Make legacy bytes valid UTF-8: drop a character cut off at the end, else read them as latin1.
     *
     * @param string $value
     * @return string
     */
    public static function legacyToUtf8($value)
    {
        $value = (string) $value;
        if (preg_match('//u', $value)) {
            return $value;
        }
        $trimmed = preg_replace('/[\xC0-\xF7][\x80-\xBF]{0,2}$/', '', $value);
        if ($trimmed !== $value && preg_match('//u', $trimmed)) {
            return $trimmed;
        }
        $latin = function_exists('mb_convert_encoding') ? mb_convert_encoding($value, 'UTF-8', 'Windows-1252')
            : (string) iconv('Windows-1252', 'UTF-8//IGNORE', $value);
        return utf8Clean($latin);
    }

    /**
     * Undo UTF-8 that a latin1 connection stored a second time in a 3.x utf8 table.
     *
     * @param object $db
     * @param string $table
     */
    private static function repairDoubleEncoding($db, $table)
    {
        if (!self::columnExists($table, 'id')) {
            return;
        }
        $stmt = $db->prepare(
            "SELECT `COLUMN_NAME` FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND `CHARACTER_SET_NAME` IS NOT NULL"
        );
        $stmt->execute(array($table));
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $column) {
            $rows = $db->query("SELECT `id`, `{$column}` FROM `{$table}` WHERE `{$column}` REGEXP '[^ -~\t\r\n]'")
                ->fetchAll(PDO::FETCH_NUM);
            $upd = $db->prepare("UPDATE `{$table}` SET `{$column}` = ? WHERE `id` = ?");
            foreach ($rows as $row) {
                $bytes = self::toLatin1Bytes((string) $row[1]);
                if ($bytes !== null && $bytes !== $row[1] && preg_match('//u', $bytes)) {
                    $upd->execute(array($bytes, $row[0]));
                }
            }
        }
    }

    /**
     * The latin1 (cp1252, as MySQL maps it) bytes of a UTF-8 string, or null when a character has no byte.
     *
     * @param string $value
     * @return string|null
     */
    private static function toLatin1Bytes($value)
    {
        static $cp1252 = array(
            0x20AC => 0x80, 0x201A => 0x82, 0x0192 => 0x83, 0x201E => 0x84, 0x2026 => 0x85, 0x2020 => 0x86,
            0x2021 => 0x87, 0x02C6 => 0x88, 0x2030 => 0x89, 0x0160 => 0x8A, 0x2039 => 0x8B, 0x0152 => 0x8C,
            0x017D => 0x8E, 0x2018 => 0x91, 0x2019 => 0x92, 0x201C => 0x93, 0x201D => 0x94, 0x2022 => 0x95,
            0x2013 => 0x96, 0x2014 => 0x97, 0x02DC => 0x98, 0x2122 => 0x99, 0x0161 => 0x9A, 0x203A => 0x9B,
            0x0153 => 0x9C, 0x017E => 0x9E, 0x0178 => 0x9F,
        );
        $out = '';
        foreach (preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            $cp = self::codepoint($ch);
            if ($cp < 0x80 || ($cp >= 0xA0 && $cp <= 0xFF) || in_array($cp, array(0x81, 0x8D, 0x8F, 0x90, 0x9D), true)) {
                $out .= chr($cp);
            } elseif (isset($cp1252[$cp])) {
                $out .= chr($cp1252[$cp]);
            } else {
                return null;
            }
        }
        return $out;
    }

    /**
     * Code point of one UTF-8 character.
     *
     * @param string $ch
     * @return int
     */
    private static function codepoint($ch)
    {
        $b = array_values(unpack('C*', $ch));
        if ($b[0] < 0x80) {
            return $b[0];
        }
        if ($b[0] < 0xE0) {
            return (($b[0] & 0x1F) << 6) | ($b[1] & 0x3F);
        }
        if ($b[0] < 0xF0) {
            return (($b[0] & 0x0F) << 12) | (($b[1] & 0x3F) << 6) | ($b[2] & 0x3F);
        }
        return (($b[0] & 0x07) << 18) | (($b[1] & 0x3F) << 12) | (($b[2] & 0x3F) << 6) | ($b[3] & 0x3F);
    }

    /**
     * Relabel a latin1 table's text columns as binary, wide enough to hold the same text re-encoded as UTF-8.
     *
     * @param object $db
     * @param string $table
     */
    private static function relabelToBinary($db, $table)
    {
        $stmt = $db->prepare(
            "SELECT `COLUMN_NAME`, `DATA_TYPE`, `CHARACTER_MAXIMUM_LENGTH`, `IS_NULLABLE` FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND `CHARACTER_SET_NAME` IS NOT NULL"
        );
        $stmt->execute(array($table));
        $specs = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $type = strtolower($c['DATA_TYPE']);
            if ($type === 'char' || $type === 'varchar') {
                $bin = 'VARBINARY(' . min(65000, 4 * (int) $c['CHARACTER_MAXIMUM_LENGTH']) . ')';
            } elseif ($type === 'tinytext' || $type === 'text') {
                $bin = 'MEDIUMBLOB';
            } else {
                $bin = 'LONGBLOB';
            }
            $specs[] = "MODIFY `{$c['COLUMN_NAME']}` {$bin} " . ($c['IS_NULLABLE'] === 'YES' ? 'NULL' : 'NOT NULL');
        }
        if (!empty($specs)) {
            $db->exec("ALTER TABLE `{$table}` " . implode(', ', $specs));
        }
    }

    /**
     * The binary/blob columns of a table.
     *
     * @param string $table
     * @return array
     */
    private static function binaryColumns($table)
    {
        $stmt = Database::getInstance()->prepare(
            "SELECT `COLUMN_NAME` FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ?
             AND `DATA_TYPE` IN ('binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob')"
        );
        $stmt->execute(array($table));
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * A table's collation, or null when it does not exist.
     *
     * @param string $table
     * @return string|null
     */
    private static function tableCollation($table)
    {
        $stmt = Database::getInstance()->prepare(
            "SELECT `TABLE_COLLATION` FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?"
        );
        $stmt->execute(array($table));
        $c = $stmt->fetchColumn();
        return $c === false ? null : $c;
    }

    /**
     * SQL literal to backfill NULLs with before a column becomes NOT NULL (MariaDB quotes defaults, MySQL does not).
     *
     * @param object $db
     * @param array $col
     * @return string
     */
    private static function fillValue($db, array $col)
    {
        $d = $col['default'];
        if ($d === null || $d === 'NULL') {
            return preg_match('/int|decimal|float|double/', $col['type']) ? '0' : (preg_match('/date|time/', $col['type']) ? 'NOW()' : "''");
        }
        if (stripos($d, 'current_timestamp') === 0) {
            return 'NOW()';
        }
        if (preg_match("/^'(.*)'$/s", $d, $m)) {
            $d = str_replace("''", "'", $m[1]);
        }
        return $db->quote($d);
    }

    /**
     * Bring every schema.sql table to exactly its schema.sql shape (engine, charset, columns, keys).
     *
     * @param object $db
     */
    private static function alignWithSchema($db)
    {
        foreach (self::schemaTables() as $table => $def) {
            if (!self::tableExists($table)) {
                continue;
            }
            // Build the wanted shape from schema.sql itself in a scratch table, so types compare normalized.
            $probe = '_ezxss_shape_' . $table;
            $db->exec("DROP TABLE IF EXISTS `{$probe}`");
            $db->exec("CREATE TABLE `{$probe}` (" . $def['body'] . "\n) " . $def['options']);
            try {
                $want = self::liveShape($probe);
            } finally {
                $db->exec("DROP TABLE IF EXISTS `{$probe}`");
            }
            $have = self::liveShape($table);

            if ($have['engine'] !== $want['engine']) {
                $db->exec("ALTER TABLE `{$table}` ENGINE=" . $want['engine']);
            }
            if ($have['collation'] !== $want['collation']) {
                $charset = strstr($want['collation'], '_', true);
                $db->exec("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET {$charset} COLLATE {$want['collation']}");
                $have = self::liveShape($table);
            }

            $specs = array();
            foreach ($def['columns'] as $column => $sqlDef) {
                $w = $want['columns'][$column];
                if (!isset($have['columns'][$column])) {
                    $specs[] = "ADD COLUMN `{$column}` {$sqlDef}";
                    continue;
                }
                $h = $have['columns'][$column];
                if ($h['sig'] === $w['sig']) {
                    continue;
                }
                if ($h['nullable'] && !$w['nullable']) {
                    $db->exec("UPDATE `{$table}` SET `{$column}` = " . self::fillValue($db, $w) . " WHERE `{$column}` IS NULL");
                }
                $text = "`{$column}`";
                if ($h['collation'] === null && $w['collation'] !== null) {
                    // Binary left over from the pre-4.0 relabel: scrub invalid UTF-8 so the MODIFY cannot fail.
                    self::scrubBinaryColumn($db, $table, $column);
                    $text = "CONVERT(`{$column}` USING utf8mb4)";
                }
                if (preg_match('/^varchar\((\d+)\)/', $w['type'], $len)) {
                    $db->exec("UPDATE `{$table}` SET `{$column}` = LEFT({$text}, {$len[1]}) WHERE CHAR_LENGTH({$text}) > {$len[1]}");
                }
                $specs[] = "MODIFY COLUMN `{$column}` {$sqlDef}";
            }
            foreach ($have['indexes'] as $name => $sig) {
                if (!isset($want['indexes'][$name]) || $want['indexes'][$name] !== $sig) {
                    $specs[] = "DROP INDEX `{$name}`";
                }
            }
            foreach ($want['indexes'] as $name => $sig) {
                if (!isset($have['indexes'][$name]) || $have['indexes'][$name] !== $sig) {
                    $specs[] = 'ADD ' . str_replace(' (', " `{$name}` (", $sig);
                }
            }
            if (!empty($specs)) {
                $db->exec("ALTER TABLE `{$table}` " . implode(', ', $specs));
            }
        }
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
