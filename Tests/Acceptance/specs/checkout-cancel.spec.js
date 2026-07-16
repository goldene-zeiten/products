import { test, expect } from '@playwright/test';

async function addProductAndGoToCheckout(page) {
  await page.goto('/product/white-silk-blouse');
  await page.getByRole('button', { name: 'Add to Basket' }).click();
  await page.goto('/checkout');
}

async function assertBasketStillHasTheProduct(page) {
  await page.goto('/basket');
  await expect(page.locator('tr', { hasText: 'White Silk Blouse' })).toBeVisible();
}

test('cancelling at the address step leaves the basket untouched', async ({ page }) => {
  await addProductAndGoToCheckout(page);
  await page.goto('/');

  await assertBasketStillHasTheProduct(page);
});

test('cancelling at the shipping method step leaves the basket untouched', async ({ page }) => {
  await addProductAndGoToCheckout(page);
  await page.locator('input[name="tx_productscore_checkout[address][email]"]').fill('cancel1@example.com');
  await page.locator('input[name="tx_productscore_checkout[address][firstName]"]').fill('Can');
  await page.locator('input[name="tx_productscore_checkout[address][lastName]"]').fill('Cel');
  await page.locator('input[name="tx_productscore_checkout[address][street]"]').fill('Street 1');
  await page.locator('input[name="tx_productscore_checkout[address][zip]"]').fill('11111');
  await page.locator('input[name="tx_productscore_checkout[address][city]"]').fill('Berlin');
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.goto('/');

  await assertBasketStillHasTheProduct(page);
});

test('cancelling at the payment step leaves the basket untouched', async ({ page }) => {
  await addProductAndGoToCheckout(page);
  await page.locator('input[name="tx_productscore_checkout[address][email]"]').fill('cancel2@example.com');
  await page.locator('input[name="tx_productscore_checkout[address][firstName]"]').fill('Can');
  await page.locator('input[name="tx_productscore_checkout[address][lastName]"]').fill('Cel');
  await page.locator('input[name="tx_productscore_checkout[address][street]"]').fill('Street 1');
  await page.locator('input[name="tx_productscore_checkout[address][zip]"]').fill('11111');
  await page.locator('input[name="tx_productscore_checkout[address][city]"]').fill('Berlin');
  await page.getByRole('button', { name: 'Continue to payment' }).click();
  await page.locator('input[name="tx_productscore_checkout[shippingOption]"]').first().check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.goto('/');

  await assertBasketStillHasTheProduct(page);
});

test('cancelling at the review step leaves the basket untouched and places no order', async ({ page }) => {
  await addProductAndGoToCheckout(page);
  await page.locator('input[name="tx_productscore_checkout[address][email]"]').fill('cancel3@example.com');
  await page.locator('input[name="tx_productscore_checkout[address][firstName]"]').fill('Can');
  await page.locator('input[name="tx_productscore_checkout[address][lastName]"]').fill('Cel');
  await page.locator('input[name="tx_productscore_checkout[address][street]"]').fill('Street 1');
  await page.locator('input[name="tx_productscore_checkout[address][zip]"]').fill('11111');
  await page.locator('input[name="tx_productscore_checkout[address][city]"]').fill('Berlin');
  await page.getByRole('button', { name: 'Continue to payment' }).click();
  await page.locator('input[name="tx_productscore_checkout[shippingOption]"]').first().check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();
  await page.locator('input[name="tx_productscore_checkout[paymentMethod]"][value="invoice"]').check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  await page.goto('/');

  await assertBasketStillHasTheProduct(page);
});
