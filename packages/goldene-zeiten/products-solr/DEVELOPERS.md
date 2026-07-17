# Developing `products_solr`

Internal notes for contributors. User-facing documentation (integrators, editors, extension points) lives
in `Documentation/`; this file is about how the extension works and how to work on it.

## Architecture

The extension is **configuration-first**. It does not implement a search engine, an indexer or a search
plugin — it reuses EXT:solr's Index Queue and search plugins wholesale and ships a ready-made product
configuration on top of them. The bulk of the package is therefore TypoScript (`Configuration/TypoScript/`
and the `Configuration/Sets/Solr/` site set), Fluid partials, and language labels — not PHP.

The **only PHP** is one Index Queue helper, `Indexing/ProductIndexFieldMapper` (autoconfigured `public: true`
so it is reachable as a `USER` cObject userFunc). It exists solely to compute the two facet values a flat
TypoScript mapping cannot:

- `attributeValues()` — the multi-hop attribute traversal `Product -> Article -> AttributeValue ->
  Attribute`, emitting `"Attribute: Value"` labels for the `attribute_stringM` field.
- `categoryPaths()` — the depth-prefixed category hierarchy paths (`0-/Root`, `1-/Root/Child`, …) for the
  `categoryPath_stringM` tree facet, walked up each category's parent chain in PHP.

Everything a flat relation *can* express stays in TypoScript: the flat category facet is a plain
`SOLR_RELATION`, the full-text `content` field a `SOLR_CONTENT` COA, the detail URL a `typolink`.

## Index-queue field mapping rationale

The mapping (`plugin.tx_solr.index.queue.products.fields` in `setup.typoscript`) deliberately **reuses
EXT:solr's shipped default schema fields** wherever one exists — `title`, `subTitle`, `description`,
`content`, `category`, `price`, `url`, `image` — and only falls back to a **dynamic field**
(`itemNumber_stringS`, `ean_stringS`, `*_boolS`, `attribute_stringM`, `categoryPath_stringM`) where the
default schema has none.

The point of this is that **the Solr server configset is never modified**: we index into fields the default
EXT:solr configset already ships, so a stock `typo3solr/ext-solr` core works with no schema edits, no custom
configset to distribute, and no drift to keep in sync across Solr majors. A contributor adding a new indexed
value should reach for an existing default field first, and a matching dynamic-field suffix
(`_stringS`/`_boolS`/`_stringM`/…) only when there is genuinely no default field — never a new static schema
field.

The detail `url` is built from `{$plugin.tx_productscore.settings.pids.detailPage}` and the same
`ProductDetail` plugin arguments the core catalog uses, so indexed links resolve to the shop's real product
slugs via the site's route enhancers (EXT:solr runs the typolink inside its own indexing request, so
enhancers apply on both v13 and v14).

## Testing

There is **no live Solr in the functional suite**. Functional tests drive EXT:solr's Index Queue and then
inspect the `tx_solr_indexqueue_item` table directly — asserting that product records are enqueued (and
re-enqueued on change / removed on delete) with the expected item type — rather than round-tripping through a
running Solr server. The `ProductIndexFieldMapper` helpers are exercised as plain services against CSV
fixtures of the product/article/attribute/category tables, asserting the resolved `|`-separated field value.

A **live Solr server appears only in acceptance tests**, where the full index-and-search path is exercised
end to end against a real `typo3solr/ext-solr` core.

## TYPO3 v13 / v14 pairing

EXT:solr is versioned in lockstep with TYPO3 and Apache Solr, so the two supported lanes pin different
EXT:solr releases:

- **TYPO3 13.4** → EXT:solr **13.1.3** (`apache-solr-for-typo3/solr:^13.1.3`), Apache Solr 9.
- **TYPO3 14.3** → EXT:solr **14.0.0-beta3** (`apache-solr-for-typo3/solr:14.0.*@beta`), Apache Solr 10.

The `composer.json` constraint is `^13.1.3 || 14.0.*@beta`; keep the acceptance Solr image tag matched to
the lane under test (Solr 9 vs Solr 10) — the default schema field names this extension relies on are stable
across the two, but the server images are not interchangeable.
