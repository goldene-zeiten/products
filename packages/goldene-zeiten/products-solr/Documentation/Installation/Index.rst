:navigation-title: Installation

..  include:: /Includes.rst.txt
..  _installation:

============
Installation
============

..  contents:: Table of contents
    :local:

..  _installation-requirements:

Requirements
============

*   TYPO3 13.4 LTS (with EXT:solr 13.1 and Apache Solr 9) or TYPO3 14.3 (with EXT:solr 14 and Apache
    Solr 10)
*   PHP 8.2, 8.3, 8.4 or 8.5
*   EXT:products_core (``goldene-zeiten/products-core``), for the shop and its product records
*   EXT:solr (``apache-solr-for-typo3/solr``), which this extension configures
*   A reachable Apache Solr server. The official ``typo3solr/ext-solr`` Docker image ships the cores EXT:solr
    expects.

..  note::
    We recommend a **MySQL or MariaDB** database for shops using this extension. EXT:solr's Index Queue
    currently targets the MySQL family, and on PostgreSQL the queue is not populated — so the product
    indexing step does not run there. Apache Solr serves the search itself regardless of the TYPO3 database,
    but because indexing needs MySQL/MariaDB, a MySQL/MariaDB setup is the smoothest one to run
    ``products-solr`` on. (A small part of this extension's PostgreSQL test coverage is skipped for the same
    reason.)

..  _installation-composer:

Installation with Composer
===========================

..  code-block:: bash

    composer require goldene-zeiten/products-solr

This pulls in ``apache-solr-for-typo3/solr`` (EXT:solr), whose indexing and search plugins this extension
builds on — nothing further needs to be required by hand.

..  _installation-solr-server:

Connecting a Solr server
========================

EXT:solr needs an Apache Solr server to talk to. In development the official Docker image ships the required
cores:

..  code-block:: bash

    docker run -p 8983:8983 typo3solr/ext-solr:13.1

Match the image tag to your TYPO3 major: Solr 9 for TYPO3 13.4 (EXT:solr 13.1), Solr 10 for TYPO3 14.3
(EXT:solr 14).

..  _installation-site-set:

Activation
==========

#.  Add the :guilabel:`Products Solr Search` site set (``goldene-zeiten/products-solr``) to the site that
    should offer Solr search, under :guilabel:`Site Management > Sites`.
#.  Configure the Solr connection on the site's :guilabel:`Solr` tab: the read host, port and path
    (``solr_host_read`` / ``solr_port_read`` / ``solr_path_read``) and the per-language core
    (``solr_core_read``).
#.  Initialize the Solr connection (EXT:solr's :guilabel:`Search > Connections` backend module, or
    ``typo3 solr:initializeconnections``).
#.  Run the EXT:solr Index Queue (the :guilabel:`Search > Index Queue` backend module, or the EXT:solr
    scheduler tasks) to index the product records.

Once the queue has run, products are searchable. See :ref:`Configuration <configuration>` for what the site
set ships and how to adjust it, and :ref:`Users Manual <users-manual>` for placing the search plugin on a
page.
