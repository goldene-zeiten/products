import { test, expect } from '@playwright/test';

test('single-use voucher exhausts after first redemption and rejects sequential reuse', async ({ page }) => {
  // Add Blue Cotton Shirt to basket and apply the single-use ONETIME5 voucher.
  await page.goto('/product/blue-cotton-shirt');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');
  await page.locator('input[name="tx_products_basket[voucherCode]"]').fill('ONETIME5');
  await page.getByRole('button', { name: 'Apply' }).click();

  await expect(page.getByText('Voucher applied: ONETIME5')).toBeVisible();

  // Complete a full guest checkout to redeem the voucher (first and only successful use).
  await page.goto('/checkout');
  await page.locator('#address-email, input[name="tx_products_checkout[address][email]"]').fill('guest@example.com');
  await page.locator('input[name="tx_products_checkout[address][firstName]"]').fill('Jane');
  await page.locator('input[name="tx_products_checkout[address][lastName]"]').fill('Doe');
  await page.locator('input[name="tx_products_checkout[address][street]"]').fill('Main Street 1');
  await page.locator('input[name="tx_products_checkout[address][zip]"]').fill('12345');
  await page.locator('input[name="tx_products_checkout[address][city]"]').fill('Berlin');
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  // Shipping method step (products.shipping.enabled is on in the demo shop).
  await page.locator('input[name="tx_products_checkout[shippingMethod]"]').first().check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_products_checkout[paymentMethod]"]').first().check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  await expect(page.getByText('Jane Doe')).toBeVisible();
  await page.getByRole('button', { name: 'Place order' }).click();

  await expect(page.getByText('Your order number is', { exact: false })).toBeVisible();

  // The finalized order clears the basket and its voucher codes (established behavior).
  // Add a different product and attempt to reuse the exhausted ONETIME5 voucher.
  // This proves the guard correctly rejects sequential reuse (not a concurrency race).
  await page.goto('/product/red-cotton-shirt');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');
  await page.locator('input[name="tx_products_basket[voucherCode]"]').fill('ONETIME5');
  await page.getByRole('button', { name: 'Apply' }).click();

  await expect(page.getByText('Voucher "ONETIME5" has already been used the maximum number of times.')).toBeVisible();
  await expect(page.getByText('Voucher applied: ONETIME5')).toHaveCount(0);
});
