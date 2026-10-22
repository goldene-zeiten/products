:navigation-title: Events

..  include:: /Includes.rst.txt
..  _mcp-events:

======
Events
======

The endpoint dispatches PSR-14 events so that an integrator can change what an assistant sees without replacing
a tool. Register a listener with the :php:`#[AsEventListener]` attribute. The events are dispatched on every
request to the endpoint, with the site and request of that request in :php:`getContext()`, so a listener can
decide per site.

..  contents:: Table of contents
    :local:

..  _developer-events-modify-tools:

ModifyMcpToolsEvent
===================

Dispatched when the endpoint builds the list of tools. :php:`getTools()` returns every registered tool,
:php:`addTool()` adds one (replacing a tool of the same name) and :php:`removeTool()` hides one by its name.
A hidden tool is neither listed nor callable.

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class HideShippingQuotes
    {
        public function __invoke(ModifyMcpToolsEvent $event): void
        {
            $event->removeTool('get_shipping_options');
            $event->addTool(new StoreLocatorTool());
        }
    }

..  _developer-events-modify-resources:

ModifyMcpResourcesEvent
=======================

Dispatched when the endpoint builds the list of resources. :php:`getResources()`, :php:`addResource()` and
:php:`removeResource()` work like the methods of the tools event, with the resource URI as the identity.

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class OfferTheSizeGuide
    {
        public function __invoke(ModifyMcpResourcesEvent $event): void
        {
            $event->addResource(new SizeGuideResource());
        }
    }

..  _developer-events-after-tool-called:

AfterMcpToolCalledEvent
=======================

Dispatched after a tool has returned. :php:`getToolName()` and :php:`getArguments()` tell which call it was,
:php:`getResult()` is the array the tool returned and :php:`setResult()` replaces it; the assistant receives
what the last listener set. Use it to add data to an answer or to record calls for tracking. A call that failed
with a tool error does not dispatch the event.

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class TrackSearches
    {
        public function __construct(private readonly SearchLog $log) {}

        public function __invoke(AfterMcpToolCalledEvent $event): void
        {
            if ($event->getToolName() !== 'search_products') {
                return;
            }
            $this->log->record($event->getArguments());
            $event->setResult([...$event->getResult(), 'supportPhone' => '+49 30 1234567']);
        }
    }
