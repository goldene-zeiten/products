:navigation-title: Users Manual

..  include:: /Includes.rst.txt
..  _users-manual:

=============
Users Manual
=============

This chapter is for editors and shop operators: what changes for the shopper once the extension is
installed and configured. See :ref:`Configuration <configuration>` for the technical settings.

..  contents:: Table of contents
    :local:

..  _users-manual-wishlist:

Using the wishlist
=====================

Once `products.wishlist.enabled` is on (see :ref:`configuration`), an "add to wishlist" link
appears next to every product in the catalog's product list and detail views. Clicking it adds the
product and the link turns into "remove from wishlist" for that same product; clicking it again
removes it. The :guilabel:`Wishlist` page itself is reachable regardless of that setting, e.g. via
a manually placed link.

*   **Guest shoppers** get a wishlist stored in their browser session only. It works exactly like a
    logged-in wishlist while it lasts, but is lost when the session expires and is never shared
    across devices.
*   **Logged-in customers** get a wishlist tied to their account, so it follows them across visits
    and devices.
*   **Logging in merges** any products already on a guest's session wishlist into the
    now-identified account's wishlist (skipping anything already saved there) and clears the
    session copy — a guest who builds a wishlist before creating an account or logging in does not
    lose it.

The :guilabel:`Wishlist` page lists every saved product with its image, price, a :guilabel:`Details`
link to the product, a :guilabel:`Remove` link, and :guilabel:`Move up` / :guilabel:`Move down`
links to reorder the list one step at a time (there is no drag-and-drop) — the first item has no
:guilabel:`Move up` link and the last has no :guilabel:`Move down` link. The page heading shows the
current item count. An empty wishlist shows a plain "Your wishlist is empty." message instead of
the product grid.

If a wishlisted product is later deleted, it simply disappears from the list.

..  _users-manual-order-placed:

Placing an order
===================

Placing an order automatically removes any of its items from the placing customer's wishlist. Guest
orders are skipped, since a guest's session wishlist is not tied to any identity an order could be
matched against. This cleanup never blocks or fails the order itself, even if it runs into trouble.
