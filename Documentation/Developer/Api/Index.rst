..  include:: /Includes.rst.txt
..  _developer-api:

=============
Provider APIs
=============

Some extension points in the Products extension use **interface-based contracts** rather than
events. These APIs define explicit implementations that must be registered by integrators — they
are not optional listeners but mandatory service implementations for specific integration tasks.

**Key difference from events:**

-   **Events** (PSR-14) let listeners react to shop lifecycle milestones, mutate lists, or log
    activity after the fact. Many listeners may respond; each is independent.
-   **Provider APIs** (interfaces) describe a service the shop depends on. The backend asks the
    service a specific question (e.g., "which order exporters are available?") and expects a
    deterministic answer. These APIs carry registration metadata — typically a Symfony
    ``#[AutoconfigureTag]`` attribute — so an integrator only needs to implement the interface;
    no manual configuration is required.

The APIs documented here cover the following patterns:

**Discovery & Resolution** — The shop needs to choose between multiple implementations or filter
a set based on availability (example: order exporters). The interface defines methods for
availability checks and priority, plus a registry service that collects and queries them.

**Table of Contents:**

..  toctree::
    :maxdepth: 1
    :titlesonly:

    OrderExport
