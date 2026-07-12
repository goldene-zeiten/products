import { test, expect } from '@playwright/test';

test('viewing a product increments the personal view count and makes it appear in my-most-viewed', async ({ page }) => {
  // Visit the Popularity Test Item product multiple times to build up shopper1's personal view
  // count for it. ProductViewTrackingService records per-user view counts only for logged-in
  // shoppers, so the product will become visible in their personal "my most viewed" listing.
  await page.goto('/product/popularity-test-item');
  await page.reload();
  await page.reload();
  await page.reload();
  await page.reload();

  // Navigate to the my-most-viewed page, which renders products ordered by that shopper's own
  // view count descending (capped at the default limit of 10).
  await page.goto('/my-most-viewed');

  // Assert that Popularity Test Item is visible in the listing. The page renders each product
  // using the shared Product/ListItem partial, which includes the product title as
  // <h5 class="card-title">{product.title}</h5>.
  await expect(page.getByText('Popularity Test Item')).toBeVisible();
});
