import { test, expect } from '@playwright/test';

test('the header badge shows the basket count on a cached page', async ({ page }) => {
  await page.goto('/');
  const badge = page.locator('products-basket-summary [data-basket-count]');
  await expect(badge).toBeHidden();

  await page.goto('/product/blue-cotton-shirt');
  await page.locator('input[name="tx_productscore_basket[quantity]"]').fill('2');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/');
  await expect(badge).toBeVisible();
  await expect(badge).toHaveText('2');
  await expect(page.locator('products-basket-summary a')).toHaveAttribute('title', /59\.98/);
});
