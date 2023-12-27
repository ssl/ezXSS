INSERT INTO `settings` (`id`, `setting`, `value`) VALUES (NULL, 'screenshot', '0');

ALTER TABLE `reports` 
ADD `screenshot` LONGTEXT NULL DEFAULT NULL AFTER `archive`, 
ADD `localstorage` LONGTEXT NULL DEFAULT NULL AFTER `archive`, 
ADD `sessionstorage` LONGTEXT NULL DEFAULT NULL AFTER `archive`, 
ADD `shareid` VARCHAR(50) NOT NULL AFTER `id`;

UPDATE `reports` SET `shareid` = concat(
    lpad(conv(floor(rand()*pow(36,8)), 10, 36), 8, 0),
    lpad(conv(floor(rand()*pow(36,8)), 10, 36), 8, 0),
    lpad(conv(floor(rand()*pow(36,8)), 10, 36), 8, 0),
    lpad(conv(floor(rand()*pow(36,8)), 10, 36), 8, 0)
);