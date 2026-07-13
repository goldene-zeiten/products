import { test, expect } from '@playwright/test';

test('a free-shipping voucher waives the actual shipping cost even though the review page still shows the nominal rate', async ({
  page,
}) => {
  await page.goto('/product/blue-cotton-shirt');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');
  await page.locator('input[name="tx_products_basket[voucherCode]"]').fill('FREESHIP');
  await page.getByRole('button', { name: 'Apply' }).click();
  await expect(page.getByText('Voucher applied: FREESHIP')).toBeVisible();

  await page.goto('/checkout');
  await page.locator('input[name="tx_products_checkout[address][email]"]').fill('freeship@example.com');
  await page.locator('input[name="tx_products_checkout[address][firstName]"]').fill('Free');
  await page.locator('input[name="tx_products_checkout[address][lastName]"]').fill('Ship');
  await page.locator('input[name="tx_products_checkout[address][street]"]').fill('Freedom Street 1');
  await page.locator('input[name="tx_products_checkout[address][zip]"]').fill('12345');
  await page.locator('input[name="tx_products_checkout[address][city]"]').fill('Berlin');
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('label', { hasText: 'Standard Shipping' }).click();
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_products_checkout[paymentMethod]"]').first().check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  await page.locator('#termsAccepted').check();
  await page.getByRole('button', { name: 'Order with obligation to pay' }).click();

  // 29.99 (shipping waived), not 34.98
  await expect(page.locator('tr', { hasText: 'Total Gross' })).toContainText('29.99');
  await expect(page.locator('tr', { hasText: 'Total Gross' })).not.toContainText('34.98');
});
