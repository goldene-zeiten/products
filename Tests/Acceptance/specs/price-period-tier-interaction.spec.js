import { test, expect } from '@playwright/test';

test('a shopper adding a bulk quantity sees the lower of period and tier prices', async ({ page }) => {
  await page.goto('/product/bulk-tier-vs-sale-socks');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');
  const row = page.locator('tr', { hasText: 'Bulk Tier vs Sale Socks' });
  await row.locator('input[name="tx_products_basket[quantity]"]').fill('5');
  await row.getByRole('button', { name: 'Update' }).click();

  // Base: 29.99 EUR; active period = 19.99 EUR; tier (qty 5+) = 17.99 EUR (lowest wins)
  // 5 x 17.99 = 89.95
  await expect(row).toContainText('89.95');
  await expect(row.locator('input[name="tx_products_basket[quantity]"]')).toHaveValue('5');
});
