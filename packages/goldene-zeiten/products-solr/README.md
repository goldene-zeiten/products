# TYPO3 extension `products_solr`

> **This repository is READ-ONLY.**
> It is split automatically out of the [goldene-zeiten/products](https://github.com/goldene-zeiten/products)
> monorepo, which is the single source of truth. Pull requests and commits made
> here are overwritten by the next split — please open them in the monorepo instead.

Apache Solr backed product search for the
[Products](https://github.com/goldene-zeiten/products-core) shop system: a faster, more
scalable alternative to the MySQL `LIKE` search of `products_search`. It reuses
[EXT:solr](https://github.com/TYPO3-Solr/ext-solr)'s indexing and search plugins with a
ready-to-use default configuration for the product catalog — full-text search over title,
subtitle, SKU, EAN and description, plus category, attribute and price-range facets.

This extension is **self-contained**: it does not depend on, and is not meant to run
alongside, `products_search`. Install one search add-on or the other.

## Installation

```shell
composer require goldene-zeiten/products-solr
```

Then:

1. Install and configure an Apache Solr server via EXT:solr (the official
   `typo3solr/ext-solr` Docker image ships the required cores).
2. Add the **Products Solr Search** site set to your site and configure the Solr
   connection in the site settings.
3. Initialize the Solr connection and run the EXT:solr Index Queue to index your products.
4. Place the **Products Solr Search** plugin on a page.

Every shipped default (connection, index-queue field mapping, facets) is an overridable
site setting or TypoScript value — adjust it the same way you would any EXT:solr setup.

## Requirements

- TYPO3 13.4 LTS (EXT:solr 13.1, Apache Solr 9) or TYPO3 14.3 LTS (EXT:solr 14, Apache Solr 10)
- PHP 8.2 or newer
- `goldene-zeiten/products-core`
- `apache-solr-for-typo3/solr`

## Database

We recommend running `products-solr` on **MySQL or MariaDB**. EXT:solr's Index Queue currently targets the
MySQL family, and on PostgreSQL the queue is not populated — so the indexing step does not run there. Apache
Solr serves the search itself regardless of your TYPO3 database, but because indexing needs MySQL/MariaDB,
a MySQL/MariaDB setup is the smoothest for shops using this add-on. (For the same reason, a small part of
our PostgreSQL test suite is skipped rather than asserted.)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
