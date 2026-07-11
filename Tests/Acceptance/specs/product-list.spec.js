import { test, expect } from '@playwright/test';

test.describe('User views the home page product list', () => {
  test('the home page product list shows products and links through to their detail pages', async ({ page }) => {
    await page.goto('/');

    await expect(page.getByText('Blue Cotton Shirt')).toBeVisible();
    await expect(page.getByText('Red Cotton Shirt')).toBeVisible();

    await page.getByRole('link', { name: 'Details' }).first().click();

    await expect(page).toHaveURL(/\/product\//);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    // Product detail uses the €-symbol format, unlike basket/checkout's "EUR" text.
    await expect(page.getByText(/€\d+\.\d{2}/)).toBeVisible();
  });
});
