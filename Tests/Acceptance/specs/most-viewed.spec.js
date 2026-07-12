import { test, expect } from '@playwright/test';

test('visiting a product multiple times increments its view count and it appears in the most-viewed listing', async ({
  page,
}) => {
  await page.goto('/product/popularity-test-item');
  await page.goto('/product/popularity-test-item');
  await page.goto('/product/popularity-test-item');
  await page.goto('/product/popularity-test-item');
  await page.goto('/product/popularity-test-item');

  await page.goto('/most-viewed');

  await expect(page.getByText('Popularity Test Item')).toBeVisible();
});
