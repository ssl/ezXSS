-- Migration from ezXSS 4.3 to 4.4
-- Adds DOM threshold setting

INSERT INTO `settings` (`setting`, `value`) VALUES ('dom_threshold', '2097152') 
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- Note: 2097152 bytes = 2MB default threshold
