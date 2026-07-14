import { test, expect } from '@playwright/test';

test('a guest cancels their own order via the thank-you page link', async ({ page }) => {
  await page.goto('/product/black-denim-jeans');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/checkout');
  await page.locator('input[name="tx_products_checkout[address][email]"]').fill('withdraw-guest@example.com');
  await page.locator('input[name="tx_products_checkout[address][firstName]"]').fill('With');
  await page.locator('input[name="tx_products_checkout[address][lastName]"]').fill('Draw');
  await page.locator('input[name="tx_products_checkout[address][street]"]').fill('Return Street 1');
  await page.locator('input[name="tx_products_checkout[address][zip]"]').fill('10115');
  await page.locator('input[name="tx_products_checkout[address][city]"]').fill('Berlin');
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

  await page.getByRole('link', { name: 'Cancel order' }).click();

  await expect(page.getByRole('heading', { name: /Cancel order/ })).toBeVisible();
  await page.getByLabel('Confirm your email address').fill('withdraw-guest@example.com');
  await page.getByLabel('Reason (optional)').fill('Changed my mind');
  await page.getByRole('button', { name: 'Cancel this order' }).click();

  await expect(page.getByRole('heading', { name: 'Order cancelled' })).toBeVisible();
  await expect(page.getByText(orderNumber)).toBeVisible();
});
