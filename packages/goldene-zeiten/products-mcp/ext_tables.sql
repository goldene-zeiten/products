CREATE TABLE tx_productsmcp_session (
    ses_id varchar(190) DEFAULT '' NOT NULL,
    ses_iplock varchar(39) DEFAULT '' NOT NULL,
    ses_userid int(11) unsigned DEFAULT '0' NOT NULL,
    ses_tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    ses_data mediumblob,
    ses_permanent tinyint(1) unsigned DEFAULT '0' NOT NULL,

    PRIMARY KEY (ses_id),
    KEY ses_tstamp (ses_tstamp, ses_userid)
);

CREATE TABLE tx_productsmcp_basket (
    ses_id varchar(190) DEFAULT '' NOT NULL,
    ses_iplock varchar(39) DEFAULT '' NOT NULL,
    ses_userid int(11) unsigned DEFAULT '0' NOT NULL,
    ses_tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    ses_data mediumblob,
    ses_permanent tinyint(1) unsigned DEFAULT '0' NOT NULL,

    PRIMARY KEY (ses_id),
    KEY ses_tstamp (ses_tstamp, ses_userid)
);
