CREATE TABLE tx_products_domain_model_product (
    price varchar(255) DEFAULT '0.00' NOT NULL,
    categories int(11) unsigned DEFAULT '0' NOT NULL,
    articles int(11) unsigned DEFAULT '0' NOT NULL
);

CREATE TABLE tx_products_domain_model_article (
    price varchar(255) DEFAULT '0.00' NOT NULL
);


CREATE TABLE tx_products_domain_model_order (
    total_net int(11) DEFAULT '0' NOT NULL,
    total_tax int(11) DEFAULT '0' NOT NULL,
    total_gross int(11) DEFAULT '0' NOT NULL,
    tax_breakdown text,
    status_log text,
    items int(11) unsigned DEFAULT '0' NOT NULL,
    legacy_order_data text
);

CREATE TABLE tx_products_domain_model_orderitem (
    unit_price_net int(11) DEFAULT '0' NOT NULL,
    unit_price_gross int(11) DEFAULT '0' NOT NULL,
    line_total_net int(11) DEFAULT '0' NOT NULL,
    line_total_tax int(11) DEFAULT '0' NOT NULL,
    line_total_gross int(11) DEFAULT '0' NOT NULL,
    options text
);

CREATE TABLE tx_products_domain_model_paymenttransaction (
    amount int(11) DEFAULT '0' NOT NULL,
    raw_data text
);

CREATE TABLE tx_products_number_range (
    scope varchar(255) DEFAULT '' NOT NULL,
    current_value int(11) DEFAULT '0' NOT NULL,

    PRIMARY KEY (scope)
);

CREATE TABLE tx_products_migration_map (
    legacy_table varchar(255) DEFAULT '' NOT NULL,
    legacy_uid int(11) DEFAULT '0' NOT NULL,
    local_table varchar(255) DEFAULT '' NOT NULL,
    local_uid int(11) DEFAULT '0' NOT NULL,

    UNIQUE KEY legacy (legacy_table, legacy_uid)
);

CREATE TABLE be_users (
    tx_products_category_mounts varchar(255) DEFAULT '' NOT NULL
);

CREATE TABLE be_groups (
    tx_products_category_mounts varchar(255) DEFAULT '' NOT NULL
);
