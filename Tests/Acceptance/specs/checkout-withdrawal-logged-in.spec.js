import { test, expect } from '@playwright/test';

test('a logged-in shopper withdraws from their own order from the order-detail page', async ({ page }) => {
  await page.goto('/product/soundmax-headphones');
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

  await page.locator('input[name="tx_productscore_checkout[paymentMethod]"][value="invoice"]').check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  await page.locator('#termsAccepted').check();
  await page.getByRole('button', { name: 'Order with obligation to pay' }).click();
  await expect(page.getByText('Your order number is', { exact: false })).toBeVisible();
  const orderNumberText = await page.getByText('Your order number is', { exact: false }).textContent();
  const orderNumber = orderNumberText.replace('Your order number is', '').replace('.', '').trim();

  await page.goto('/order-history');
  await page.locator('tr', { hasText: orderNumber }).getByRole('link', { name: 'Details' }).click();

  await page.locator('a.btn-outline-danger', { hasText: 'Withdraw from contract here' }).click();

  await expect(page.getByRole('heading', { name: 'Withdraw from contract' })).toBeVisible();
  await page.getByLabel('Confirm your email address').fill('shopper1@example.com');
  await page.getByRole('button', { name: 'Confirm withdrawal' }).click();

  await expect(page.getByRole('heading', { name: 'Withdrawal received' })).toBeVisible();
  await expect(page.getByText(orderNumber)).toBeVisible();
});

test('a logged-in shopper picks their order from the footer withdrawal page without identifying it again', async ({
  page,
}) => {
  await page.goto('/product/soundmax-headphones');
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

  await page.locator('input[name="tx_productscore_checkout[paymentMethod]"][value="invoice"]').check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  await page.locator('#termsAccepted').check();
  await page.getByRole('button', { name: 'Order with obligation to pay' }).click();
  await expect(page.getByText('Your order number is', { exact: false })).toBeVisible();
  const orderNumberText = await page.getByText('Your order number is', { exact: false }).textContent();
  const orderNumber = orderNumberText.replace('Your order number is', '').replace('.', '').trim();

  await page.goto('/cancel-order');
  // The shopper's withdrawable orders are listed; the delivered order past its period is not
  await expect(page.getByRole('link', { name: 'Order ORD-EXPIRED-1' })).toHaveCount(0);
  await page.getByRole('link', { name: 'Order ' + orderNumber }).click();

  await expect(page.getByLabel('Your name')).toHaveValue('Sam Shopper');
  await expect(page.getByLabel('Confirm your email address')).toHaveValue('shopper1@example.com');
  await page.getByRole('button', { name: 'Confirm withdrawal' }).click();

  await expect(page.getByRole('heading', { name: 'Withdrawal received' })).toBeVisible();
  await expect(page.getByText(orderNumber)).toBeVisible();
});
