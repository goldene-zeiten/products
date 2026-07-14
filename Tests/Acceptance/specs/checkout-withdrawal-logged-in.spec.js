import { test, expect } from '@playwright/test';

test('a logged-in shopper cancels their own order from the order-detail page', async ({ page }) => {
  await page.goto('/product/soundmax-headphones');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/checkout');
  await page.locator('input[name="tx_products_checkout[address][email]"]').fill('shopper1@example.com');
  await page.locator('input[name="tx_products_checkout[address][firstName]"]').fill('Sam');
  await page.locator('input[name="tx_products_checkout[address][lastName]"]').fill('Shopper');
  await page.locator('input[name="tx_products_checkout[address][street]"]').fill('Shop Street 5');
  await page.locator('input[name="tx_products_checkout[address][zip]"]').fill('54321');
  await page.locator('input[name="tx_products_checkout[address][city]"]').fill('Hamburg');
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_products_checkout[shippingOption]"]').first().check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_products_checkout[paymentMethod]"]').first().check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  await page.locator('#termsAccepted').check();
  await page.getByRole('button', { name: 'Order with obligation to pay' }).click();
  await expect(page.getByText('Your order number is', { exact: false })).toBeVisible();
  const orderNumberText = await page.getByText('Your order number is', { exact: false }).textContent();
  const orderNumber = orderNumberText.replace('Your order number is', '').replace('.', '').trim();

  await page.goto('/order-history');
  await page.locator('tr', { hasText: orderNumber }).getByRole('link', { name: 'Details' }).click();

  await page.getByRole('link', { name: 'Cancel order' }).click();

  await expect(page.getByRole('heading', { name: /Cancel order/ })).toBeVisible();
  await page.getByLabel('Confirm your email address').fill('shopper1@example.com');
  await page.getByRole('button', { name: 'Cancel this order' }).click();

  await expect(page.getByRole('heading', { name: 'Order cancelled' })).toBeVisible();
  await expect(page.getByText(orderNumber)).toBeVisible();
});
