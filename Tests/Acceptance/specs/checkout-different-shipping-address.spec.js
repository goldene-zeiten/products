import { test, expect } from '@playwright/test';

test('guest checks out with a billing address and a different shipping address', async ({ page }) => {
  await page.goto('/product/black-denim-jeans');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/checkout');
  await page.locator('input[name="tx_productscore_checkout[address][email]"]').fill('guest2@example.com');
  await page.locator('input[name="tx_productscore_checkout[address][firstName]"]').fill('Bill');
  await page.locator('input[name="tx_productscore_checkout[address][lastName]"]').fill('Payer');
  await page.locator('input[name="tx_productscore_checkout[address][street]"]').fill('Billing Street 1');
  await page.locator('input[name="tx_productscore_checkout[address][zip]"]').fill('11111');
  await page.locator('input[name="tx_productscore_checkout[address][city]"]').fill('Munich');

  await page.locator('#shipToDifferentAddress').check();
  await page.locator('input[name="tx_productscore_checkout[deliveryAddress][firstName]"]').fill('Della');
  await page.locator('input[name="tx_productscore_checkout[deliveryAddress][lastName]"]').fill('Recipient');
  await page.locator('input[name="tx_productscore_checkout[deliveryAddress][street]"]').fill('Delivery Street 2');
  await page.locator('input[name="tx_productscore_checkout[deliveryAddress][zip]"]').fill('22222');
  await page.locator('input[name="tx_productscore_checkout[deliveryAddress][city]"]').fill('Cologne');

  await page.getByRole('button', { name: 'Continue to payment' }).click();
  await page.locator('input[name="tx_productscore_checkout[shippingOption]"]').first().check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();
  await page.locator('input[name="tx_productscore_checkout[paymentMethod]"][value="invoice"]').check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  await expect(page.getByText('Bill Payer')).toBeVisible();
  await expect(page.getByText('Della Recipient')).toBeVisible();

  await page.locator('#termsAccepted').check();
  await page.getByRole('button', { name: 'Order with obligation to pay' }).click();
  await expect(page.getByText('Your order number is', { exact: false })).toBeVisible();
});
