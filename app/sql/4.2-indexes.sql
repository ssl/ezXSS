--
-- INDEX for tables
--

ALTER TABLE reports ENGINE INNODB;
ALTER TABLE settings ENGINE INNODB;

ALTER TABLE reports ADD INDEX(archive);
ALTER TABLE reports ADD INDEX(payload);
ALTER TABLE reports ADD INDEX(id);
ALTER TABLE reports ADD INDEX(shareid);
ALTER TABLE reports_data ADD INDEX(reportid);

ALTER TABLE sessions ADD INDEX(id);
ALTER TABLE sessions ADD INDEX(payload);
ALTER TABLE sessions ADD INDEX(clientid);
ALTER TABLE sessions ADD INDEX(origin);
ALTER TABLE sessions_data ADD INDEX(sessionid);