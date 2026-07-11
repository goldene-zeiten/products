import { test, expect } from '@playwright/test';

// Checkout/Address.html has no `required` attributes and CheckoutController::submitAddressAction
// has no validators wired up (grep confirms there isn't a single Validator class in Classes/) -
// a blank address form is expected to sail straight through today. This pins that current
// (surprising) behaviour so a future validation feature is an intentional test update, not a
// silent regression nobody notices.
test('checkout places the order even when every address field is left blank', async ({ page }) => {
  await page.goto('/product/blue-denim-jeans');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/checkout');
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_products_checkout[shippingMethod]"]').first().check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_products_checkout[paymentMethod]"]').first().check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  await page.getByRole('button', { name: 'Place order' }).click();
  await expect(page.getByText('Your order number is', { exact: false })).toBeVisible();

  await page.getByRole('link', { name: 'View Order' }).click();
  await expect(page.getByRole('heading', { name: /Order Details/ })).toBeVisible();
});
