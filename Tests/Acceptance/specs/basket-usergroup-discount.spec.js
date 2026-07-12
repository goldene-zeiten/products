import { test, expect } from '@playwright/test';

test('a shopper in a discounted FE usergroup sees the reduced price in the basket', async ({ page }) => {
  await page.goto('/product/red-cotton-shirt');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');

  // Red Cotton Shirt: 29.99 EUR; shopper1's "Wholesale" fe_group carries a 10% discount = 26.99 EUR
  await expect(page.getByText('26.99 EUR').first()).toBeVisible();
  await expect(page.getByText('29.99 EUR')).toHaveCount(0);
});
