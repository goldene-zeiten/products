import { test, expect } from '@playwright/test';

test('applying a voucher discounts the basket, removing it reverts to the full price', async ({ page }) => {
  await page.goto('/product/blue-cotton-shirt');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');

  // Blue Cotton Shirt: 29.99 EUR, category "Men" carries 0% discount
  await expect(page.getByText('29.99 EUR').first()).toBeVisible();

  // SAVE5: combinable, unlimited, fixed 5.00 EUR, no minimum basket value
  await page.locator('input[name="tx_productscore_basket[voucherCode]"]').fill('SAVE5');
  await page.getByRole('button', { name: 'Apply' }).click();

  await expect(page.getByText('Voucher applied: SAVE5')).toBeVisible();
  await expect(page.locator('tr', { hasText: 'Voucher discount' })).toContainText('5.00 EUR');
  await expect(page.getByText('24.99 EUR')).toBeVisible();

  await page.locator('.basket-vouchers').getByRole('link', { name: 'Remove' }).click();

  await expect(page.getByText('Voucher applied:', { exact: false })).toHaveCount(0);
  await expect(page.getByText('24.99 EUR')).toHaveCount(0);
  await expect(page.getByText('29.99 EUR').first()).toBeVisible();
});
