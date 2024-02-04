--
-- INDEX for tables
--

ALTER TABLE reports ENGINE INNODB;
ALTER TABLE settings ENGINE INNODB;

ALTER TABLE reports ADD INDEX(archive);
ALTER TABLE reports ADD INDEX(id);
ALTER TABLE reports ADD INDEX(shareid);
ALTER TABLE reports_data ADD INDEX(reportid);

ALTER TABLE sessions ADD INDEX(id);
ALTER TABLE sessions ADD INDEX(clientid);
ALTER TABLE sessions_data ADD INDEX(sessionid);

ALTER TABLE `reports` CHANGE `payload` `payload` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `sessions` CHANGE `payload` `payload` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `sessions` CHANGE `origin` `origin` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;

ALTER TABLE reports ADD INDEX(payload);
ALTER TABLE sessions ADD INDEX(payload);
ALTER TABLE sessions ADD INDEX(origin);

