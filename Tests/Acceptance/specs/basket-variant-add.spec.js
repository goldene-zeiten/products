import { test, expect } from '@playwright/test';

test('adding a variant article puts that article - not the base product - in the basket with its own price and stock', async ({
  page,
}) => {
  await page.goto('/product/variant-tee');

  // Base: 10.00 EUR; Large: 28.00 EUR; Small: 25.00 EUR. Only the articles are for sale, so the cheapest
  // one is the price shown until a variant is chosen.
  await expect(page.getByText('From €25.00')).toBeVisible();
  await expect(page.getByText('€10.00')).toHaveCount(0);
  await expect(page.getByText('In Stock (999)')).toBeVisible();

  // The variant chooser submits itself on change; the "Update" button is the no-JavaScript fallback.
  await Promise.all([
    page.waitForNavigation(),
    page.locator('.variant-attribute-select').selectOption({ label: 'Large' }),
  ]);

  await expect(page.getByText('€28.00')).toBeVisible();
  await expect(page.getByText('In Stock (4)')).toBeVisible();
  await expect(page.getByText('€10.00')).toHaveCount(0);

  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');

  await expect(page.getByText('Variant Tee - Large')).toBeVisible();
  await expect(page.getByText('€28.00').first()).toBeVisible();
  await expect(page.getByText('€10.00')).toHaveCount(0);
  await expect(page.getByText('€25.00')).toHaveCount(0);
});
