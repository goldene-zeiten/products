import { test, expect } from '@playwright/test';
import { hasExpress } from '../helper/combination.js';

// Only the "express-stripe" combination installs products-express-stripe and places its plugin on the
// basket page. A real wallet sheet cannot be driven headless, so this proves the server side: the plugin
// renders the Express Checkout Element container with everything its JS needs. Stripe.js itself is
// blocked - the spec must not depend on reaching js.stripe.com.
test.describe('Stripe express checkout on the basket page', () => {
  test.skip(!hasExpress('stripe'), 'products-express-stripe not installed');

  test.beforeEach(async ({ page }) => {
    await page.route(/https:\/\/js\.stripe\.com\//, (route) => route.abort());
  });

  test('renders no button for an empty basket', async ({ page }) => {
    await page.goto('/basket');

    await expect(page.locator('[data-stripe-express-checkout]')).toHaveCount(0);
  });

  test('renders the express button container for a filled basket', async ({ page }) => {
    await page.goto('/product/blue-cotton-shirt');
    await page.getByRole('button', { name: 'Add to Basket' }).click();
    await page.goto('/basket');

    const button = page.locator('[data-stripe-express-checkout]');
    await expect(button).toHaveCount(1);
    await expect(button).toHaveAttribute('data-publishable-key', 'pk_test_mock');
    await expect(button).toHaveAttribute('data-currency', /^[a-z]{3}$/);
    await expect(button).toHaveAttribute('data-amount', /^[1-9][0-9]*$/);
    await expect(button).toHaveAttribute('data-basket-token', /.+/);
    await expect(button).toHaveAttribute('data-confirm-url', /.+/);
  });
});
