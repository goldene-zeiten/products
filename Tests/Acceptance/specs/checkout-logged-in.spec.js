import { test, expect } from '@playwright/test';

test('logged-in shopper checks out and finds the order in their order history', async ({ page }) => {
  await page.goto('/product/red-cotton-shirt');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/checkout');
  await page.locator('input[name="tx_productscore_checkout[address][email]"]').fill('shopper1@example.com');
  await page.locator('input[name="tx_productscore_checkout[address][firstName]"]').fill('Sam');
  await page.locator('input[name="tx_productscore_checkout[address][lastName]"]').fill('Shopper');
  await page.locator('input[name="tx_productscore_checkout[address][street]"]').fill('Shop Street 5');
  await page.locator('input[name="tx_productscore_checkout[address][zip]"]').fill('54321');
  await page.locator('input[name="tx_productscore_checkout[address][city]"]').fill('Hamburg');
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_productscore_checkout[shippingOption]"]').first().check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_productscore_checkout[paymentMethod]"]').first().check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  await page.locator('#termsAccepted').check();
  await page.getByRole('button', { name: 'Order with obligation to pay' }).click();
  await expect(page.getByText('Your order number is', { exact: false })).toBeVisible();
  const orderNumberText = await page.getByText('Your order number is', { exact: false }).textContent();
  const orderNumber = orderNumberText.replace('Your order number is', '').replace('.', '').trim();

  await page.goto('/order-history');

  await expect(page.getByText(orderNumber)).toBeVisible();
});
