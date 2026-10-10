import { test, expect } from '@playwright/test';
import { hasExpress } from '../helper/combination.js';

// Only the "express-paypal" combination installs products-express-paypal and places its plugin on the
// basket page. The PayPal sheet cannot be driven headless, so this proves the server side: the plugin
// renders the Smart Buttons container with everything its JS needs. The PayPal JS SDK itself is blocked -
// the spec must not depend on reaching paypal.com.
test.describe('PayPal express checkout on the basket page', () => {
  test.skip(!hasExpress('paypal'), 'products-express-paypal not installed');

  test.beforeEach(async ({ page }) => {
    await page.route(/https:\/\/([a-z0-9-]+\.)*paypal(objects)?\.com\//, (route) => route.abort());
  });

  test('renders no button for an empty basket', async ({ page }) => {
    await page.goto('/basket');

    await expect(page.locator('[data-paypal-express-checkout]')).toHaveCount(0);
  });

  test('renders the express button container for a filled basket', async ({ page }) => {
    await page.goto('/product/blue-cotton-shirt');
    await page.getByRole('button', { name: 'Add to Basket' }).click();
    await page.goto('/basket');

    const button = page.locator('[data-paypal-express-checkout]');
    await expect(button).toHaveCount(1);
    await expect(button).toHaveAttribute('data-client-id', 'mock-client');
    await expect(button).toHaveAttribute('data-currency', /^[A-Za-z]{3}$/);
    await expect(button).toHaveAttribute('data-basket-token', /.+/);
    await expect(button).toHaveAttribute('data-create-url', /.+/);
    await expect(button).toHaveAttribute('data-shipping-url', /.+/);
    await expect(button).toHaveAttribute('data-confirm-url', /.+/);
  });
});
