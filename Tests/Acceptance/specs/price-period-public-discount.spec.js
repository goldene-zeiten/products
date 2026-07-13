import { test, expect } from '@playwright/test';

test('a shopper sees an active public price period (sale) instead of the base price', async ({ page }) => {
  await page.goto('/product/flash-sale-jacket');

  // Flash Sale Jacket: 39.99 EUR base price; active PUBLIC price period = 24.99 EUR
  await expect(page.getByText('€24.99').first()).toBeVisible();
  await expect(page.getByText('€39.99')).toHaveCount(0);
});
