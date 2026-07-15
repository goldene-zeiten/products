import { test, expect } from '@playwright/test';

test('user adds a product to the basket', async ({ page }) => {
  await page.goto('/product/blue-cotton-shirt');
  await page.locator('input[name="tx_productscore_basket[quantity]"]').fill('2');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');

  const row = page.locator('tr', { hasText: 'Blue Cotton Shirt' });
  await expect(row).toBeVisible();
  await expect(row.locator('input[name="tx_productscore_basket[quantity]"]')).toHaveValue('2');
});
