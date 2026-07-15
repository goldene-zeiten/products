import { test, expect } from '@playwright/test';

test('a product flagged as unlimited stock can be ordered past its nominal stock level without error', async ({
  page,
}) => {
  await page.goto('/product/everlasting-digital-download');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');
  const row = page.locator('tr', { hasText: 'Everlasting Digital Download' });
  await row.locator('input[name="tx_productscore_basket[quantity]"]').fill('5');
  await row.getByRole('button', { name: 'Update' }).click();

  await page.goto('/checkout');
  await page
    .locator('#address-email, input[name="tx_productscore_checkout[address][email]"]')
    .fill('guest@example.com');
  await page.locator('input[name="tx_productscore_checkout[address][firstName]"]').fill('Jane');
  await page.locator('input[name="tx_productscore_checkout[address][lastName]"]').fill('Doe');
  await page.locator('input[name="tx_productscore_checkout[address][street]"]').fill('Main Street 1');
  await page.locator('input[name="tx_productscore_checkout[address][zip]"]').fill('12345');
  await page.locator('input[name="tx_productscore_checkout[address][city]"]').fill('Berlin');
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_productscore_checkout[shippingOption]"]').first().check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_productscore_checkout[paymentMethod]"]').first().check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  await expect(page.getByText('Jane Doe')).toBeVisible();
  await page.locator('#termsAccepted').check();
  await page.getByRole('button', { name: 'Order with obligation to pay' }).click();

  await expect(page.getByText('Your order number is', { exact: false })).toBeVisible();
});
