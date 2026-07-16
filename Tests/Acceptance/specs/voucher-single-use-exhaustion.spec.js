import { test, expect } from '@playwright/test';

test('single-use voucher exhausts after first redemption and rejects sequential reuse', async ({ page }) => {
  await page.goto('/product/blue-cotton-shirt');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');
  await page.locator('input[name="tx_productscore_basket[voucherCode]"]').fill('ONETIME5');
  await page.getByRole('button', { name: 'Apply' }).click();

  await expect(page.getByText('Voucher applied: ONETIME5')).toBeVisible();

  await page.goto('/checkout');
  await page
    .locator('#address-email, input[name="tx_productscore_checkout[address][email]"]')
    .fill('guest@example.com');
  await page.locator('input[name="tx_productscore_checkout[address][firstName]"]').fill('Jane');
  await page.locator('input[name="tx_productscore_checkout[address][lastName]"]').fill('Doe');
  await page.locator('input[name="tx_productscore_checkout[address][street]"]').fill('Main Street 1');
  await page.locator('input[name="tx_productscore_checkout[address][zip]"]').fill('12345');
  await page.locator('input[name="tx_productscore_checkout[address][city]"]').fill('Berlin');
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_productscore_checkout[shippingOption]"]').first().check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_productscore_checkout[paymentMethod]"][value="invoice"]').check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  await expect(page.getByText('Jane Doe')).toBeVisible();
  await page.locator('#termsAccepted').check();
  await page.getByRole('button', { name: 'Order with obligation to pay' }).click();

  await expect(page.getByText('Your order number is', { exact: false })).toBeVisible();

  await page.goto('/product/red-cotton-shirt');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');
  await page.locator('input[name="tx_productscore_basket[voucherCode]"]').fill('ONETIME5');
  await page.getByRole('button', { name: 'Apply' }).click();

  await expect(page.getByText('Voucher "ONETIME5" has already been used the maximum number of times.')).toBeVisible();
  await expect(page.getByText('Voucher applied: ONETIME5')).toHaveCount(0);
});
