import { test, expect } from '@playwright/test';

test("a guest follows the thank-you page's order-detail link and sees the order without logging in", async ({
  page,
}) => {
  await page.goto('/product/black-denim-jeans');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/checkout');
  await page.locator('input[name="tx_products_checkout[address][email]"]').fill('token-guest@example.com');
  await page.locator('input[name="tx_products_checkout[address][firstName]"]').fill('Toni');
  await page.locator('input[name="tx_products_checkout[address][lastName]"]').fill('Guest');
  await page.locator('input[name="tx_products_checkout[address][street]"]').fill('Guest Street 9');
  await page.locator('input[name="tx_products_checkout[address][zip]"]').fill('20095');
  await page.locator('input[name="tx_products_checkout[address][city]"]').fill('Hamburg');
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_products_checkout[shippingMethod]"]').first().check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_products_checkout[paymentMethod]"]').first().check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  await page.getByRole('button', { name: 'Place order' }).click();
  await expect(page.getByText('Your order number is', { exact: false })).toBeVisible();

  const viewOrderLink = page.getByRole('link', { name: 'View Order' });
  const href = await viewOrderLink.getAttribute('href');
  // OrderController::showAction only grants a guest (frontendUser 0) access via this HMAC token -
  // without a valid ?hash= it redirects straight back to the (empty, for a guest) order list.
  expect(href).toContain('hash');

  await viewOrderLink.click();

  await expect(page.getByRole('heading', { name: /Order Details/ })).toBeVisible();
  await expect(page.getByText('Toni Guest')).toBeVisible();
  await expect(page.getByText('Guest Street 9')).toBeVisible();
});
