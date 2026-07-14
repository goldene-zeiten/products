<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Backend\Form;

use GoldeneZeiten\Products\Catalog\ProductListModeRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Adds the registered product-list modes to the choices an editor sees on the product-list content
 * element, so a listing a feature registered can be placed like the built-in ones. Without this the
 * registered modes would work when stored but never be offered.
 *
 * Public, because FormEngine instantiates an itemsProcFunc through makeInstance, which only injects
 * dependencies into a service marked public.
 */
#[Autoconfigure(public: true)]
final class ProductListModeItemsProvider
{
    public function __construct(
        private readonly ProductListModeRegistry $registry
    ) {}

    /**
     * @param array{items: array<int, array<string, mixed>>} $parameters
     */
    public function populate(array &$parameters): void
    {
        foreach ($this->registry->getSelectItems() as $item) {
            $parameters['items'][] = ['label' => $item['label'], 'value' => $item['value']];
        }
    }
}
