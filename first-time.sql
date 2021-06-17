-- MySQL dump 10.19  Distrib 10.3.29-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: 127.0.0.1    Database: iabot
-- ------------------------------------------------------
-- Server version	10.5.10-MariaDB-1:10.5.10+maria~focal

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `externallinks_botqueue`
--

DROP TABLE IF EXISTS `externallinks_botqueue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `externallinks_botqueue` (
  `queue_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `wiki` varchar(45) NOT NULL,
  `queue_user` int(10) unsigned NOT NULL,
  `queue_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `status_timestamp` timestamp NULL DEFAULT NULL,
  `queue_status` int(11) NOT NULL DEFAULT 0,
  `run_stats` blob NOT NULL,
  `assigned_worker` varchar(100) DEFAULT NULL,
  `worker_finished` int(11) NOT NULL DEFAULT 0,
  `worker_target` int(11) NOT NULL,
  PRIMARY KEY (`queue_id`),
  KEY `WIKI` (`wiki`),
  KEY `USER` (`queue_user`),
  KEY `QUEUED` (`queue_timestamp`),
  KEY `STATUSCHANGE` (`status_timestamp`),
  KEY `STATUS` (`queue_status`),
  KEY `RUNSIZE` (`worker_target`),
  KEY `WORKER` (`assigned_worker`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `externallinks_botqueue`
--

LOCK TABLES `externallinks_botqueue` WRITE;
/*!40000 ALTER TABLE `externallinks_botqueue` DISABLE KEYS */;
/*!40000 ALTER TABLE `externallinks_botqueue` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `externallinks_botqueuepages`
--

DROP TABLE IF EXISTS `externallinks_botqueuepages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `externallinks_botqueuepages` (
  `entry_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `queue_id` int(10) unsigned NOT NULL,
  `page_title` varchar(255) CHARACTER SET utf8 NOT NULL,
  `status` varchar(15) NOT NULL DEFAULT 'wait',
  `rev_id` int(11) NOT NULL DEFAULT 0,
  `status_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`entry_id`),
  KEY `QUEUEID` (`queue_id`),
  KEY `TITLE` (`page_title`),
  KEY `STATUSCHANGE` (`status_timestamp`),
  KEY `STATUS` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `externallinks_botqueuepages`
--

