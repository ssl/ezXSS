--
-- Table structure for table `alerts`
--

CREATE TABLE `alerts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `method_id` int(11) NOT NULL,
  `enabled` tinyint(1) NOT NULL,
  `value1` varchar(250) NOT NULL,
  `value2` varchar(250) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `alerts`
--

INSERT INTO `alerts` (`id`, `user_id`, `method_id`, `enabled`, `value1`, `value2`) VALUES
(1, 0, 1, 0, '', ''),
(2, 0, 2, 0, '', ''),
(3, 0, 3, 0, '', ''),
(4, 0, 4, 0, '', '');

-- --------------------------------------------------------

--
-- Table structure for table `payloads`
--

CREATE TABLE `payloads` (
  `id` int(11) NOT NULL,
  `payload` varchar(500) NOT NULL,
  `user_id` int(11) NOT NULL,
  `pages` text,
  `blacklist` text,
  `whitelist` text,
  `customjs` text,
  `collect_uri` tinyint(1) NOT NULL DEFAULT '1',
  `collect_ip` tinyint(1) NOT NULL DEFAULT '1',
  `collect_referer` tinyint(1) NOT NULL DEFAULT '1',
  `collect_user-agent` tinyint(1) NOT NULL DEFAULT '1',
  `collect_cookies` tinyint(1) NOT NULL DEFAULT '1',
  `collect_localstorage` tinyint(1) NOT NULL DEFAULT '1',
  `collect_sessionstorage` tinyint(1) NOT NULL DEFAULT '1',
  `collect_dom` tinyint(1) NOT NULL DEFAULT '1',
  `collect_origin` tinyint(1) NOT NULL DEFAULT '1',
  `collect_screenshot` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `payloads`
--

INSERT INTO `payloads` (`id`, `payload`, `user_id`, `pages`, `blacklist`, `whitelist`, `customjs`, `collect_uri`, `collect_ip`, `collect_referer`, `collect_user-agent`, `collect_cookies`, `collect_localstorage`, `collect_sessionstorage`, `collect_dom`, `collect_origin`, `collect_screenshot`) VALUES
(1, 'Fallback (default)', 0, '', '', '', '', 1, 1, 1, 1, 1, 1, 1, 1, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `shareid` varchar(50) NOT NULL,
  `cookies` mediumtext,
  `dom` longtext,
  `origin` varchar(500) DEFAULT NULL,
  `referer` varchar(1000) DEFAULT NULL,
  `payload` varchar(500) DEFAULT NULL,
  `uri` varchar(1000) DEFAULT NULL,
  `user-agent` varchar(500) DEFAULT NULL,
  `ip` varchar(50) DEFAULT NULL,
  `time` int(11) DEFAULT NULL,
  `archive` int(11) DEFAULT '0',
  `screenshot` longtext,
  `localstorage` longtext,
  `sessionstorage` longtext
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting` varchar(500) NOT NULL,
  `value` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting`, `value`) VALUES
(1, 'filter-save', '1'),
(2, 'filter-alert', '1'),
(3, 'dompart', '500'),
(4, 'timezone', 'Europe/Amsterdam'),
(5, 'customjs', ''),
(7, 'notepad', 'Welcome to ezXSS 4!'),
(8, 'version', '4.0'),
(9, 'killswitch', ''),
(10, 'collect_uri', '1'),
(11, 'collect_ip', '1'),
(12, 'collect_referer', '1'),
(13, 'collect_user-agent', '1'),
(14, 'collect_cookies', '1'),
(15, 'collect_localstorage', '1'),
(16, 'collect_sessionstorage', '1'),
(17, 'collect_dom', '1'),
(18, 'collect_origin', '1'),
(19, 'collect_screenshot', '1'),
(20, 'theme', 'classic'),
(21, 'callback-url', ''),
(22, 'alert-mail', '1'),
(23, 'alert-telegram', '1'),
(24, 'alert-callback', '1'),
(25, 'alert-slack', '1'),
(26, 'alert-discord', '1');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(25) NOT NULL,
  `password` varchar(60) NOT NULL,
  `rank` int(11) NOT NULL DEFAULT '1',
  `secret` varchar(25) NOT NULL,
  `row1` tinyint(4) NOT NULL DEFAULT '1',
  `row2` tinyint(4) NOT NULL DEFAULT '3',
  `notepad` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alerts`
--
ALTER TABLE `alerts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payloads`
--
ALTER TABLE `payloads`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alerts`
--
ALTER TABLE `alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
--
-- AUTO_INCREMENT for table `payloads`
--
ALTER TABLE `payloads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;
--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;