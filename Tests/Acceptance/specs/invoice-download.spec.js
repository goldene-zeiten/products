import { test, expect } from '@playwright/test';

test('a guest downloads the invoice PDF from the thank-you page', async ({ page }) => {
  await page.goto('/product/soundmax-headphones');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/checkout');
  await page.locator('input[name="tx_products_checkout[address][email]"]').fill('invoice-guest@example.com');
  await page.locator('input[name="tx_products_checkout[address][firstName]"]').fill('Ivy');
  await page.locator('input[name="tx_products_checkout[address][lastName]"]').fill('Voice');
  await page.locator('input[name="tx_products_checkout[address][street]"]').fill('Invoice Street 3');
  await page.locator('input[name="tx_products_checkout[address][zip]"]').fill('80331');
  await page.locator('input[name="tx_products_checkout[address][city]"]').fill('Munich');
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_products_checkout[shippingMethod]"]').first().check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_products_checkout[paymentMethod]"]').first().check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  await page.getByRole('button', { name: 'Place order' }).click();
  await expect(page.getByText('Your order number is', { exact: false })).toBeVisible();

  const [download] = await Promise.all([
    page.waitForEvent('download'),
    page.getByRole('link', { name: 'Download Invoice' }).click(),
  ]);

  // InvoiceController::downloadAction names the file after the order's invoice number, which is
  // only assigned at finalize time - assert the shape rather than a value we can't predict here.
  expect(download.suggestedFilename()).toMatch(/^invoice-.+\.pdf$/);
});