LOCK TABLES `externallinks_botqueuepages` WRITE;
/*!40000 ALTER TABLE `externallinks_botqueuepages` DISABLE KEYS */;
/*!40000 ALTER TABLE `externallinks_botqueuepages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `externallinks_configuration`
--

DROP TABLE IF EXISTS `externallinks_configuration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `externallinks_configuration` (
  `config_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `config_type` varchar(45) NOT NULL,
  `config_key` varchar(45) NOT NULL,
  `config_wiki` varchar(45) NOT NULL,
  `config_data` blob NOT NULL,
  PRIMARY KEY (`config_id`),
  UNIQUE KEY `unique_CONFIG` (`config_wiki`,`config_type`,`config_key`),
  KEY `TYPE` (`config_type`),
  KEY `WIKI` (`config_wiki`),
  KEY `KEY` (`config_key`)
) ENGINE=InnoDB AUTO_INCREMENT=140 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `externallinks_configuration`
--

LOCK TABLES `externallinks_configuration` WRITE;
/*!40000 ALTER TABLE `externallinks_configuration` DISABLE KEYS */;
INSERT INTO `externallinks_configuration` VALUES (1,'versionData','currentVersion','global','s:5:\"2.0.8\";'),(2,'versionData','rollbackVersions','global','a:0:{}'),(3,'systemglobals','token','global','s:13:\"{{csrftoken}}\";'),(4,'systemglobals','checksum','global','s:12:\"{{checksum}}\";'),(5,'systemglobals','disableEdits','global','b:0;'),(6,'systemglobals','userAgent','global','s:5:\"IABot\";'),(7,'systemglobals','cidUserAgent','global','s:5:\"IABot\";'),(8,'systemglobals','taskname','global','s:5:\"IABot\";'),(9,'systemglobals','enableAPILogging','global','b:0;'),(10,'systemglobals','expectedValue','global','s:0:\"\";'),(11,'systemglobals','decodeFunction','global','s:0:\"\";'),(12,'systemglobals','enableMail','global','b:0;'),(13,'systemglobals','to','global','s:0:\"\";'),(14,'systemglobals','from','global','s:0:\"\";'),(15,'systemglobals','guifrom','global','s:21:\"karim.ratib@gmail.com\";'),(16,'systemglobals','guidomainroot','global','s:22:\"http://localhost:8080/\";'),(17,'systemglobals','useCIDservers','global','b:0;'),(18,'systemglobals','cidServers','global','a:1:{i:0;s:0:\"\";}'),(19,'systemglobals','cidAuthCode','global','s:0:\"\";'),(20,'systemglobals','enableProfiling','global','b:0;'),(21,'systemglobals','defaultWiki','global','s:8:\"testwiki\";'),(22,'systemglobals','autoFPReport','global','b:0;'),(23,'systemglobals','availabilityThrottle','global','i:0;'),(24,'systemglobals','disableInterface','global','b:0;'),(40,'systemglobals-allwikis','testwiki','global','a:16:{s:12:\"wikiNameFrom\";s:8:\"testwiki\";s:5:\"token\";s:13:\"{{csrftoken}}\";s:8:\"checksum\";s:12:\"{{checksum}}\";s:10:\"i18nsource\";s:36:\"https://meta.wikimedia.org/w/api.php\";s:14:\"i18nsourcename\";s:4:\"meta\";s:8:\"language\";s:2:\"en\";s:7:\"rooturl\";s:27:\"https://test.wikipedia.org/\";s:6:\"apiurl\";s:36:\"https://test.wikipedia.org/w/api.php\";s:8:\"oauthurl\";s:58:\"https://test.wikipedia.org/w/index.php?title=Special:OAuth\";s:7:\"runpage\";b:1;s:6:\"nobots\";b:1;s:8:\"botqueue\";s:1:\"0\";s:7:\"apiCall\";s:0:\"\";s:7:\"usekeys\";s:7:\"default\";s:9:\"usewikidb\";s:1:\"0\";s:15:\"runpagelocation\";s:12:\"metatestwiki\";}'),(41,'wikiconfig','runpage','testwiki','s:7:\"disable\";'),(42,'wiki-languages','en','global','a:1:{s:16:\"metatestwikiname\";s:25:\"testwiki - Test Wikipedia\";}'),(43,'languages','en','global','a:40:{s:2:\"ar\";s:11:\"ar - Arabic\";s:3:\"ast\";s:14:\"ast - Asturian\";s:2:\"az\";s:16:\"az - Azerbaijani\";s:2:\"bn\";s:11:\"bn - Bangla\";s:2:\"br\";s:11:\"br - Breton\";s:2:\"bs\";s:12:\"bs - Bosnian\";s:2:\"ca\";s:12:\"ca - Catalan\";s:2:\"da\";s:11:\"da - Danish\";s:2:\"de\";s:11:\"de - German\";s:3:\"diq\";s:12:\"diq - Zazaki\";s:2:\"el\";s:10:\"el - Greek\";s:2:\"en\";s:12:\"en - English\";s:2:\"eo\";s:14:\"eo - Esperanto\";s:2:\"es\";s:12:\"es - Spanish\";s:2:\"fa\";s:12:\"fa - Persian\";s:2:\"fi\";s:12:\"fi - Finnish\";s:2:\"fr\";s:11:\"fr - French\";s:2:\"gl\";s:13:\"gl - Galician\";s:2:\"he\";s:11:\"he - Hebrew\";s:2:\"hr\";s:13:\"hr - Croatian\";s:2:\"hu\";s:14:\"hu - Hungarian\";s:2:\"it\";s:12:\"it - Italian\";s:2:\"ja\";s:13:\"ja - Japanese\";s:2:\"ko\";s:11:\"ko - Korean\";s:2:\"lb\";s:18:\"lb - Luxembourgish\";s:2:\"lt\";s:15:\"lt - Lithuanian\";s:2:\"mk\";s:15:\"mk - Macedonian\";s:2:\"nb\";s:22:\"nb - Norwegian Bokmål\";s:2:\"nl\";s:10:\"nl - Dutch\";s:2:\"oc\";s:12:\"oc - Occitan\";s:2:\"pt\";s:15:\"pt - Portuguese\";s:5:\"pt-br\";s:28:\"pt-br - Brazilian Portuguese\";s:2:\"ru\";s:12:\"ru - Russian\";s:2:\"sq\";s:13:\"sq - Albanian\";s:5:\"sr-ec\";s:33:\"sr-ec - Serbian (Cyrillic script)\";s:2:\"sv\";s:12:\"sv - Swedish\";s:2:\"tr\";s:12:\"tr - Turkish\";s:2:\"uk\";s:14:\"uk - Ukrainian\";s:7:\"zh-hans\";s:28:\"zh-hans - Simplified Chinese\";s:7:\"zh-hant\";s:29:\"zh-hant - Traditional Chinese\";}'),(44,'archive-templates','Webarchive','global','a:2:{s:16:\"templatebehavior\";s:6:\"append\";s:26:\"archivetemplatedefinitions\";O:7:\"CiteMap\":12:{s:13:\"\0*\0formalName\";s:19:\"Template:Webarchive\";s:15:\"\0*\0informalName\";s:10:\"Webarchive\";s:6:\"\0*\0map\";a:3:{s:6:\"params\";a:5:{i:0;s:3:\"url\";i:1;s:1:\"1\";i:2;s:4:\"date\";i:3;s:1:\"2\";i:4;s:5:\"title\";}s:4:\"data\";a:3:{i:0;a:3:{s:5:\"mapto\";a:2:{i:0;i:0;i:1;i:1;}s:11:\"valueString\";s:12:\"{archiveurl}\";s:9:\"universal\";b:1;}i:1;a:3:{s:5:\"mapto\";a:2:{i:0;i:2;i:1;i:3;}s:11:\"valueString\";s:28:\"{archivetimestamp:automatic}\";s:9:\"universal\";b:1;}i:2;a:3:{s:5:\"mapto\";a:1:{i:0;i:4;}s:11:\"valueString\";s:7:\"{title}\";s:9:\"universal\";b:1;}}s:8:\"services\";a:1:{s:8:\"@default\";a:3:{s:11:\"archive_url\";a:1:{i:0;i:0;}s:12:\"archive_date\";a:1:{i:0;a:3:{s:5:\"index\";i:1;s:6:\"format\";s:9:\"automatic\";s:4:\"type\";s:9:\"timestamp\";}}s:5:\"title\";a:1:{i:0;i:2;}}}}s:15:\"\0*\0templateData\";b:0;s:9:\"\0*\0string\";s:68:\"url|1={archiveurl}|date|2={archivetimestamp:automatic}|title={title}\";s:14:\"\0*\0luaLocation\";b:0;s:13:\"\0*\0redirected\";b:0;s:11:\"\0*\0disabled\";b:0;s:17:\"\0*\0disabledByUser\";b:0;s:21:\"\0*\0assertRequirements\";a:1:{s:8:\"__NONE__\";a:1:{i:0;s:11:\"archive_url\";}}s:18:\"\0*\0useTemplateData\";b:0;s:17:\"\0*\0classification\";s:7:\"archive\";}}'),(93,'wikiconfig','darchive_Webarchive','testwiki','a:1:{i:0;s:9:\"{{hello}}\";}'),(94,'wikiconfig','link_scan','testwiki','i:0;'),(95,'wikiconfig','dead_only','testwiki','i:2;'),(96,'wikiconfig','tag_override','testwiki','i:1;'),(97,'wikiconfig','page_scan','testwiki','i:0;'),(98,'wikiconfig','archive_by_accessdate','testwiki','i:1;'),(99,'wikiconfig','touch_archive','testwiki','i:0;'),(100,'wikiconfig','notify_on_talk','testwiki','i:0;'),(101,'wikiconfig','notify_on_talk_only','testwiki','i:0;'),(102,'wikiconfig','notify_error_on_talk','testwiki','i:0;'),(103,'wikiconfig','talk_message_verbose','testwiki','i:0;'),(104,'wikiconfig','rate_limit','testwiki','s:13:\"60 per minute\";'),(105,'wikiconfig','talk_message_header','testwiki','s:5:\"hello\";'),(106,'wikiconfig','talk_message','testwiki','s:5:\"hello\";'),(107,'wikiconfig','talk_message_header_talk_only','testwiki','s:5:\"hello\";'),(108,'wikiconfig','talk_message_talk_only','testwiki','s:5:\"hello\";'),(109,'wikiconfig','talk_error_message_header','testwiki','s:5:\"hello\";'),(110,'wikiconfig','talk_error_message','testwiki','s:5:\"hello\";'),(111,'wikiconfig','ignore_tags','testwiki','a:1:{i:0;s:9:\"{{hello}}\";}'),(112,'wikiconfig','talk_only_tags','testwiki','a:1:{i:0;s:9:\"{{hello}}\";}'),(113,'wikiconfig','no_talk_tags','testwiki','a:1:{i:0;s:9:\"{{hello}}\";}'),(114,'wikiconfig','paywall_tags','testwiki','a:1:{i:0;s:9:\"{{hello}}\";}'),(115,'wikiconfig','deadlink_tags','testwiki','a:1:{i:0;s:9:\"{{hello}}\";}'),(116,'wikiconfig','verify_dead','testwiki','i:1;'),(117,'wikiconfig','archive_alive','testwiki','i:0;'),(118,'wikiconfig','convert_archives','testwiki','i:1;'),(119,'wikiconfig','convert_archives_encoding','testwiki','i:1;'),(120,'wikiconfig','convert_to_cites','testwiki','i:1;'),(121,'wikiconfig','mladdarchivetalkonly','testwiki','s:5:\"hello\";'),(122,'wikiconfig','mltaggedtalkonly','testwiki','s:5:\"hello\";'),(123,'wikiconfig','mltagremovedtalkonly','testwiki','s:5:\"hello\";'),(124,'wikiconfig','mladdarchive','testwiki','s:5:\"hello\";'),(125,'wikiconfig','mlmodifyarchive','testwiki','s:5:\"hello\";'),(126,'wikiconfig','mlfix','testwiki','s:5:\"hello\";'),(127,'wikiconfig','mltagged','testwiki','s:5:\"hello\";'),(128,'wikiconfig','mltagremoved','testwiki','s:5:\"hello\";'),(129,'wikiconfig','mldefault','testwiki','s:5:\"hello\";'),(130,'wikiconfig','plerror','testwiki','s:5:\"hello\";'),(131,'wikiconfig','maineditsummary','testwiki','s:5:\"hello\";'),(132,'wikiconfig','errortalkeditsummary','testwiki','s:5:\"hello\";'),(133,'wikiconfig','talkeditsummary','testwiki','s:5:\"hello\";'),(134,'wikiconfig','notify_domains','testwiki','a:0:{}'),(135,'wikiconfig','templatebehavior','testwiki','s:6:\"append\";'),(136,'wikiconfig','dateformat','testwiki','a:2:{s:10:\"raw_syntax\";s:5:\"hello\";s:6:\"syntax\";a:1:{i:0;a:1:{s:6:\"format\";s:5:\"hello\";}}}'),(137,'wikiconfig','tag_cites','testwiki','i:0;'),(138,'wikiconfig','ref_tags','testwiki','a:1:{i:0;s:19:\"{{hello}};{{hello}}\";}'),(139,'interface-usergroups','root','global','a:8:{s:14:\"inheritsgroups\";a:0:{}s:13:\"inheritsflags\";a:41:{i:0;s:15:\"alteraccesstime\";i:1;s:15:\"alterarchiveurl\";i:2;s:11:\"analyzepage\";i:3;s:16:\"blacklistdomains\";i:4;s:13:\"blacklisturls\";i:5;s:9:\"blockuser\";i:6;s:18:\"botsubmitlimit5000\";i:7;s:19:\"botsubmitlimit50000\";i:8;s:21:\"botsubmitlimitnolimit\";i:9;s:20:\"changefpreportstatus\";i:10;s:11:\"changebqjob\";i:11;s:16:\"changedomaindata\";i:12;s:23:\"changeglobalpermissions\";i:13;s:12:\"changemassbq\";i:14;s:17:\"changepermissions\";i:15;s:13:\"changeurldata\";i:16;s:22:\"configurecitationrules\";i:17;s:20:\"configuresystemsetup\";i:18;s:13:\"configurewiki\";i:19;s:18:\"deblacklistdomains\";i:20;s:15:\"deblacklisturls\";i:21;s:22:\"definearchivetemplates\";i:22;s:16:\"defineusergroups\";i:23;s:10:\"definewiki\";i:24;s:18:\"dewhitelistdomains\";i:25;s:15:\"dewhitelisturls\";i:26;s:12:\"highapilimit\";i:27;s:22:\"fpruncheckifdeadreview\";i:28;s:9:\"invokebot\";i:29;s:25:\"overridearchivevalidation\";i:30;s:15:\"overridelockout\";i:31;s:8:\"reportfp\";i:32;s:13:\"submitbotjobs\";i:33;s:13:\"togglerunpage\";i:34;s:11:\"unblockuser\";i:35;s:9:\"unblockme\";i:36;s:12:\"viewbotqueue\";i:37;s:16:\"viewfpreviewpage\";i:38;s:15:\"viewmetricspage\";i:39;s:16:\"whitelistdomains\";i:40;s:13:\"whitelisturls\";}s:12:\"assigngroups\";a:0:{}s:12:\"removegroups\";a:0:{}s:11:\"assignflags\";a:0:{}s:11:\"removeflags\";a:0:{}s:10:\"labelclass\";s:7:\"default\";s:11:\"autoacquire\";a:4:{s:10:\"registered\";i:0;s:9:\"editcount\";i:0;s:13:\"withwikigroup\";a:0:{}s:13:\"withwikiright\";a:0:{}}}');
/*!40000 ALTER TABLE `externallinks_configuration` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `externallinks_fpreports`
--

DROP TABLE IF EXISTS `externallinks_fpreports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `externallinks_fpreports` (
  `report_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `wiki` varchar(45) NOT NULL,
  `report_user_id` int(10) unsigned NOT NULL,
  `report_url_id` int(10) unsigned NOT NULL,
  `report_error` blob NOT NULL DEFAULT '',
  `report_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `status_timestamp` timestamp NULL DEFAULT NULL,
  `report_status` int(11) NOT NULL DEFAULT 0,
  `report_version` varchar(15) NOT NULL,
  PRIMARY KEY (`report_id`),
  KEY `WIKI` (`wiki`),
  KEY `USER` (`report_user_id`),
  KEY `REPORTED` (`report_timestamp`),
  KEY `STATUSCHANGE` (`status_timestamp`),
  KEY `STATUS` (`report_status`),
  KEY `VERSION` (`report_version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `externallinks_fpreports`
--

LOCK TABLES `externallinks_fpreports` WRITE;
/*!40000 ALTER TABLE `externallinks_fpreports` DISABLE KEYS */;
/*!40000 ALTER TABLE `externallinks_fpreports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `externallinks_user`
--

DROP TABLE IF EXISTS `externallinks_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `externallinks_user` (
  `user_id` int(10) unsigned NOT NULL,
  `wiki` varchar(45) NOT NULL,
  `user_name` varbinary(255) NOT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `last_action` timestamp NULL DEFAULT NULL,
  `blocked` int(11) NOT NULL DEFAULT 0,
  `language` varchar(45) NOT NULL,
  `data_cache` blob NOT NULL,
  `user_link_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`wiki`,`user_id`),
  KEY `USERNAME` (`user_name`),
  KEY `LASTLOGIN` (`last_login`),
  KEY `LASTACTION` (`last_action`),
  KEY `BLOCKED` (`blocked`),
  KEY `LINKID` (`user_link_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `externallinks_userflags`
--

DROP TABLE IF EXISTS `externallinks_userflags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `externallinks_userflags` (
  `user_id` int(10) unsigned NOT NULL,
  `wiki` varchar(45) NOT NULL,
  `user_flag` varchar(255) NOT NULL,
  KEY `USERID` (`wiki`,`user_id`),
  KEY `FLAGS` (`user_flag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `externallinks_userflags`
--

LOCK TABLES `externallinks_userflags` WRITE;
/*!40000 ALTER TABLE `externallinks_userflags` DISABLE KEYS */;
/*!40000 ALTER TABLE `externallinks_userflags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `externallinks_userlog`
--

DROP TABLE IF EXISTS `externallinks_userlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `externallinks_userlog` (
  `log_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `wiki` varchar(45) NOT NULL,
  `locale` varchar(45) NOT NULL,
  `log_type` varchar(45) NOT NULL,
  `log_action` varchar(45) NOT NULL,
  `log_object` bigint(12) NOT NULL,
  `log_object_text` blob DEFAULT NULL,
  `log_from` blob DEFAULT NULL,
  `log_to` blob DEFAULT NULL,
  `log_user` int(11) NOT NULL,
  `log_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `log_reason` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`log_id`),
  KEY `WIKI` (`wiki`),
  KEY `LOCALE` (`locale`),
  KEY `LOGTYPE` (`log_type`),
  KEY `LOGACTION` (`log_action`),
  KEY `LOGOBJECT` (`log_object`),
  KEY `LOGUSER` (`log_user`),
  KEY `LOGTIMESTAMP` (`log_timestamp`),
  KEY `LOGSPECIFIC` (`log_type`,`log_action`,`log_object`,`log_user`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `externallinks_userlog`
--

LOCK TABLES `externallinks_userlog` WRITE;
/*!40000 ALTER TABLE `externallinks_userlog` DISABLE KEYS */;
INSERT INTO `externallinks_userlog` VALUES (1,'testwiki','testwiki','tos','accept',0,'',NULL,NULL,1,'2021-06-16 23:40:57',''),(2,'testwiki','testwiki','wikiconfig','change',0,'',NULL,NULL,1,'2021-06-17 00:05:39',''),(3,'testwiki','testwiki','wikiconfig','change',0,'',NULL,NULL,1,'2021-06-17 00:06:13',''),(4,'global','testwiki','usergroups','define',0,'',NULL,NULL,1,'2021-06-17 00:07:43','');
/*!40000 ALTER TABLE `externallinks_userlog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `externallinks_userpreferences`
--

DROP TABLE IF EXISTS `externallinks_userpreferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `externallinks_userpreferences` (
  `user_link_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_email` blob DEFAULT NULL,
  `user_email_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `user_email_confirm_hash` varchar(32) DEFAULT NULL,
  `user_email_fpreport` tinyint(1) NOT NULL DEFAULT 0,
  `user_email_blockstatus` tinyint(1) NOT NULL DEFAULT 1,
  `user_email_permissions` tinyint(1) NOT NULL DEFAULT 1,
  `user_email_fpreportstatusfixed` tinyint(1) NOT NULL DEFAULT 1,
  `user_email_fpreportstatusdeclined` tinyint(1) NOT NULL DEFAULT 1,
  `user_email_fpreportstatusopened` tinyint(1) NOT NULL DEFAULT 1,
  `user_email_bqstatuscomplete` tinyint(1) NOT NULL DEFAULT 1,
  `user_email_bqstatuskilled` tinyint(1) NOT NULL DEFAULT 1,
  `user_email_bqstatussuspended` tinyint(1) NOT NULL DEFAULT 1,
  `user_email_bqstatusresume` tinyint(1) NOT NULL DEFAULT 1,
  `user_new_tab_one_tab` tinyint(1) NOT NULL DEFAULT 1,
  `user_allow_analytics` tinyint(1) NOT NULL DEFAULT 0,
  `user_default_wiki` varchar(45) DEFAULT NULL,
  `user_default_language` varchar(45) DEFAULT NULL,
  `user_default_theme` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`user_link_id`),
  KEY `HASEMAIL` (`user_email_confirmed`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `externallinks_userpreferences`
--

LOCK TABLES `externallinks_userpreferences` WRITE;
/*!40000 ALTER TABLE `externallinks_userpreferences` DISABLE KEYS */;
INSERT INTO `externallinks_userpreferences` VALUES (1,NULL,0,NULL,0,1,1,1,1,1,1,1,1,1,1,0,NULL,NULL,NULL);
/*!40000 ALTER TABLE `externallinks_userpreferences` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2021-06-16 17:20:43
