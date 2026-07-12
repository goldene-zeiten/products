import { test, expect } from '@playwright/test';

test('visiting a product multiple times increments its view count and it appears in the most-viewed listing', async ({
  page,
}) => {
  // Visit the Popularity Test Item product five times to build up its view count
  await page.goto('/product/popularity-test-item');
  await page.goto('/product/popularity-test-item');
  await page.goto('/product/popularity-test-item');
  await page.goto('/product/popularity-test-item');
  await page.goto('/product/popularity-test-item');

  // Go to the most-viewed page
  await page.goto('/most-viewed');

  // Assert that "Popularity Test Item" is visible in the listing
  await expect(page.getByText('Popularity Test Item')).toBeVisible();
});
