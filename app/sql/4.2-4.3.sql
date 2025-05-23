--
-- SQL for updating from 4.2 to 4.3
--

INSERT INTO `settings` (`setting`, `value`) VALUES
('customjs2', ''),
('spider', '1'),
('extensions', '');

ALTER TABLE logs ADD INDEX(user_id);

ALTER TABLE `payloads` ADD `customjs2` TEXT AFTER `customjs`;

ALTER TABLE `payloads` ADD `spider` INT NOT NULL DEFAULT '0' AFTER `pages`;

ALTER TABLE `payloads` ADD `extensions` VARCHAR(255) DEFAULT NULL AFTER `collect_screenshot`;

ALTER TABLE `reports_data` ADD `extra` LONGTEXT NULL DEFAULT NULL AFTER `sessionstorage`;
