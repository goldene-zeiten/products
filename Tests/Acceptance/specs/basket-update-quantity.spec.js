import { test, expect } from '@playwright/test';

test('user updates the quantity of an existing basket line', async ({ page }) => {
  await page.goto('/product/red-cotton-shirt');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');
  const row = page.locator('tr', { hasText: 'Red Cotton Shirt' });
  await row.locator('input[name="tx_products_basket[quantity]"]').fill('3');
  await row.getByRole('button', { name: 'Update' }).click();

  const updatedRow = page.locator('tr', { hasText: 'Red Cotton Shirt' });
  await expect(updatedRow.locator('input[name="tx_products_basket[quantity]"]')).toHaveValue('3');
  // Red Cotton Shirt is 29.99 EUR (see shop-demo.csv); 3 x 29.99 = 89.97.
  await expect(updatedRow).toContainText('89.97');
});
