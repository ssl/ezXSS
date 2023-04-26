--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
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


ALTER TABLE `payloads` ADD `sessions` BOOLEAN NOT NULL DEFAULT FALSE AFTER `pages`;

ALTER TABLE `sessions` ADD `console` LONGTEXT NOT NULL AFTER `sessionstorage`;


--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

INSERT INTO `settings` (`id`, `setting`, `value`) VALUES (NULL, 'logging', '0');
INSERT INTO `settings` (`id`, `setting`, `value`) VALUES (NULL, 'persistent', '0');

CREATE TABLE `logs` (
  `id` int NOT NULL,
  `user` varchar(500) COLLATE utf8mb4_general_ci NOT NULL,
  `description` varchar(1000) COLLATE utf8mb4_general_ci NOT NULL,
  `ip` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `time` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;
COMMIT;

ALTER TABLE `logs` CHANGE `user` `user_id` INT NOT NULL;
