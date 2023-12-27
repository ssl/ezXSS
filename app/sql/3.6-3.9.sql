INSERT INTO `settings` (`setting`, `value`) VALUES 
("collect_uri", "1"), 
("collect_ip", "1"), 
("collect_referer", "1"), 
("collect_user-agent", "1"), 
("collect_cookies", "1"),
("theme", "classic");

INSERT INTO `settings` (`setting`, `value`) VALUES 
("collect_localstorage", "1"), 
("collect_sessionstorage", "1"), 
("collect_dom", "1"), 
("collect_origin", "1"), 
("collect_screenshot", "0");

DELETE FROM `settings` WHERE `setting` = "screenshot"