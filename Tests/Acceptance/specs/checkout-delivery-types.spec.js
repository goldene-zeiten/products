import { test, expect } from '@playwright/test';

async function fillAddressAndReachShippingStep(page, email) {
  await page.goto('/product/soundmax-headphones');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/checkout');
  await page.locator('input[name="tx_productscore_checkout[address][email]"]').fill(email);
  await page.locator('input[name="tx_productscore_checkout[address][firstName]"]').fill('Del');
  await page.locator('input[name="tx_productscore_checkout[address][lastName]"]').fill('Ivery');
  await page.locator('input[name="tx_productscore_checkout[address][street]"]').fill('Some Street 3');
  await page.locator('input[name="tx_productscore_checkout[address][zip]"]').fill('33333');
  await page.locator('input[name="tx_productscore_checkout[address][city]"]').fill('Frankfurt');
  await page.getByRole('button', { name: 'Continue to payment' }).click();
}

test('choosing standard shipping is reflected on the review page', async ({ page }) => {
  await fillAddressAndReachShippingStep(page, 'standard@example.com');

  await page.locator('label', { hasText: 'Standard Shipping' }).click();
  await page.getByRole('button', { name: 'Continue to payment' }).click();
  await page.locator('input[name="tx_productscore_checkout[paymentMethod]"]').first().check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  await expect(page.getByText('Standard Shipping - 4.99', { exact: false })).toBeVisible();
});

test('choosing express shipping is reflected on the review page', async ({ page }) => {
  await fillAddressAndReachShippingStep(page, 'express@example.com');

  await page.locator('label', { hasText: 'Express Shipping' }).click();
  await page.getByRole('button', { name: 'Continue to payment' }).click();
  await page.locator('input[name="tx_productscore_checkout[paymentMethod]"]').first().check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  await expect(page.getByText('Express Shipping - 9.99', { exact: false })).toBeVisible();
});
