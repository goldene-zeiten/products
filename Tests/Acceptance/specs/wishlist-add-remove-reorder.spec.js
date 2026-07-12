import { test, expect } from '@playwright/test';

test('guest adds products to wishlist, reorders them, and removes them one by one', async ({ page }) => {
  // Add first product (Blue Cotton Shirt) to wishlist from its product page - WishlistController
  // has no content element of its own on the product detail page, so submitting "add" redirects
  // the browser straight to the dedicated /wishlist page (WishlistController::showAction), not
  // back to the product page.
  await page.goto('/product/blue-cotton-shirt');
  await page.getByRole('button', { name: 'Add to wishlist' }).click();
  await expect(page).toHaveURL(/\/wishlist/);
  await expect(page.getByText('Blue Cotton Shirt')).toBeVisible();

  // Add second product (Red Cotton Shirt) to wishlist
  await page.goto('/product/red-cotton-shirt');
  await page.getByRole('button', { name: 'Add to wishlist' }).click();
  await expect(page).toHaveURL(/\/wishlist/);

  // Verify both products appear in the order they were added
  // (Blue Cotton Shirt first, Red Cotton Shirt second)
  await expect(page.getByText('Blue Cotton Shirt')).toBeVisible();
  await expect(page.getByText('Red Cotton Shirt')).toBeVisible();

  // Verify order: Blue Cotton Shirt (index 0) appears before Red Cotton Shirt (index 1)
  // in DOM by checking the card-title elements
  const titles = page.locator('h5.card-title');
  await expect(titles.nth(0)).toContainText('Blue Cotton Shirt');
  await expect(titles.nth(1)).toContainText('Red Cotton Shirt');

  // Reorder: Move Red Cotton Shirt up (or Blue Cotton Shirt down) to swap their positions
  // The "Move up" button is hidden for the first item, so we click "Move up" on the second item (Red)
  const redProductCard = page.locator('.card', { has: page.locator('.card-title', { hasText: 'Red Cotton Shirt' }) });
  await redProductCard.getByRole('link', { name: 'Move up' }).click();

  // Verify order is now reversed: Red Cotton Shirt first, Blue Cotton Shirt second
  await expect(titles.nth(0)).toContainText('Red Cotton Shirt');
  await expect(titles.nth(1)).toContainText('Blue Cotton Shirt');

  // Remove the first product (Red Cotton Shirt) via its "Remove" link/button
  const firstProductCard = page.locator('.card').first();
  await firstProductCard.getByRole('link', { name: 'Remove' }).click();

  // Verify Red Cotton Shirt is gone, but Blue Cotton Shirt remains
  await expect(page.getByText('Red Cotton Shirt')).toHaveCount(0);
  await expect(page.getByText('Blue Cotton Shirt')).toBeVisible();

  // Remove the last remaining product (Blue Cotton Shirt)
  const lastProductCard = page.locator('.card').first();
  await lastProductCard.getByRole('link', { name: 'Remove' }).click();

  // Verify the wishlist is now empty with the empty-state message
  await expect(page.getByText('Your wishlist is empty.')).toBeVisible();
});
