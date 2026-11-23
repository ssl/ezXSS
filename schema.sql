-- ezXSS 5.0 schema. MySQL/MariaDB, utf8mb4.

CREATE TABLE IF NOT EXISTS `users` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`    VARCHAR(25) NOT NULL,
    `password`    VARCHAR(255) NOT NULL,
    `role`        VARCHAR(50) NOT NULL DEFAULT 'user',
    `permissions` JSON DEFAULT NULL,
    `twofa_secret` VARCHAR(64) DEFAULT NULL,
    `notepad`     TEXT,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `banned`      TINYINT(1) NOT NULL DEFAULT 0,
    `github`      VARCHAR(39) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tokens` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED NOT NULL,
    `token_hash`   CHAR(64) NOT NULL,
    `type`         ENUM('session','apikey') NOT NULL DEFAULT 'session',
    `name`         VARCHAR(100) DEFAULT NULL,
    `last_used_at` DATETIME DEFAULT NULL,
    `expires_at`   DATETIME DEFAULT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tokens_hash` (`token_hash`),
    KEY `idx_tokens_user` (`user_id`),
    KEY `idx_tokens_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_access` (
    `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`   INT UNSIGNED NOT NULL,
    `for_table` VARCHAR(64) NOT NULL,
    `for_id`    INT UNSIGNED NOT NULL,
    `view`      TINYINT(1) NOT NULL DEFAULT 0,
    `edit`      TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_access` (`user_id`, `for_table`, `for_id`),
    KEY `idx_access_lookup` (`for_table`, `for_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payloads` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `identifier`  VARCHAR(500) NOT NULL,
    `user_id`     INT UNSIGNED NOT NULL DEFAULT 0,
    `persistent`  TINYINT(1) NOT NULL DEFAULT 0,
    `spider`      TINYINT(1) NOT NULL DEFAULT 0,
    `beacon`      TINYINT(1) NOT NULL DEFAULT 0,
    `customjs`    TEXT,
    `customjs2`   TEXT,
    `extensions`  VARCHAR(255) DEFAULT NULL,
    `type`        VARCHAR(32) NOT NULL DEFAULT 'default',
    `csp_filter`  VARCHAR(255) DEFAULT NULL,
    `meta`        JSON DEFAULT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_payloads_identifier` (`identifier`),
    KEY `idx_payloads_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payload_pages` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payload_id` INT UNSIGNED NOT NULL,
    `path`       VARCHAR(1000) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_pages_payload` (`payload_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payload_filters` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payload_id` INT UNSIGNED NOT NULL,
    `domain`     VARCHAR(500) NOT NULL,
    `type`       ENUM('allow','deny') NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_filters_payload` (`payload_id`),
    KEY `idx_filters_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payload_collect` (
    `payload_id`             INT UNSIGNED NOT NULL,
    `collect_uri`            TINYINT(1) NOT NULL DEFAULT 1,
    `collect_ip`             TINYINT(1) NOT NULL DEFAULT 1,
    `collect_referer`        TINYINT(1) NOT NULL DEFAULT 1,
    `collect_useragent`      TINYINT(1) NOT NULL DEFAULT 1,
    `collect_cookies`        TINYINT(1) NOT NULL DEFAULT 1,
    `collect_localstorage`   TINYINT(1) NOT NULL DEFAULT 1,
    `collect_sessionstorage` TINYINT(1) NOT NULL DEFAULT 1,
    `collect_dom`            TINYINT(1) NOT NULL DEFAULT 1,
    `collect_screenshot`     TINYINT(1) NOT NULL DEFAULT 0,
    `collect_csp`            TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`payload_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reports` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payload`     VARCHAR(255) DEFAULT NULL,
    `uri`         VARCHAR(1000) DEFAULT NULL,
    `ip`          VARCHAR(50) DEFAULT NULL,
    `referer`     VARCHAR(1000) DEFAULT NULL,
    `useragent`   VARCHAR(500) DEFAULT NULL,
    `cookies`     MEDIUMTEXT,
    `origin`      VARCHAR(500) DEFAULT NULL,
    `archive`     TINYINT(1) NOT NULL DEFAULT 0,
    `meta`        JSON DEFAULT NULL,
    `created_at`  INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_reports_payload` (`payload`),
    KEY `idx_reports_archive` (`archive`),
    KEY `idx_reports_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reports_data` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `report_id`      INT UNSIGNED NOT NULL,
    `dom`            LONGTEXT,
    `screenshot`     LONGTEXT,
    `localstorage`   LONGTEXT,
    `sessionstorage` LONGTEXT,
    `extra`          LONGTEXT,
    `compressed`     TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_reports_data_report` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `report_shares` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`            VARCHAR(32) NOT NULL,
    `report_id`       INT UNSIGNED NOT NULL,
    `allowed_columns` JSON DEFAULT NULL,
    `expires_at`      DATETIME DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_report_shares_slug` (`slug`),
    KEY `idx_report_shares_report` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id`  VARCHAR(50) NOT NULL,
    `payload`    VARCHAR(255) DEFAULT NULL,
    `uri`        VARCHAR(1000) DEFAULT NULL,
    `ip`         VARCHAR(50) DEFAULT NULL,
    `referer`    VARCHAR(1000) DEFAULT NULL,
    `useragent`  VARCHAR(500) DEFAULT NULL,
    `cookies`    MEDIUMTEXT,
    `origin`     VARCHAR(500) DEFAULT NULL,
    `archive`    TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` INT UNSIGNED DEFAULT NULL,
    `updated_at` INT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    KEY `idx_sessions_client` (`client_id`),
    KEY `idx_sessions_payload` (`payload`),
    KEY `idx_sessions_origin` (`origin`),
    KEY `idx_sessions_archive` (`archive`),
    KEY `idx_sessions_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions_data` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id`     INT UNSIGNED NOT NULL,
    `dom`            LONGTEXT,
    `localstorage`   LONGTEXT,
    `sessionstorage` LONGTEXT,
    `console`        LONGTEXT,
    `compressed`     TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_sessions_data_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `console` (
    `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id` VARCHAR(50) NOT NULL,
    `origin`    VARCHAR(500) NOT NULL,
    `command`   TEXT NOT NULL,
    `executed`  TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_console_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
    `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting` VARCHAR(191) NOT NULL,
    `value`   TEXT NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_settings_setting` (`setting`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `roles` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(50) NOT NULL,
    `permissions` JSON DEFAULT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `alerts` (
    `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`   INT UNSIGNED NOT NULL DEFAULT 0,
    `method_id` INT UNSIGNED NOT NULL,
    `enabled`   TINYINT(1) NOT NULL DEFAULT 0,
    `value1`    VARCHAR(250) NOT NULL DEFAULT '',
    `value2`    VARCHAR(250) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    KEY `idx_alerts_user` (`user_id`),
    KEY `idx_alerts_method` (`method_id`),
    KEY `idx_alerts_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `extensions` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(35) NOT NULL,
    `description` VARCHAR(250) NOT NULL DEFAULT '',
    `version`     VARCHAR(15) NOT NULL DEFAULT '',
    `author`      VARCHAR(50) NOT NULL DEFAULT '',
    `source`      VARCHAR(250) NOT NULL DEFAULT '',
    `code`        MEDIUMTEXT NOT NULL,
    `enabled`     TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_extensions_source` (`source`),
    KEY `idx_extensions_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `logs` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NOT NULL DEFAULT 0,
    `description` VARCHAR(1000) NOT NULL,
    `ip`          VARCHAR(100) NOT NULL DEFAULT '',
    `created_at`  INT UNSIGNED NOT NULL,
    `type`        VARCHAR(32) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    KEY `idx_logs_user` (`user_id`),
    KEY `idx_logs_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache` (
    `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`      VARCHAR(191) NOT NULL,
    `value`     LONGTEXT,
    `timestamp` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cache_name` (`name`),
    KEY `idx_cache_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `migrations` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `filename`   VARCHAR(191) NOT NULL,
    `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_migrations_filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
