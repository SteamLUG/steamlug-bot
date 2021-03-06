CREATE DATABASE `steamlug-bot`;
USE `steamlug-bot`;
CREATE TABLE `tweets` (tweet_id BIGINT UNSIGNED UNIQUE NOT NULL, tweet_date DATETIME NOT NULL, tweet_text VARCHAR(500) NOT NULL, tweet_user VARCHAR(500) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE `settings` (setting_key VARCHAR(200) UNIQUE NOT NULL, setting_value VARCHAR(200) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;
INSERT INTO `settings` VALUES ('last_tweet_mentioned', '0');
CREATE TABLE `log` (log_id BIGINT UNSIGNED UNIQUE NOT NULL AUTO_INCREMENT, log_nick VARCHAR(500) NOT NULL, log_ident VARCHAR(500) NOT NULL, log_host VARCHAR(500) NOT NULL, log_command VARCHAR(500) NOT NULL, log_channel VARCHAR(500), log_person VARCHAR(500), log_identified INT(1), log_text VARCHAR(500), log_datetime DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE `news` (news_id BIGINT UNSIGNED UNIQUE NOT NULL AUTO_INCREMENT, news_group VARCHAR(500) NOT NULL, news_title VARCHAR(500) NOT NULL, news_text VARCHAR(10000) NOT NULL, news_link VARCHAR(500) NOT NULL, news_date DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;
INSERT INTO `settings` VALUES ('last_news_mentioned', '0');
CREATE TABLE `events` (event_id BIGINT UNSIGNED UNIQUE NOT NULL AUTO_INCREMENT, event_category VARCHAR(500) NOT NULL, event_title VARCHAR(500) NOT NULL, event_text VARCHAR(10000) NOT NULL, event_link VARCHAR(500) NOT NULL, event_guid VARCHAR(500) NOT NULL, event_pubdate DATETIME NOT NULL, event_date DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;
INSERT INTO `settings` VALUES ('last_event_mentioned', '0');
CREATE TABLE `customurl` (customurl_id BIGINT UNSIGNED UNIQUE NOT NULL AUTO_INCREMENT, customurl_nick VARCHAR(100) UNIQUE NOT NULL, customurl_url VARCHAR(500) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE `askcustomurl` (askcustomurl_id BIGINT UNSIGNED UNIQUE NOT NULL AUTO_INCREMENT, askcustomurl_nick VARCHAR(100) UNIQUE NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;
INSERT INTO `customurl` VALUES (NULL, 'homerj', 'none');
CREATE TABLE `newreleases` (newrelease_id VARCHAR(10) UNIQUE NOT NULL, newrelease_type VARCHAR(10) NOT NULL, newrelease_name VARCHAR(500) NOT NULL, newrelease_fullgame VARCHAR(500), newrelease_windows INT(1) NOT NULL, newrelease_mac INT(1) NOT NULL, newrelease_linux INT(1) NOT NULL, newrelease_said INT(1) NOT NULL, newrelease_datetime DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE `newreleases_temp` (newrelease_id VARCHAR(10) UNIQUE NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE `getlog` (getlog_id BIGINT UNSIGNED UNIQUE NOT NULL AUTO_INCREMENT, getlog_text VARCHAR(500) NOT NULL, getlog_datetime DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE `humbletitles` (humbletitles_id BIGINT UNSIGNED UNIQUE NOT NULL AUTO_INCREMENT, humbletitles_weekly INT(1) NOT NULL, humbletitles_title VARCHAR(500) NOT NULL, humbletitles_date DATETIME NOT NULL, humbletitles_said INT(1) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE `messages` (message_id BIGINT UNSIGNED UNIQUE NOT NULL AUTO_INCREMENT, message_nickfrom VARCHAR(500) NOT NULL, message_nickto VARCHAR(500) NOT NULL, message_text VARCHAR(500) NOT NULL, message_date DATETIME NOT NULL, message_delivered INT(1) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE `xkcd` (xkcd_id BIGINT UNSIGNED UNIQUE NOT NULL AUTO_INCREMENT, xkcd_nr INT UNSIGNED NOT NULL, xkcd_title VARCHAR(500), xkcd_alt VARCHAR(10000)) ENGINE=InnoDB DEFAULT CHARSET=utf8;
