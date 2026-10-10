..  include:: /Includes.rst.txt
..  _developer-interfaces:

==========
Interfaces
==========

Some extension points in the Products extension are defined as contracts (interfaces) rather than
events. These interfaces enforce explicit implementations that become visible in the codebase and
allow for stronger fail-closed behavior than event listeners can achieve.

..  _developer-interfaces-product-visibility:

ProductVisibilityInterface
==========================

**Location:** :php:`GoldeneZeiten\Products\Core\Visibility\ProductVisibilityInterface`

**Purpose:**

The :php:`ProductVisibilityInterface` contract enables extensions to control whether products appear
anywhere in the catalogue: the product list and detail views, the category listing, related and
accessory products, the category navigation's product counts, the category tree data processor and the
basket. Common use cases include:

- Hiding products from specific frontend user groups
- Per-site assortments
- Hiding products during seasonal blackouts or other time windows

**Caching:**

The product list and category views are page-cached, and the page cache only varies by the visitor's
frontend user groups (and the site and language). A checker must therefore decide on the frontend user
groups, the site and the time alone. A verdict depending on session data, cookies, the individual
frontend user, the visitor's location or an A/B bucket would be cached for the first visitor and served
to everyone sharing their user groups. Model such catalogues as frontend user groups instead.

**Aggregation Rule (Fail-Closed):**

- If **no** implementation is registered: every product is **visible** (the system defaults open).
- If **one or more** implementations are registered: a product is **visible** only if **all** checkers
  return true. A single checker returning false hides the product — **deny wins over allow**.

This fail-closed aggregation ensures that a forgotten or disabled visibility checker cannot
accidentally expose sensitive catalogue restrictions.

**Why an Interface, Not an Event?**

Visibility is enforced as an interface contract rather than a mutable event listener because a
listener that forgot to filter products would silently expose the entire catalogue. The interface
makes the implementation visible in the codebase and forces integrators to be explicit about
visibility decisions.

**Example Implementation:**

..  code-block:: php

    <?php

    namespace MyVendor\MyExtension;

    use GoldeneZeiten\Products\Core\Domain\Model\Product;
    use GoldeneZeiten\Products\Core\Visibility\ProductVisibilityInterface;
    use Psr\Http\Message\ServerRequestInterface;

    /**
     * Hide products based on frontend user group membership.
     */
    final class UserGroupVisibilityChecker implements ProductVisibilityInterface
    {
        public function isVisible(Product $product, ServerRequestInterface $request): bool
        {
            $frontendUser = $request->getAttribute('frontend.user');
            if ($frontendUser === null || $frontendUser->user === null) {
                return false; // Not logged in: hide restricted products
            }

            $userGroups = explode(',', $frontendUser->user['usergroup'] ?? '');
            $requiredGroup = (int)$product->getMeta()['required_group'] ?? 0;

            return $requiredGroup === 0 || in_array($requiredGroup, $userGroups, true);
        }
    }

Registering the implementation is automatic: the interface carries
:php:`#[AutoconfigureTag('products.product_visibility')]` and your extension's :file:`Services.yaml`
has ``autoconfigure: true``, so your class is instantly registered without manual configuration.
