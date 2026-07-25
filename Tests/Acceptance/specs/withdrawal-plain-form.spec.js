import { test, expect } from '@playwright/test';

test('a guest withdraws via the always-available footer navigation withdrawal page', async ({ page }) => {
  await page.goto('/product/black-denim-jeans');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/checkout');
  await page.locator('input[name="tx_productscore_checkout[address][email]"]').fill('plain-withdraw@example.com');
  await page.locator('input[name="tx_productscore_checkout[address][firstName]"]').fill('Plain');
  await page.locator('input[name="tx_productscore_checkout[address][lastName]"]').fill('Form');
  await page.locator('input[name="tx_productscore_checkout[address][street]"]').fill('Footer Street 3');
  await page.locator('input[name="tx_productscore_checkout[address][zip]"]').fill('10115');
  await page.locator('input[name="tx_productscore_checkout[address][city]"]').fill('Berlin');
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

  // The withdrawal page type sits in the footer navigation with its styling class
  // (Art. 11a: continuously available on the online interface)
  await page.goto('/');
  const footerLink = page.locator('#site-footer a.nav-link-withdrawal');
  await expect(footerLink).toHaveText('Withdraw from contract');
  await footerLink.click();

  // Plain mode: no token, the customer identifies the order themselves
  await expect(page.getByRole('heading', { name: 'Withdraw from contract' })).toBeVisible();
  await expect(page.getByLabel('Order number')).toHaveValue('');
  await page.getByLabel('Order number').fill(orderNumber);
  await page.getByLabel('Confirm your email address').fill('plain-withdraw@example.com');
  await page.getByLabel('Reason (optional)').fill('Found a better price');
  await page.getByRole('button', { name: 'Confirm withdrawal' }).click();

  await expect(page.getByRole('heading', { name: 'Order cancelled' })).toBeVisible();
  await expect(page.getByText(orderNumber)).toBeVisible();
});

test('the plain form rejects an order number and email pair that does not match', async ({ page }) => {
  await page.goto('/cancel-order');
  await expect(page.getByRole('heading', { name: 'Withdraw from contract' })).toBeVisible();

  await page.getByLabel('Order number').fill('ORD-DOES-NOT-EXIST');
  await page.getByLabel('Confirm your email address').fill('nobody@example.com');
  await page.getByRole('button', { name: 'Confirm withdrawal' }).click();

  await expect(
    page.getByText('The order number and email address did not match an order. Please check your input.'),
  ).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Withdraw from contract' })).toBeVisible();
});
