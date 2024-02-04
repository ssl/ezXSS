-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` int(11) NOT NULL,
  `clientid` varchar(50) NOT NULL,
  `cookies` mediumtext,
  `dom` longtext,
  `origin` varchar(255) DEFAULT NULL,
  `referer` varchar(1000) DEFAULT NULL,
  `payload` varchar(255) DEFAULT NULL,
  `uri` varchar(1000) DEFAULT NULL,
  `user-agent` varchar(500) DEFAULT NULL,
  `ip` varchar(50) DEFAULT NULL,
  `time` int(11) DEFAULT NULL,
  `localstorage` longtext,
  `sessionstorage` longtext,
  `console` longtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `description` varchar(1000) NOT NULL,
  `ip` varchar(100) NOT NULL,
  `time` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `console`
--

CREATE TABLE `console` (
  `id` int(11) NOT NULL,
  `clientid` varchar(50) NOT NULL,
  `origin` varchar(500) NOT NULL,
  `command` text NOT NULL,
  `executed` decimal(10,0) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `console`
--
ALTER TABLE `console`
  ADD PRIMARY KEY (`id`);


--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
COMMIT;
--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
COMMIT;
--
-- AUTO_INCREMENT for table `console`
--
ALTER TABLE `console`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
COMMIT;


ALTER TABLE `payloads` ADD `persistent` tinyint(1) NOT NULL DEFAULT '0' AFTER `pages`;

INSERT INTO `settings` (`setting`, `value`) VALUES ('logging', '0');
INSERT INTO `settings` (`setting`, `value`) VALUES ('persistent', '0');