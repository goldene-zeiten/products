import { test, expect } from '@playwright/test';
import { fillAddressAndReachShipping } from '../helper/checkout.js';

test('checkout keeps the customer on the address step until the address is complete', async ({ page }) => {
  await page.goto('/product/blue-denim-jeans');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/checkout');
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await expect(page.getByText('Please enter a valid email address.')).toBeVisible();
  await expect(page.getByText('Please enter your last name.')).toBeVisible();
  await expect(page.getByText('Please enter your street and house number.')).toBeVisible();
  await expect(page.locator('input[name="tx_productscore_checkout[shippingOption]"]')).toHaveCount(0);

  await fillAddressAndReachShipping(page);

  await expect(page.locator('input[name="tx_productscore_checkout[shippingOption]"]').first()).toBeVisible();
});
