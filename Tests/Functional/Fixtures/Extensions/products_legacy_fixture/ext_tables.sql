CREATE TABLE tt_products (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	sorting int(11) DEFAULT '0' NOT NULL,
	deleted tinyint(3) DEFAULT '0' NOT NULL,
	hidden tinyint(4) DEFAULT '0' NOT NULL,
	title tinytext,
	subtitle mediumtext,
	slug varchar(2048),
	itemnumber varchar(120) DEFAULT '' NOT NULL,
	ean varchar(48) DEFAULT '' NOT NULL,
	price decimal(19,2) DEFAULT '0.00' NOT NULL,
	description mediumtext,
	category int(11) unsigned DEFAULT '0' NOT NULL,
	inStock int(11) DEFAULT '1' NOT NULL,
	basketminquantity decimal(19,2) DEFAULT '0.00' NOT NULL,
	basketmaxquantity decimal(19,2) DEFAULT '0.00' NOT NULL,
	taxcat_id tinyint(3) unsigned DEFAULT '0',
	weight decimal(19,6) DEFAULT '0.000000' NOT NULL,
	offer int(11) DEFAULT '0' NOT NULL,
	highlight int(11) DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid)
);

CREATE TABLE tt_products_language (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	sorting int(11) DEFAULT '0' NOT NULL,
	sys_language_uid int(11) DEFAULT '0' NOT NULL,
	deleted tinyint(4) DEFAULT '0' NOT NULL,
	hidden tinyint(4) DEFAULT '0' NOT NULL,
	title tinytext,
	subtitle mediumtext,
	slug varchar(2048),
	itemnumber varchar(120) DEFAULT '' NOT NULL,
	prod_uid int(11) DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid)
);

CREATE TABLE tt_products_cat (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	sorting int(11) DEFAULT '0' NOT NULL,
	deleted tinyint(3) unsigned DEFAULT '0' NOT NULL,
	hidden tinyint(4) unsigned DEFAULT '0' NOT NULL,
	title tinytext,
	slug varchar(2048),
	note text,
	note2 text,
	parent_category int(11) DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid)
);

CREATE TABLE tt_products_cat_language (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	sorting int(11) DEFAULT '0' NOT NULL,
	sys_language_uid int(11) DEFAULT '0' NOT NULL,
	deleted tinyint(4) DEFAULT '0' NOT NULL,
	hidden tinyint(4) DEFAULT '0' NOT NULL,
	title tinytext,
	slug varchar(2048),
	note text,
	note2 text,
	cat_uid int(11) DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid)
);

CREATE TABLE tt_products_articles (
	uid int(11) DEFAULT '0' NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	sorting int(11) DEFAULT '0' NOT NULL,
	deleted tinyint(4) DEFAULT '0' NOT NULL,
	hidden tinyint(4) DEFAULT '0' NOT NULL,
	title varchar(80) DEFAULT '' NOT NULL,
	itemnumber varchar(120) DEFAULT '' NOT NULL,
	price decimal(19,2) DEFAULT '0.00' NOT NULL,
	inStock int(11) DEFAULT '1' NOT NULL,
	weight decimal(19,6) DEFAULT '0.000000' NOT NULL,
	uid_product int(11) DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid)
);

CREATE TABLE tt_products_articles_language (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	sorting int(11) DEFAULT '0' NOT NULL,
	sys_language_uid int(11) DEFAULT '0' NOT NULL,
	deleted tinyint(4) DEFAULT '0' NOT NULL,
	hidden tinyint(4) DEFAULT '0' NOT NULL,
	title varchar(80) DEFAULT '' NOT NULL,
	article_uid int(11) DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid)
);
