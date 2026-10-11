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

  // The wallet sheet's shipping-rate callback answers from the customer's own session basket, so it must
  // work in a real request, before page resolution, and report the order total the order would charge.
  test('answers the shipping-rate callback with an order total for the session basket', async ({ page }) => {
    await page.goto('/product/blue-cotton-shirt');
    await page.getByRole('button', { name: 'Add to Basket' }).click();
    await page.goto('/basket');
    const button = page.locator('[data-stripe-express-checkout]');
    const basketToken = await button.getAttribute('data-basket-token');
    const quoteUrl = await button.getAttribute('data-shipping-quote-url');

    const response = await page.request.post(quoteUrl, {
      data: { basketToken, provider: 'stripe-express', country: 'DE', postalCode: '10115' },
    });

    expect(response.status()).toBe(200);
    const quote = await response.json();
    expect(Array.isArray(quote.options)).toBe(true);
    for (const option of quote.options) {
      expect(Number.isInteger(option.orderTotal)).toBe(true);
      expect(option.orderTotal).toBeGreaterThan(0);
    }
  });
});
