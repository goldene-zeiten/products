import { test, expect } from '@playwright/test';

test('a guest cannot cancel an order by entering a different email address', async ({ page }) => {
  await page.goto('/product/black-denim-jeans');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/checkout');
  await page.locator('input[name="tx_productscore_checkout[address][email]"]').fill('mismatch-guest@example.com');
  await page.locator('input[name="tx_productscore_checkout[address][firstName]"]').fill('Mia');
  await page.locator('input[name="tx_productscore_checkout[address][lastName]"]').fill('Match');
  await page.locator('input[name="tx_productscore_checkout[address][street]"]').fill('Wrong Street 2');
  await page.locator('input[name="tx_productscore_checkout[address][zip]"]').fill('20099');
  await page.locator('input[name="tx_productscore_checkout[address][city]"]').fill('Hamburg');
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_productscore_checkout[shippingOption]"]').first().check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_productscore_checkout[paymentMethod]"][value="invoice"]').check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  await page.locator('#termsAccepted').check();
  await page.getByRole('button', { name: 'Order with obligation to pay' }).click();
  await expect(page.getByText('Your order number is', { exact: false })).toBeVisible();

  await page.getByRole('link', { name: 'Cancel order' }).click();
  await expect(page.getByRole('heading', { name: /Cancel order/ })).toBeVisible();

  await page.getByLabel('Confirm your email address').fill('someone-else@example.com');
  await page.getByRole('button', { name: 'Cancel this order' }).click();

  await expect(page.getByText('The email address does not match this order.')).toBeVisible();
  await expect(page.getByRole('heading', { name: /Cancel order/ })).toBeVisible();
});
