import { test, expect } from '@playwright/test';

test('a personalized item order carries no withdrawal right (Art. 16(c))', async ({ page }) => {
  await page.goto('/product/engraved-silver-ring');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/checkout');
  await page.locator('input[name="tx_productscore_checkout[address][email]"]').fill('ring-buyer@example.com');
  await page.locator('input[name="tx_productscore_checkout[address][firstName]"]').fill('Ruby');
  await page.locator('input[name="tx_productscore_checkout[address][lastName]"]').fill('Ring');
  await page.locator('input[name="tx_productscore_checkout[address][street]"]').fill('Jewel Street 7');
  await page.locator('input[name="tx_productscore_checkout[address][zip]"]').fill('80331');
  await page.locator('input[name="tx_productscore_checkout[address][city]"]').fill('Munich');
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_productscore_checkout[shippingOption]"]').first().check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_productscore_checkout[paymentMethod]"][value="invoice"]').check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  // Pre-contract information: personalized items are exempt; no consent checkbox is involved
  await expect(
    page.getByText('Your order contains items made to your specifications or clearly personalized', {
      exact: false,
    }),
  ).toBeVisible();
  await expect(page.locator('#withdrawalWaiverAccepted')).toHaveCount(0);

  await page.locator('#termsAccepted').check();
  await page.getByRole('button', { name: 'Order with obligation to pay' }).click();
  await expect(page.getByText('Your order number is', { exact: false })).toBeVisible();
  const orderNumberText = await page.getByText('Your order number is', { exact: false }).textContent();
  const orderNumber = orderNumberText.replace('Your order number is', '').replace('.', '').trim();

  // The withdrawal function is not offered for an order with nothing left to withdraw
  await expect(page.locator('a.btn-outline-danger', { hasText: 'Withdraw from contract here' })).toHaveCount(0);
  await expect(
    page.getByText('All items of this order are exempt from the statutory right of withdrawal', { exact: false }),
  ).toBeVisible();

  // And the plain form refuses it as well
  await page.goto('/cancel-order');
  await page.getByLabel('Your name').fill('Ruby Ring');
  await page.getByLabel('Order number').fill(orderNumber);
  await page.getByLabel('Confirm your email address').fill('ring-buyer@example.com');
  await page.getByRole('button', { name: 'Continue' }).click();

  await expect(page.getByText('This order can no longer be cancelled online.', { exact: false })).toBeVisible();
});
