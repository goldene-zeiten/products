import { test, expect } from '@playwright/test';

test('a product under a discounted category shows the cascaded discount in the basket', async ({ page }) => {
  await page.goto('/product/white-silk-blouse');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');

  // White Silk Blouse's list price is 39.99 EUR (see shop-demo.csv), directly assigned to
  // category "Shirts" (womens-shirts), which has no discount of its own - but its ancestor
  // "Women" carries a 15% discount. maxAcrossTree mode (the default) cascades that down through
  // Shirts -> Women, so the basket should show 33.99 EUR instead of the undiscounted price.
  await expect(page.getByText('33.99 EUR').first()).toBeVisible();
  await expect(page.getByText('39.99 EUR')).toHaveCount(0);
});
