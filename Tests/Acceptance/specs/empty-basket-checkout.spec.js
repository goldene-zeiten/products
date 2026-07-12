import { test, expect } from '@playwright/test';

test('checkout finalize fails visibly when the basket is empty', async ({ page }) => {
  await page.goto('/checkout');

  await page.locator('#address-email, input[name="tx_products_checkout[address][email]"]').fill('guest@example.com');
  await page.locator('input[name="tx_products_checkout[address][firstName]"]').fill('Jane');
  await page.locator('input[name="tx_products_checkout[address][lastName]"]').fill('Doe');
  await page.locator('input[name="tx_products_checkout[address][street]"]').fill('Main Street 1');
  await page.locator('input[name="tx_products_checkout[address][zip]"]').fill('12345');
  await page.locator('input[name="tx_products_checkout[address][city]"]').fill('Berlin');
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_products_checkout[shippingMethod]"]').first().check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_products_checkout[paymentMethod]"]').first().check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  await page.getByRole('button', { name: 'Place order' }).click();

  await expect(page.getByText('Basket is empty.')).toBeVisible();

  await expect(page.getByText('Your order number is', { exact: false })).toHaveCount(0);

  // Button visible = still on review step
  await expect(page.getByRole('button', { name: 'Place order' })).toBeVisible();
});
