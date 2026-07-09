import { test, expect } from '@playwright/test';

test('a shopper in a discounted FE usergroup sees the reduced price in the basket', async ({ page }) => {
  await page.goto('/product/red-cotton-shirt');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');

  // Red Cotton Shirt's list price is 29.99 EUR (see shop-demo.csv) - shopper1 belongs to the
  // "Wholesale" fe_groups row carrying a 10% discount, so the basket should show 26.99 EUR
  // instead of the anonymous price. Quantity is 1, so the unit price cell and the line total
  // cell both read "26.99 EUR" - matching either one is enough proof, so use .first().
  await expect(page.getByText('26.99 EUR').first()).toBeVisible();
  await expect(page.getByText('29.99 EUR')).toHaveCount(0);
});
