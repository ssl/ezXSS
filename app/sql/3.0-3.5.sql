INSERT INTO `settings` (`setting`, `value`) VALUES ("killswitch", "");

ALTER TABLE `reports` ADD `payload` VARCHAR(500) NULL AFTER `referer`;