import { test, expect } from '@playwright/test';

test('a guest downloads the invoice PDF from the thank-you page', async ({ page }) => {
  await page.goto('/product/soundmax-headphones');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/checkout');
  await page.locator('input[name="tx_productscore_checkout[address][email]"]').fill('invoice-guest@example.com');
  await page.locator('input[name="tx_productscore_checkout[address][firstName]"]').fill('Ivy');
  await page.locator('input[name="tx_productscore_checkout[address][lastName]"]').fill('Voice');
  await page.locator('input[name="tx_productscore_checkout[address][street]"]').fill('Invoice Street 3');
  await page.locator('input[name="tx_productscore_checkout[address][zip]"]').fill('80331');
  await page.locator('input[name="tx_productscore_checkout[address][city]"]').fill('Munich');
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_productscore_checkout[shippingOption]"]').first().check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_productscore_checkout[paymentMethod]"][value="invoice"]').check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  await page.locator('#termsAccepted').check();
  await page.getByRole('button', { name: 'Order with obligation to pay' }).click();
  await expect(page.getByText('Your order number is', { exact: false })).toBeVisible();

  const [download] = await Promise.all([
    page.waitForEvent('download'),
    page.getByRole('link', { name: 'Download Invoice' }).click(),
  ]);

  // Invoice number is only assigned at finalize time, so assert the filename shape, not its value
  expect(download.suggestedFilename()).toMatch(/^invoice-.+\.pdf$/);
});
