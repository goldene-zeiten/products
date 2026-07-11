import { test, expect } from '@playwright/test';

test('checkout finalize fails visibly when the basket is empty', async ({ page }) => {
  // Navigate directly to checkout with an empty basket (no items added).
  await page.goto('/checkout');

  // Fill in address fields.
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

  // Payment method step.
  await page.locator('input[name="tx_products_checkout[paymentMethod]"]').first().check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  // Attempt to place order with empty basket.
  await page.getByRole('button', { name: 'Place order' }).click();

  // Assert that the flash message "Basket is empty." is visible.
  await expect(page.getByText('Basket is empty.')).toBeVisible();

  // Assert that the thank-you page was NOT reached.
  await expect(page.getByText('Your order number is', { exact: false })).toHaveCount(0);

  // Assert that the "Place order" button is still visible (confirms we're back on the review step).
  await expect(page.getByRole('button', { name: 'Place order' })).toBeVisible();
});
