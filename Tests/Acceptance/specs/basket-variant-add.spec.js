import { test, expect } from '@playwright/test';

test('adding a variant article puts that article - not the base product - in the basket with its own price and stock', async ({
  page,
}) => {
  await page.goto('/product/variant-tee');

  // Base: 10.00 EUR; Large: 28.00 EUR; Small: 25.00 EUR (product detail uses €-symbol, basket uses EUR text)
  await expect(page.getByText('€10.00')).toBeVisible();
  await expect(page.getByText('In Stock (999)')).toBeVisible();

  await page.locator('.variant-attribute-select').selectOption({ label: 'Large' });
  await page.getByRole('button', { name: 'Update' }).click();

  await expect(page.getByText('€28.00')).toBeVisible();
  await expect(page.getByText('In Stock (4)')).toBeVisible();
  await expect(page.getByText('€10.00')).toHaveCount(0);

  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');

  await expect(page.getByText('Variant Tee - Large')).toBeVisible();
  await expect(page.getByText('28.00 EUR').first()).toBeVisible();
  await expect(page.getByText('10.00 EUR')).toHaveCount(0);
  await expect(page.getByText('25.00 EUR')).toHaveCount(0);
});
