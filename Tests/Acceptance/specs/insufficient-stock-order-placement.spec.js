import { test, expect } from '@playwright/test';

test('order placement fails visibly when the requested quantity exceeds available stock', async ({ page }) => {
  await page.goto('/product/limited-vintage-compass');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');
  const row = page.locator('tr', { hasText: 'Limited Vintage Compass' });
  await row.locator('input[name="tx_products_basket[quantity]"]').fill('2');
  await row.getByRole('button', { name: 'Update' }).click();

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

  await page.locator('#termsAccepted').check();
  await page.getByRole('button', { name: 'Order with obligation to pay' }).click();

  await expect(page.getByText('Insufficient stock', { exact: false })).toBeVisible();
  await expect(page.getByText('Your order number is', { exact: false })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Order with obligation to pay' })).toBeVisible();
});
