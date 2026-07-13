..  include:: /Includes.rst.txt
..  _developer-events-catalog:

=======
Catalog
=======

Events fired during catalog operations and category tree building.

ModifyProductListEvent
----------------------

Lets integrators adjust the product list before rendering - filter/reorder the product list,
hide out-of-region items, pin promotions, or inject cross-sells.

Mutable: Yes (via ``setProducts(Product[] $products)``)

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class FilterProductsByRegion
    {
        public function __invoke(ModifyProductListEvent $event): void
        {
            $products = $event->getProducts();
            // Filter, reorder, or inject products based on region, promotions, etc.
            $event->setProducts($products);
        }
    }

ModifyCategoryQueryEvent
------------------------

Lets integrators constrain or reorder the category query behind the catalogue navigation - restrict by user group,
hide seasonal categories, or apply dynamic filters. The query is mutated in place by listeners
(e.g. ``$event->getQuery()->matching(...)``).

Mutable: Yes (in place via ``QueryInterface`` mutation)

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class RestrictCategoriesByUserGroup
    {
        public function __invoke(ModifyCategoryQueryEvent $event): void
        {
            $query = $event->getQuery();
            // Constrain the query to filter or reorder categories
            $query->matching($query->equals('hidden', 0));
        }
    }

ModifyCategoryTreeEvent
-----------------------

Lets integrators reshape the built category tree - reorder branches, prune empty categories,
or inject virtual nodes for dynamic catalog navigation.

Mutable: Yes (via ``setTree(CategoryTreeNode[] $tree)``)

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class ReshapeCategoryTree
    {
        public function __invoke(ModifyCategoryTreeEvent $event): void
        {
            $tree = $event->getTree();
            // Reorder, prune, or augment the category tree structure
            $event->setTree($tree);
        }
    }
