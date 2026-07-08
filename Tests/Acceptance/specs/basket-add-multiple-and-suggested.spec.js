import { test, expect } from '@playwright/test';

test('user searches a product, adds it twice, then adds a suggested product from its detail page', async ({ page }) => {
  await page.goto('/');
  const results = page.locator('.product-search');
  await results.locator('input[name="tx_products_search[term]"]').fill('Photon X100');
  await results.locator('input[type=submit]').click();
  await results.getByRole('link', { name: 'Details' }).first().click();
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Photon X100');

  // Add-to-basket redirects to the basket page each time, so go back to the product between adds.
  await page.getByRole('button', { name: 'Add to Basket' }).click();
  await page.goto('/product/photon-x100-smartphone');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  // The accessory product suggested on the same detail page.
  await page.goto('/product/photon-x100-smartphone');
  await page.getByRole('link', { name: 'Photon Phone Case' }).click();
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');

  const smartphoneRow = page.locator('tr', { hasText: 'Photon X100 Smartphone' });
  const caseRow = page.locator('tr', { hasText: 'Photon Phone Case' });
  await expect(smartphoneRow).toBeVisible();
  await expect(caseRow).toBeVisible();
  await expect(smartphoneRow.locator('input[name="tx_products_basket[quantity]"]')).toHaveValue('2');
  await expect(caseRow.locator('input[name="tx_products_basket[quantity]"]')).toHaveValue('1');

  // 2 x 499.00 + 19.99 = 1017.99
  await expect(page.getByText('1017.99', { exact: false }).first()).toBeVisible();
});
