:navigation-title: Introduction

..  include:: /Includes.rst.txt
..  _introduction:

============
Introduction
============

Every REST-based third-party integration the shop needs — a shipping carrier's rating API, a
payment gateway's orders API — turns out to need the same three pieces of plumbing: an OAuth 2.0
client-credentials token exchange, PSR-18 request building with sane error handling, and a rule
for layering a system-wide extension configuration under a site's settings. Copying that
plumbing into every integration package would mean copying its bugs too, and copying its
inconsistencies (should a 400 be treated as an error? does the token cache key include the
environment?) along with it.

EXT:products_api_client carries exactly that plumbing and nothing else. It has no shop concepts
of its own — no order, no basket, no shipping rate — and is not meant to be installed on a site by
itself. It is a Composer dependency pulled in transitively by an integration package such as
``goldene-zeiten/products-shipping-ups`` (UPS live rating) or
``goldene-zeiten/products-payment-paypal`` (PayPal orders).

..  contents:: Table of contents
    :local:

What it provides
=================

Three services, each usable independently:

*   :php:`GoldeneZeiten\Products\ApiClient\Http\ApiHttpClient` — a thin PSR-18 wrapper that builds
    the request (JSON body, form body, or none), sends it, and turns a transport-level failure into
    a single :php:`ApiTransportException`. See :ref:`developer-http-client`.

*   :php:`GoldeneZeiten\Products\ApiClient\Authentication\OAuth2ClientCredentialsProvider` — performs
    the OAuth 2.0 client-credentials grant and caches the resulting bearer token until shortly before
    it expires. See :ref:`developer-oauth2`.

*   :php:`GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver` (together with
    :php:`CurrentSiteResolver`) — resolves a set of configuration values by layering the extension
    configuration under a site's settings, the pattern every carrier/gateway integration uses for its
    own credentials and endpoint settings. See :ref:`developer-configuration`.

What it does not provide
=========================

The library holds no per-vendor knowledge. It does not know what a "UPS rate" or a "PayPal order"
is, does not map HTTP status codes to business outcomes, and does not define any settings of its
own — an integration package defines its own :file:`ext_conf_template.txt` /
:file:`settings.definitions.yaml` fields and only reuses the *layering rule* from
:php:`ApiSettingsResolver`. It also does not own a token cache; see
:ref:`developer-oauth2-wiring` for why that is deliberate.

Who this is for
================

Everything in this documentation is aimed at the developer of an integration package, not at a
site editor — this library exposes no backend module, no site set, and no frontend-facing
behaviour of its own.
