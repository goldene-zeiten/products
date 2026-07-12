import { test, expect } from '@playwright/test';

test('a product under a discounted category shows the cascaded discount in the basket', async ({ page }) => {
  await page.goto('/product/white-silk-blouse');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');

  // White Silk Blouse: 39.99 EUR; category Shirts (no discount) under Women (15% discount, cascades down) = 33.99 EUR
  await expect(page.getByText('33.99 EUR').first()).toBeVisible();
  await expect(page.getByText('39.99 EUR')).toHaveCount(0);
});
