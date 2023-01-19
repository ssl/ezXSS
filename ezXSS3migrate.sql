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

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting`, `value`) VALUES
('alert-slack', '1'),
('alert-discord', '1');

DELETE FROM `settings` WHERE `setting` = 'blocked-domains';
DELETE FROM `settings` WHERE `setting` = 'password';
DELETE FROM `settings` WHERE `setting` = 'email';
DELETE FROM `settings` WHERE `setting` = 'payload-domain';
DELETE FROM `settings` WHERE `setting` = 'emailfrom';
DELETE FROM `settings` WHERE `setting` = 'whitelist-domains';
DELETE FROM `settings` WHERE `setting` = 'telegram-bottoken';
DELETE FROM `settings` WHERE `setting` = 'telegram-chatid';
DELETE FROM `settings` WHERE `setting` = 'extract-pages';
DELETE FROM `settings` WHERE `setting` = 'adminurl';

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
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

ALTER TABLE reports CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;