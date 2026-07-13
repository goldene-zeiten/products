import { test, expect } from '@playwright/test';

test('a shopper in a reseller group sees the lower of public and reseller prices under lowestWins policy', async ({
  page,
}) => {
  await page.goto('/product/reseller-precedence-shirt');

  // Reseller Precedence Shirt: 44.99 EUR base; public period = 34.99 EUR; Wholesale period = 29.99 EUR
  // With lowestWins (default), the lower reseller price 29.99 EUR applies - shopper1 also
  // belongs to the "Wholesale" fe_group, which carries its own 10% basket discount
  // (tx_products_discount_percent), so the price actually shown/charged is 29.99 * 0.9 = 26.99.
  await expect(page.getByText('€26.99').first()).toBeVisible();
  await expect(page.getByText('€34.99')).toHaveCount(0);
  await expect(page.getByText('€44.99')).toHaveCount(0);
});
