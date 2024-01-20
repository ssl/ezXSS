INSERT INTO `settings` (`setting`, `value`) VALUES ("killswitch", "");

INSERT INTO `settings` (`setting`, `value`) VALUES ("version", "3.5");

ALTER TABLE `reports` ADD `payload` VARCHAR(500) NULL AFTER `referer`;