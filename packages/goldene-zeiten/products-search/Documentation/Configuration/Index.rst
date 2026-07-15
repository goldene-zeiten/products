:navigation-title: Configuration

..  include:: /Includes.rst.txt
..  _configuration:

=============
Configuration
=============

..  contents:: Table of contents
    :local:

..  _configuration-site-set:

Site set
========

Activate the :guilabel:`Products Search` site set (``goldene-zeiten/products-search``) on every site
that should offer search, then adjust its setting under :guilabel:`Site Management > Sites > Edit
settings`.

..  confval:: products.search.resultsPerPage
    :type: int
    :Default: 20

    Results shown per page of a :guilabel:`Free text search` result list. Values below 1 are
    treated as 1. Only the free-text search mode paginates at all — see
    :ref:`Search Browse Mode <configuration-browse-mode>` below; every other browse mode returns
    its full, unpaginated result (or, for :guilabel:`Most recent entries`, a fixed 10 records).

..  _configuration-content-element:

Content element fields
========================

The :guilabel:`Search` plugin (``CType`` ``productssearch_search``) has three fields of its own,
found on the content element's :guilabel:`Plugin` tab.

..  _configuration-browse-mode:

Search Browse Mode
--------------------

..  confval:: tx_products_search_browse_mode
    :type: select
    :Default: text

    Selects which of the two engines described in :ref:`Introduction <introduction-two-engines>`
    renders the element, and which of six modes it runs. Six values are offered:

**text** (label :guilabel:`Free text search`, the default)
    Runs :php:`SearchService`: the visitor's typed search term is matched, as a substring, against
    a product's title, subtitle, item number, description and EAN (see
    :ref:`Substring matching, not a full-text engine <introduction-not-full-text>`). Always
    searches products, whatever :ref:`Search Target <configuration-search-target>` is set to.
    Results are paginated at `products.search.resultsPerPage`. :ref:`Search Field
    <configuration-search-field>` is not used by this mode.

    *Example:* a visitor types ``shoe`` into the search box. A product titled
    ``"Running Shoes"`` and one titled ``"Comfort Shoehorn"`` both match (the term appears as a
    substring of the title in both), independent of word boundaries or capitalisation.

**firstletter** (label :guilabel:`Browse by first letter`)
    Runs :php:`FacetedBrowseService`: groups every entity of :ref:`Search Target
    <configuration-search-target>` by the **first character** of its :ref:`Search Field
    <configuration-search-field>` value (uppercased), producing an A-Z index. An entity whose
    resolved field value is empty is grouped under ``#``. Groups are sorted alphabetically. All
    matching entities are returned — there is no pagination and no result cap.

    *Example:* Search Target ``products``, Search Field left empty (falls back to ``title``):
    ``"Running Shoes"`` and ``"Red Sneakers"`` are grouped under ``R``, ``"Espresso Cup"`` under
    ``E``.

**year** (label :guilabel:`Browse by year`)
    Also runs :php:`FacetedBrowseService`, grouping by the **year** of the resolved
    :ref:`Search Field <configuration-search-field>` value, newest year first. This only produces
    meaningful groups when the resolved field is actually a date — in practice this means Search
    Target ``products`` with Search Field explicitly set to ``crdate`` (the record's creation
    date), since ``crdate`` is not an accepted Search Field value for the ``articles`` or
    ``categories`` targets (see the accepted values listed under
    :ref:`Search Field <configuration-search-field>`).

    ..  warning::
        With any other resolved field (including the ``title`` fallback used when Search Field is
        left empty or invalid), every entity is a non-date value and lands in a single
        ``"unknown"`` group instead of being split by year. Set Search Field to ``crdate``
        explicitly to get a real year index.

    *Example:* Search Target ``products``, Search Field ``crdate``: groups ``"2026"`` (12
    products created this year), then ``"2025"`` (40 products), newest first.

**field** (label :guilabel:`Browse by field value`)
    Groups every entity of :ref:`Search Target <configuration-search-target>` by the **exact**
    value of :ref:`Search Field <configuration-search-field>` (not a substring match), sorted
    alphabetically by that value. Unlike free-text search this is an equality grouping, so a field
    with mostly-unique values (e.g. item number) produces mostly one-entity groups.

    *Example:* Search Target ``products``, Search Field ``itemNumber``: one group per distinct
    item number, each containing the (usually single) product with that exact number.

**keyfield** (label :guilabel:`Browse by keyword multi-select`)
    Offers the visitor a multi-select of every **distinct** value of :ref:`Search Field
    <configuration-search-field>` across all entities of :ref:`Search Target
    <configuration-search-target>` (sorted, computed once per request). Submitting the form
    returns every entity whose resolved field value matches **any** of the selected values (a
    logical OR across the selection, not AND) — selecting several values widens the result, it
    never narrows it further.

    *Example:* Search Target ``articles``, Search Field ``itemNumber``: the visitor is offered
    every distinct article item number to tick; ticking two shows every article carrying either
    number.

**lastentries** (label :guilabel:`Most recent entries`)
    Lists the most recently created entities of :ref:`Search Target
    <configuration-search-target>` first, ignoring :ref:`Search Field <configuration-search-field>`
    entirely. Capped at a fixed **10** records — this limit is hardcoded, not configurable via any
    setting. Not paginated (there is nothing beyond the 10 to page through).

    ..  note::
        Any browse-mode value other than the six above (including an empty or corrupted TCA
        value) is treated the same as ``text`` — the content element falls back to free-text
        search rather than erroring.

..  _configuration-search-target:

Search Target
---------------

..  confval:: tx_products_search_target
    :type: select
    :Default: products

    Chooses which repository the five **browse modes** (everything except :guilabel:`Free text
    search`) read from: ``products``, ``articles`` or ``categories``. Browsing ignores each
    entity's storage page — every product/article/category in the installation is a candidate,
    not just those on one page.

    ..  note::
        This field has **no effect** while :ref:`Search Browse Mode
        <configuration-browse-mode>` is ``text``. Free-text search always searches products; there
        is no way to point it at articles or categories instead. See
        :ref:`Two independent engines behind one plugin <introduction-two-engines>`.

..  _configuration-search-field:

Search Field
--------------

..  confval:: tx_products_search_field
    :type: string
    :Default: (empty)

    A plain free-text input (the backend form does not restrict it to a dropdown) naming the
    property the **browse modes** group or filter by — used by :guilabel:`firstletter`,
    :guilabel:`year`, :guilabel:`field` and :guilabel:`keyfield`, and ignored by
    :guilabel:`lastentries` and by the default :guilabel:`text` (free-text search) mode.

    At runtime the typed value is checked against a fixed allow-list for the currently selected
    :ref:`Search Target <configuration-search-target>`. An empty value, or one that is not on that
    target's list, silently falls back to ``title`` — no error or warning is shown to the editor.
    Accepted values per target:

    *   :guilabel:`Products` — ``title``, ``itemNumber`` or ``crdate``
    *   :guilabel:`Articles` — ``title`` or ``itemNumber``
    *   :guilabel:`Categories` — ``title`` only

    ..  tip::
        ``crdate`` is only ever accepted for the ``products`` target. This is why
        :ref:`Browse by year <configuration-browse-mode>` only produces real year buckets for
        products — it is the only target/field combination that resolves to an actual date value.
