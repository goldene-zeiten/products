import { test, expect } from '@playwright/test';

test('an expired price period is not applied; shopper sees the base price', async ({ page }) => {
  await page.goto('/product/expired-sale-hat');

  // Expired Sale Hat: 34.99 EUR base price; expired price period (19.99 EUR) should NOT apply
  await expect(page.getByText('€34.99').first()).toBeVisible();
  await expect(page.getByText('€19.99')).toHaveCount(0);
});
