import { test, expect } from '@playwright/test';

async function addHeadphonesAndFillAddress(page, email) {
  await page.goto('/product/soundmax-headphones');
  await page.getByRole('button', { name: 'Add to Basket' }).click();
  await page.goto('/checkout');
  await page.locator('input[name="tx_productscore_checkout[address][email]"]').fill(email);
  await page.locator('input[name="tx_productscore_checkout[address][firstName]"]').fill('Paula');
  await page.locator('input[name="tx_productscore_checkout[address][lastName]"]').fill('Pickup');
  await page.locator('input[name="tx_productscore_checkout[address][street]"]').fill('Collect Street 3');
  await page.locator('input[name="tx_productscore_checkout[address][zip]"]').fill('20095');
  await page.locator('input[name="tx_productscore_checkout[address][city]"]').fill('Hamburg');
  await page.getByRole('button', { name: 'Continue to payment' }).click();
}

const paymentRadio = (page, method) =>
  page.locator(`input[name="tx_productscore_checkout[paymentMethod]"][value="${method}"]`);

test('an order collected in store is paid cash with a discount, never by invoice', async ({ page }) => {
  await addHeadphonesAndFillAddress(page, 'pickup-buyer@example.com');

  await page.getByLabel('Pick-up at Hamburg (Jungfernstieg 1, 20354 Hamburg)').check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await expect(paymentRadio(page, 'cash')).toBeVisible();
  await expect(paymentRadio(page, 'invoice')).toHaveCount(0);
  await paymentRadio(page, 'cash').check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  // 89.99 goods - 3 % (2.70) for paying cash, nothing for collecting.
  await expect(page.locator('.order-summary-discount')).toContainText('-€2.70');
  await expect(page.locator('.order-summary-total')).toContainText('€87.29');

  await page.locator('#termsAccepted').check();
  await page.getByRole('button', { name: 'Order with obligation to pay' }).click();
  await expect(page.getByText('Your order number is', { exact: false })).toBeVisible();
});

test('a shipped order offers no cash payment', async ({ page }) => {
  await addHeadphonesAndFillAddress(page, 'shipped-buyer@example.com');

  await page.locator('input[name="tx_productscore_checkout[shippingOption]"]').first().check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await expect(paymentRadio(page, 'invoice')).toBeVisible();
  await expect(paymentRadio(page, 'cash')).toHaveCount(0);
});

test('prepayment shows the bank details right after ordering', async ({ page }) => {
  await addHeadphonesAndFillAddress(page, 'prepayment-buyer@example.com');

  await page.locator('input[name="tx_productscore_checkout[shippingOption]"]').first().check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();
  await paymentRadio(page, 'prepayment').check();
  await page.getByRole('button', { name: 'Continue to review' }).click();
  await page.locator('#termsAccepted').check();
  await page.getByRole('button', { name: 'Order with obligation to pay' }).click();

  const instructions = page.locator('.payment-instructions');
  await expect(instructions).toContainText('IBAN DE02 1203 0000 0000 2020 51');
  await expect(instructions).toContainText('Products Acceptance');
});
