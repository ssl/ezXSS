INSERT INTO `settings` (`id`, `setting`, `value`) VALUES
(31, 'customjs2', '');

ALTER TABLE `payloads` ADD `customjs2` TEXT NOT NULL DEFAULT '' AFTER `customjs`;
