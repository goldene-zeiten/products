import { test, expect } from '@playwright/test';

test("basket quantity update silently clamps to the product's configured maximum instead of rejecting the request", async ({
  page,
}) => {
  await page.goto('/product/employee-discount-sample');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');
  const row = page.locator('tr', { hasText: 'Employee Discount Sample' });
  await row.locator('input[name="tx_productscore_basket[quantity]"]').fill('10');
  await row.getByRole('button', { name: 'Update' }).click();

  const updatedRow = page.locator('tr', { hasText: 'Employee Discount Sample' });
  // basket_max_quantity=3, so 10 clamps to 3
  await expect(updatedRow.locator('input[name="tx_productscore_basket[quantity]"]')).toHaveValue('3');
  // 5.00 EUR unit price; 3 x 5.00 = 15.00
  await expect(updatedRow).toContainText('15.00');
  await expect(updatedRow).not.toContainText('50.00');
});
