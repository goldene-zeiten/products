import { test, expect } from '@playwright/test';

test('viewing a product increments the personal view count and makes it appear in my-most-viewed', async ({ page }) => {
  await page.goto('/product/popularity-test-item');
  await page.reload();
  await page.reload();
  await page.reload();
  await page.reload();

  await page.goto('/my-most-viewed');

  await expect(page.getByText('Popularity Test Item')).toBeVisible();
});
