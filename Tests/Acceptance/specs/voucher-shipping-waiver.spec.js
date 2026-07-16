import { test, expect } from '@playwright/test';
import { hasAnyCarrier } from '../helper/combination.js';

// The nominal shipping rate this asserts is the table-rate carrier's; a live carrier supersedes it.
test.beforeEach(() => {
  test.skip(hasAnyCarrier(), 'Table-rate shipping is superseded by a live carrier in this combination.');
});

test('a free-shipping voucher waives the actual shipping cost even though the review page still shows the nominal rate', async ({
  page,
}) => {
  await page.goto('/product/blue-cotton-shirt');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');
  await page.locator('input[name="tx_productscore_basket[voucherCode]"]').fill('FREESHIP');
  await page.getByRole('button', { name: 'Apply' }).click();
  await expect(page.getByText('Voucher applied: FREESHIP')).toBeVisible();

  await page.goto('/checkout');
  await page.locator('input[name="tx_productscore_checkout[address][email]"]').fill('freeship@example.com');
  await page.locator('input[name="tx_productscore_checkout[address][firstName]"]').fill('Free');
  await page.locator('input[name="tx_productscore_checkout[address][lastName]"]').fill('Ship');
  await page.locator('input[name="tx_productscore_checkout[address][street]"]').fill('Freedom Street 1');
  await page.locator('input[name="tx_productscore_checkout[address][zip]"]').fill('12345');
  await page.locator('input[name="tx_productscore_checkout[address][city]"]').fill('Berlin');
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('label', { hasText: 'Standard Shipping' }).click();
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_productscore_checkout[paymentMethod]"][value="invoice"]').check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  // The review still shows the carrier's nominal rate - the free-shipping voucher offsets the cost,
  // it does not hide what shipping charged.
  await expect(page.getByText(/Standard Shipping\s*-\s*4\.99/)).toBeVisible();

  await page.locator('#termsAccepted').check();
  await page.getByRole('button', { name: 'Order with obligation to pay' }).click();

  // 29.99 (shipping waived), not 34.98
  await expect(page.locator('tr', { hasText: 'Total Gross' })).toContainText('29.99');
  await expect(page.locator('tr', { hasText: 'Total Gross' })).not.toContainText('34.98');
});
