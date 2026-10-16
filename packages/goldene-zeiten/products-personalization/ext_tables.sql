CREATE TABLE tx_productspersonalization_domain_model_option (
    product int(11) unsigned DEFAULT '0' NOT NULL,
    article int(11) unsigned DEFAULT '0' NOT NULL,

    KEY product (product),
    KEY article (article)
);

CREATE TABLE tx_productspersonalization_domain_model_optionitem (
    parent_option int(11) unsigned DEFAULT '0' NOT NULL,

    KEY parent_option (parent_option)
);
