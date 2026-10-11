import { test, expect } from '@playwright/test';

test('a guest withdraws from their own order via the thank-you page link', async ({ page }) => {
  await page.goto('/product/black-denim-jeans');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/checkout');
  await page.locator('input[name="tx_productscore_checkout[address][email]"]').fill('withdraw-guest@example.com');
  await page.locator('input[name="tx_productscore_checkout[address][firstName]"]').fill('With');
  await page.locator('input[name="tx_productscore_checkout[address][lastName]"]').fill('Draw');
  await page.locator('input[name="tx_productscore_checkout[address][street]"]').fill('Return Street 1');
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

  await page.locator('a.btn-outline-danger', { hasText: 'Withdraw from contract here' }).click();

  await expect(page.getByRole('heading', { name: 'Withdraw from contract' })).toBeVisible();
  // The tokened link prefills the form (Art. 11a: the function must be easy to use)
  await expect(page.getByLabel('Order number')).toHaveValue(orderNumber);
  await expect(page.getByLabel('Your name')).toHaveValue('With Draw');
  await expect(page.getByLabel('Confirm your email address')).toHaveValue('withdraw-guest@example.com');
  // The items of the order are listed and selected by default (partial withdrawal is possible)
  await expect(page.getByRole('checkbox', { name: /Black Denim Jeans/ })).toBeChecked();
  await page.getByLabel('Reason (optional)').fill('Changed my mind');
  await page.getByRole('button', { name: 'Confirm withdrawal' }).click();

  await expect(page.getByRole('heading', { name: 'Withdrawal received' })).toBeVisible();
  await expect(page.getByText(orderNumber)).toBeVisible();
});
