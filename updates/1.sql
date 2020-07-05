CREATE TABLE IF NOT EXISTS `system_backup_archives` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `path` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `created_user_id` int(11) DEFAULT NULL,
  `status_id` int(11) DEFAULT NULL,
  `comment` text,
  `error_message` text,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `system_backup_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `backup_path` varchar(255) DEFAULT NULL,
  `backup_interval` int(11) DEFAULT NULL,
  `num_files_to_keep` int(11) DEFAULT NULL,
  `notify_administrators` tinyint(4) DEFAULT NULL,
  `backup_on_login` tinyint(4) DEFAULT NULL,
  `archive_uploaded_dir` tinyint(4) DEFAULT '1',
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `system_backup_status` (
	`id` int(11) NOT NULL,
	`name` varchar(50) DEFAULT NULL,
	PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `system_colortheme_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `logo_border` tinyint(4) DEFAULT NULL,
  `header_text` varchar(100) DEFAULT NULL,
  `theme_id` varchar(30) DEFAULT NULL,
  `hide_footer_logos` tinyint(4) DEFAULT NULL,
  `footer_text` text,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `system_compound_email_vars` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) DEFAULT NULL,
  `content` text,
  `scope` varchar(50) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `code` (`code`),
  KEY `scope` (`scope`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `system_email_layouts` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`code` varchar(100) DEFAULT NULL,
	`content` text,
	`css` text,
	`name` varchar(255) DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `code` (`code`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `system_email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(100) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `content` text,
  `description` text,
  `is_system` tinyint(4) DEFAULT NULL,
  `reply_to_mode` varchar(10) DEFAULT 'default',
  `reply_to_address` varchar(100) DEFAULT NULL,
  `allow_recipient_block` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `code` (`code`)
) CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `system_htmleditor_config` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`code` varchar(100) DEFAULT NULL,
	`controls_row_1` text,
	`controls_row_2` text,
	`controls_row_3` text,
	`content_css` varchar(255) DEFAULT NULL,
	`block_formats` text,
	`custom_styles` text,
	`font_sizes` text,
	`font_colors` text,
	`background_colors` text,
	`allow_more_colors` tinyint(4) DEFAULT NULL,
	`module` varchar(100) DEFAULT NULL,
	`description` text,
	`valid_elements` text,
	`valid_child_elements` text,
	`default_height` varchar(11) DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `code_module` (`code`,`module`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `system_login_log` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`user_id` int(11) DEFAULT NULL,
	`created_at` datetime DEFAULT NULL,
	`ip` varchar(45) DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `user_id` (`user_id`),
	KEY `created_at` (`created_at`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `system_mail_settings` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`smtp_address` varchar(255) DEFAULT NULL,
	`smtp_authorization` tinyint(4) DEFAULT NULL,
	`smtp_user` text,
	`smtp_password` text,
	`sender_name` varchar(50) DEFAULT NULL,
	`sender_email` varchar(50) DEFAULT NULL,
	`smtp_port` int(11) DEFAULT NULL,
	`smtp_ssl` tinyint(4) DEFAULT NULL,
	`send_mode` varchar(20) DEFAULT NULL,
	`sendmail_path` varchar(255) DEFAULT NULL,
	`templating_engine` varchar(50) DEFAULT 'php',
	`allow_recipient_blocking` tinyint(4) DEFAULT NULL,
	PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8;


INSERT INTO system_mail_settings(sender_name) VALUES ('LateralStore');
INSERT INTO system_colortheme_settings(theme_id) VALUES ('blue');
INSERT INTO system_backup_status(id, name) VALUES (1, 'In progress...');
INSERT INTO system_backup_status(id, name) VALUES (2, 'OK');
INSERT INTO system_backup_status(id, name) VALUES (3, 'Error');
INSERT INTO system_backup_settings(id) VALUES (0);
INSERT INTO `system_email_layouts` (`id`,`code`,`content`,`css`,`name`) VALUES ('1','system','<html xmlns=\"http://www.w3.org/1999/xhtml\">\n  <head>\n    <title></title>\n    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"/>\n    <meta http-equiv=\"Content-Language\" content=\"en-us\"/>\n    <style type=\"text/css\" media=\"screen\">\n      <?= $this->css ?>\n    </style>\n  </head>\n  <body>\n    <?= $message ?>\n    <hr/>\n    <p>This is an automatic message. Do not reply to it.</p>\n  </body>\n</html>\n','table.simpleList\n{\n  font: normal 11px/150% Arial, Verdana, sans-serif;\n  border-collapse: collapse;\n  width: 100%;\n}\n\ntable.simpleList th, table.simpleList td\n{\n  padding: 0.4em 0.5em;\n  text-align: left;\n  border-right: 1px solid #ddd;\n  vertical-align: top;\n}\n\ntable.simpleList tr td.last,\ntable.simpleList tr th.last\n{\n  border-right: none!important;\n}\n\ntable.simpleList tbody tr:last-child td\n{\n  border-bottom: 1px solid #ccc;\n}\n\ntable.simpleList thead th, table.simpleList thead td\n{\n  background: #e5e5e5;\n  font-weight: normal;\n  color: #000;\n  border-right-color: #d0d0d0;\n  border-top: 2px solid #666;\n  border-bottom: 1px solid #d0d0d0;\n  white-space: nowrap;\n}\n\ntable.simpleList tbody td, table.simpleList tbody th\n{\n  border-bottom: 1px solid #eaeaea;\n  color: #333;\n  background-color: #fff;\n}\n\ntable.simpleList tr.even td, table.simpleList tr.even th\n{\n  background-color: #f5f5f5;\n}\n\ntable.simpleList th.number, table.simpleList td.number, \ntable.simpleList th.float, table.simpleList td.float, \ntable.simpleList th.datetime, table.simpleList td.datetime, \ntable.simpleList th.date, table.simpleList td.date, \ntable.simpleList th.bool, table.simpleList td.bool, \ntable.simpleList th.time, table.simpleList td.time\n{\n  text-align: right!important;\n  white-space: nowrap;\n}\n\n/*\n *\n */','System');
INSERT INTO `system_email_layouts` (`id`,`code`,`content`,`css`,`name`) VALUES ('2','external','<html xmlns=\"http://www.w3.org/1999/xhtml\">\n  <head>\n    <title></title>\n    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"/>\n    <meta http-equiv=\"Content-Language\" content=\"en-us\"/>\n    <style type=\"text/css\" media=\"screen\">\n      <?= $this->css ?>\n    </style>\n  </head>\n  <body>\n    <?= $message ?>\n  </body>\n</html>\n','table.simpleList\n{\n  font: normal 11px/150% Arial, Verdana, sans-serif;\n  border-collapse: collapse;\n  width: 100%;\n}\n\ntable.simpleList th, table.simpleList td\n{\n  padding: 0.4em 0.5em;\n  text-align: left;\n  border-right: 1px solid #ddd;\n  vertical-align: top;\n}\n\ntable.simpleList tr td.last,\ntable.simpleList tr th.last\n{\n  border-right: none!important;\n}\n\ntable.simpleList tbody tr:last-child td\n{\n  border-bottom: 1px solid #ccc;\n}\n\ntable.simpleList thead th, table.simpleList thead td\n{\n  background: #e5e5e5;\n  font-weight: normal;\n  color: #000;\n  border-right-color: #d0d0d0;\n  border-top: 2px solid #666;\n  border-bottom: 1px solid #d0d0d0;\n  white-space: nowrap;\n}\n\ntable.simpleList tbody td, table.simpleList tbody th\n{\n  border-bottom: 1px solid #eaeaea;\n  color: #333;\n  background-color: #fff;\n}\n\ntable.simpleList tr.even td, table.simpleList tr.even th\n{\n  background-color: #f5f5f5;\n}\n\ntable.simpleList th.number, table.simpleList td.number, \ntable.simpleList th.float, table.simpleList td.float, \ntable.simpleList th.datetime, table.simpleList td.datetime, \ntable.simpleList th.date, table.simpleList td.date, \ntable.simpleList th.bool, table.simpleList td.bool, \ntable.simpleList th.time, table.simpleList td.time\n{\n  text-align: right!important;\n  white-space: nowrap;\n}\n\n/*\n *\n */','External');