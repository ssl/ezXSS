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


CREATE TABLE `extensions` (
  `id` int(11) NOT NULL,
  `name` varchar(35) NOT NULL,
  `description` varchar(250) NOT NULL,
  `version` varchar(15) NOT NULL,
  `author` varchar(50) NOT NULL,
  `source` varchar(250) NOT NULL,
  `code` mediumtext NOT NULL,
  `enabled` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `extensions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `source` (`source`),
  ADD KEY `enabled` (`enabled`);

ALTER TABLE `extensions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
