import { test, expect } from '@playwright/test';

test('guest checks out with a billing address only', async ({ page }) => {
  await page.goto('/product/blue-cotton-shirt');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

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

  await expect(page.getByText('Jane Doe')).toBeVisible();
  await page.getByRole('button', { name: 'Place order' }).click();

  await expect(page.getByText('Your order number is', { exact: false })).toBeVisible();
});
