import { test, expect } from '@playwright/test';

test('a guest cannot cancel an order by entering a different email address', async ({ page }) => {
  await page.goto('/product/black-denim-jeans');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/checkout');
  await page.locator('input[name="tx_products_checkout[address][email]"]').fill('mismatch-guest@example.com');
  await page.locator('input[name="tx_products_checkout[address][firstName]"]').fill('Mia');
  await page.locator('input[name="tx_products_checkout[address][lastName]"]').fill('Match');
  await page.locator('input[name="tx_products_checkout[address][street]"]').fill('Wrong Street 2');
  await page.locator('input[name="tx_products_checkout[address][zip]"]').fill('20099');
  await page.locator('input[name="tx_products_checkout[address][city]"]').fill('Hamburg');
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_products_checkout[shippingMethod]"]').first().check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_products_checkout[paymentMethod]"]').first().check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  await page.getByRole('button', { name: 'Place order' }).click();
  await expect(page.getByText('Your order number is', { exact: false })).toBeVisible();

  await page.getByRole('link', { name: 'Cancel order' }).click();
  await expect(page.getByRole('heading', { name: /Cancel order/ })).toBeVisible();

  await page.getByLabel('Confirm your email address').fill('someone-else@example.com');
  await page.getByRole('button', { name: 'Cancel this order' }).click();

  await expect(page.getByText('The email address does not match this order.')).toBeVisible();
  // WithdrawalController::confirmAction redirects back to the form on any failure - the shopper
  // stays on the cancellation form, not bounced to some generic error page.
  await expect(page.getByRole('heading', { name: /Cancel order/ })).toBeVisible();
});
