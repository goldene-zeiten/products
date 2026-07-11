import { test, expect } from '@playwright/test';

test('adding a variant article puts that article - not the base product - in the basket with its own price and stock', async ({
  page,
}) => {
  await page.goto('/product/variant-tee');

  // Variant Tee's own (never-sold) base price is 10.00 EUR; with no article selected yet, the
  // price/stock block still falls back to the base product's own figures (see shop-demo.csv).
  // The product detail page renders prices via the p:format.money ViewHelper (ICU currency
  // formatting, e.g. "€10.00") - the basket/order pages instead use plain
  // "{decimalString} {currency}" ("10.00 EUR"), asserted further down.
  await expect(page.getByText('€10.00')).toBeVisible();
  await expect(page.getByText('In Stock (999)')).toBeVisible();

  // Selecting the "Large" attribute value and submitting the reload form (the explicit "Update"
  // button - the no-JS-fallback path exercised directly, rather than relying on
  // Resources/Public/JavaScript/article-selector.js's auto-submit-on-change timing) resolves to
  // the "Variant Tee - Large" article, which carries its own price (28.00 EUR) and stock (4),
  // distinct from both the base product and the "Small" article (25.00 EUR / 10 in stock).
  await page.locator('.variant-attribute-select').selectOption({ label: 'Large' });
  await page.getByRole('button', { name: 'Update' }).click();

  await expect(page.getByText('€28.00')).toBeVisible();
  await expect(page.getByText('In Stock (4)')).toBeVisible();
  await expect(page.getByText('€10.00')).toHaveCount(0);

  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');

  // The basket line must show the article's own title and price, not the product's.
  await expect(page.getByText('Variant Tee - Large')).toBeVisible();
  await expect(page.getByText('28.00 EUR').first()).toBeVisible();
  await expect(page.getByText('10.00 EUR')).toHaveCount(0);
  await expect(page.getByText('25.00 EUR')).toHaveCount(0);
});
