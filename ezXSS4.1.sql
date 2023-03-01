--
-- Table structure for table `persistent`
--

CREATE TABLE `persistent` (
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


ALTER TABLE `payloads` ADD `persistent` BOOLEAN NOT NULL DEFAULT FALSE AFTER `pages`;

ALTER TABLE `persistent` ADD `console` LONGTEXT NOT NULL AFTER `sessionstorage`;


--
-- AUTO_INCREMENT for table `persistent`
--
ALTER TABLE `persistent`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

