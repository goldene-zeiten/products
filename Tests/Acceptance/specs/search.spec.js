import { test, expect } from '@playwright/test';

test.describe('User searches a product', () => {
  test('searching a shared term returns every matching product', async ({ page }) => {
    await page.goto('/');
    const results = page.locator('.product-search');
    await results.locator('input[name="tx_productssearch_search[term]"]').fill('Shirt');
    await results.locator('input[type=submit]').click();

    await expect(results.getByText('Blue Cotton Shirt')).toBeVisible();
    await expect(results.getByText('Red Cotton Shirt')).toBeVisible();
  });

  test('a different search term returns a different set of products', async ({ page }) => {
    await page.goto('/');
    const results = page.locator('.product-search');
    await results.locator('input[name="tx_productssearch_search[term]"]').fill('Jeans');
    await results.locator('input[type=submit]').click();

    await expect(results.getByText('Black Denim Jeans')).toBeVisible();
    await expect(results.getByText('Blue Denim Jeans')).toBeVisible();
    await expect(results.getByText('Blue Cotton Shirt')).toHaveCount(0);
  });

  test("clicking a search result leads to that product's detail page", async ({ page }) => {
    await page.goto('/');
    const results = page.locator('.product-search');
    await results.locator('input[name="tx_productssearch_search[term]"]').fill('UltraBook');
    await results.locator('input[type=submit]').click();

    await results.getByRole('link', { name: 'Details' }).first().click();

    await expect(page.getByRole('heading', { level: 1 })).toContainText('UltraBook');
  });
});
