import { test, expect } from '@playwright/test';

// No address validators are wired up today; this pins that current behavior.
test('checkout places the order even when every address field is left blank', async ({ page }) => {
  await page.goto('/product/blue-denim-jeans');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/checkout');
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_productscore_checkout[shippingOption]"]').first().check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_productscore_checkout[paymentMethod]"]').first().check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  await page.locator('#termsAccepted').check();
  await page.getByRole('button', { name: 'Order with obligation to pay' }).click();
  await expect(page.getByText('Your order number is', { exact: false })).toBeVisible();

  await page.getByRole('link', { name: 'View Order' }).click();
  await expect(page.getByRole('heading', { name: /Order Details/ })).toBeVisible();
});
