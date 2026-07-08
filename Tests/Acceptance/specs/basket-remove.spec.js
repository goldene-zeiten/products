import { test, expect } from '@playwright/test';

test('user removes the suggested product, leaving only the product added twice', async ({ page }) => {
  await page.goto('/product/photon-x100-smartphone');
  await page.getByRole('button', { name: 'Add to Basket' }).click();
  await page.goto('/product/photon-x100-smartphone');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/product/photon-x100-smartphone');
  await page.getByRole('link', { name: 'Photon Phone Case' }).click();
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');
  await page.locator('tr', { hasText: 'Photon Phone Case' }).getByRole('link', { name: 'Remove' }).click();

  await expect(page.locator('tr', { hasText: 'Photon Phone Case' })).toHaveCount(0);
  const smartphoneRow = page.locator('tr', { hasText: 'Photon X100 Smartphone' });
  await expect(smartphoneRow).toBeVisible();
  await expect(smartphoneRow.locator('input[name="tx_products_basket[quantity]"]')).toHaveValue('2');
});
