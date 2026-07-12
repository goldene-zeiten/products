import { test, expect } from '@playwright/test';

test('a shopper in a reseller group sees the lower of public and reseller prices under lowestWins policy', async ({
  page,
}) => {
  await page.goto('/product/reseller-precedence-shirt');

  // Reseller Precedence Shirt: 44.99 EUR base; public period = 34.99 EUR; Wholesale period = 29.99 EUR
  // With lowestWins (default), the lower reseller price 29.99 EUR applies
  await expect(page.getByText('29.99 EUR').first()).toBeVisible();
  await expect(page.getByText('34.99 EUR')).toHaveCount(0);
  await expect(page.getByText('44.99 EUR')).toHaveCount(0);
});
