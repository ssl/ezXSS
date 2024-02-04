--
-- Add new settings
--

INSERT INTO `settings` (`setting`, `value`) VALUES ('storescreenshot', '0'), ('compress', '0');

--
-- Table structure for table `reports_data`
--

CREATE TABLE `reports_data` (
    `id` INT(11) NOT NULL,
    `reportid` INT(11) NOT NULL,
    `dom` longtext,
    `screenshot` longtext,
    `localstorage` longtext,
    `sessionstorage` longtext,
    `compressed` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for table `reports_data`
--

ALTER TABLE `reports_data`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for table `reports_data`
--
ALTER TABLE `reports_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

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
-- Table structure for table `sessions_data`
--

CREATE TABLE `sessions_data` (
    `id` INT(11) NOT NULL,
    `sessionid` INT(11) NOT NULL,
    `dom` longtext,
    `localstorage` longtext,
    `sessionstorage` longtext,
    `console` longtext,
    `compressed` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for table `sessions_data`
--

ALTER TABLE `sessions_data`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for table `sessions_data`
--
ALTER TABLE `sessions_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- Move data from `reports` to `sessions_data`
--

INSERT INTO sessions_data (sessionid, dom, localstorage, sessionstorage, console)
SELECT id, dom, localstorage, sessionstorage, console
FROM sessions;

ALTER TABLE sessions
DROP COLUMN dom,
DROP COLUMN localstorage,
DROP COLUMN sessionstorage,
DROP COLUMN console;