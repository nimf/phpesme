# ************************************************************
# Sequel Pro SQL dump
# Version 3408
#
# http://www.sequelpro.com/
# http://code.google.com/p/sequel-pro/
#
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Dump of table archive
# ------------------------------------------------------------

DROP TABLE IF EXISTS `archive`;

CREATE TABLE `archive` (
  `id` int(10) unsigned NOT NULL DEFAULT '0',
  `src` varchar(20) NOT NULL DEFAULT '',
  `dst` varchar(20) NOT NULL DEFAULT '',
  `msg` varchar(2048) NOT NULL DEFAULT '',
  `ts` int(10) unsigned NOT NULL DEFAULT '0',
  `mt` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `proc_ts` int(10) unsigned NOT NULL DEFAULT '0',
  `resp_id` int(10) unsigned NOT NULL DEFAULT '0',
  `srv_id` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `rslt` tinyint(3) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `proc_ts` (`proc_ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Dump of table inbox
# ------------------------------------------------------------

DROP TABLE IF EXISTS `inbox`;

CREATE TABLE `inbox` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `src` varchar(20) NOT NULL DEFAULT '',
  `dst` varchar(20) NOT NULL DEFAULT '',
  `msg` varchar(2048) NOT NULL DEFAULT '',
  `ts` int(10) unsigned NOT NULL DEFAULT '0',
  `mt` tinyint(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Dump of table outbox
# ------------------------------------------------------------

DROP TABLE IF EXISTS `outbox`;

CREATE TABLE `outbox` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rel_id` int(10) unsigned NOT NULL DEFAULT '0',
  `src` varchar(20) NOT NULL DEFAULT '',
  `dst` varchar(20) NOT NULL DEFAULT '',
  `msg` varchar(2048) NOT NULL DEFAULT '',
  `ts` int(10) unsigned NOT NULL DEFAULT '0',
  `rd` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `try_ts` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Dump of table parts
# ------------------------------------------------------------

DROP TABLE IF EXISTS `parts`;

CREATE TABLE `parts` (
  `src` char(20) NOT NULL DEFAULT '',
  `dst` char(20) NOT NULL DEFAULT '',
  `msg` char(255) NOT NULL DEFAULT '',
  `ts` int(10) unsigned NOT NULL DEFAULT '0',
  `ref` smallint(6) unsigned NOT NULL,
  `pn` tinyint(4) unsigned NOT NULL,
  `tp` tinyint(4) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Dump of table rslts
# ------------------------------------------------------------

DROP TABLE IF EXISTS `rslts`;

CREATE TABLE `rslts` (
  `srv_id` int(11) unsigned NOT NULL,
  `code` tinyint(3) NOT NULL DEFAULT '0',
  `dsc` char(255) NOT NULL,
  PRIMARY KEY (`srv_id`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Dump of table sent
# ------------------------------------------------------------

DROP TABLE IF EXISTS `sent`;

CREATE TABLE `sent` (
  `id` int(10) unsigned NOT NULL DEFAULT '0',
  `rel_id` int(10) unsigned NOT NULL DEFAULT '0',
  `src` varchar(20) NOT NULL DEFAULT '',
  `dst` varchar(20) NOT NULL DEFAULT '',
  `msg` varchar(2048) NOT NULL DEFAULT '',
  `ts` int(10) unsigned NOT NULL DEFAULT '0',
  `rd` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `snt_ts` int(10) unsigned NOT NULL DEFAULT '0',
  `msg_id` varchar(50) NOT NULL DEFAULT '',
  `dl_ts` int(10) unsigned DEFAULT NULL,
  `dl_stat` tinyint(3) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `msgid` (`msg_id`(27)),
  KEY `sent_snt_ts` (`snt_ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Dump of table service
# ------------------------------------------------------------

DROP TABLE IF EXISTS `service`;

CREATE TABLE `service` (
  `id` int(11) unsigned NOT NULL,
  `name` char(255) NOT NULL,
  `exported_id` int(11) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Dump of table stats
# ------------------------------------------------------------

DROP TABLE IF EXISTS `stats`;

CREATE TABLE `stats` (
  `ts` int(11) unsigned NOT NULL DEFAULT '0',
  `rec` int(11) unsigned NOT NULL DEFAULT '0',
  `sent` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
