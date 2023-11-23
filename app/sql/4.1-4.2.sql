--
-- Table structure for table `reports_data`
--

CREATE TABLE `reports_data` (
    `id` INT(11) NOT NULL,
    `reportid` INT(11) NOT NULL,
    `dom` longtext,
    `screenshot` longtext,
    `localstorage` longtext,
    `sessionstorage` longtext
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

--
-- Indexes for table `reports_data`
--

ALTER TABLE `reports_data`
  ADD PRIMARY KEY (`id`);

--
-- Move data from `reports` to `reports_data`
--

INSERT INTO reports_data (reportid, dom, screenshot, localstorage, sessionstorage)
SELECT id, dom, screenshot, localstorage, sessionstorage
FROM reports;

ALTER TABLE reports
DROP COLUMN dom,
DROP COLUMN screenshot,
DROP COLUMN localstorage,
DROP COLUMN sessionstorage;

--
-- INDEX for tables
--

ALTER TABLE reports ADD INDEX(archive);
ALTER TABLE reports ADD INDEX(payload);
ALTER TABLE reports ADD INDEX(id);
ALTER TABLE reports_data ADD INDEX(reportid);