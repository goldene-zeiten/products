import { test, expect } from '@playwright/test';

test("basket quantity update silently clamps to the product's configured maximum instead of rejecting the request", async ({
  page,
}) => {
  await page.goto('/product/employee-discount-sample');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');
  const row = page.locator('tr', { hasText: 'Employee Discount Sample' });
  await row.locator('input[name="tx_products_basket[quantity]"]').fill('10');
  await row.getByRole('button', { name: 'Update' }).click();

  const updatedRow = page.locator('tr', { hasText: 'Employee Discount Sample' });
  // Employee Discount Sample has basket_max_quantity=3; attempting 10 should clamp to 3.
  await expect(updatedRow.locator('input[name="tx_products_basket[quantity]"]')).toHaveValue('3');
  // Employee Discount Sample is 5.00 EUR (see shop-demo.csv); 3 x 5.00 = 15.00.
  await expect(updatedRow).toContainText('15.00');
  // Assert it does NOT show the unclamped total: 10 x 5.00 = 50.00.
  await expect(updatedRow).not.toContainText('50.00');
});
