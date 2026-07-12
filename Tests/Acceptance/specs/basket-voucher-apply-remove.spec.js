import { test, expect } from '@playwright/test';

test('applying a voucher discounts the basket, removing it reverts to the full price', async ({ page }) => {
  await page.goto('/product/blue-cotton-shirt');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');

  // Blue Cotton Shirt's list price is 29.99 EUR (see shop-demo.csv) with no category discount
  // (its category "Men" carries 0%), so the basket starts undiscounted.
  await expect(page.getByText('29.99 EUR').first()).toBeVisible();

  // SAVE5 is a combinable, unlimited, fixed 5.00 EUR voucher with no minimum basket value.
  await page.locator('input[name="tx_products_basket[voucherCode]"]').fill('SAVE5');
  await page.getByRole('button', { name: 'Apply' }).click();

  await expect(page.getByText('Voucher applied: SAVE5')).toBeVisible();
  // The discount row renders "&minus;5.00 EUR" (a proper minus sign, not a hyphen) - match on the
  // row's text content instead of the exact glyph.
  await expect(page.locator('tr', { hasText: 'Voucher discount' })).toContainText('5.00 EUR');
  await expect(page.getByText('24.99 EUR')).toBeVisible();

  await page.locator('.basket-vouchers').getByRole('link', { name: 'Remove' }).click();

  await expect(page.getByText('Voucher applied:', { exact: false })).toHaveCount(0);
  await expect(page.getByText('24.99 EUR')).toHaveCount(0);
  await expect(page.getByText('29.99 EUR').first()).toBeVisible();
});
