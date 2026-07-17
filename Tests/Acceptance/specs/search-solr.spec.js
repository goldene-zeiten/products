import { test, expect } from '@playwright/test';
import { currentCombination } from '../helper/combination.js';

// Only the "solr" combination installs goldene-zeiten/products-solr, activates the index-queue +
// search TypoScript and boots a live Apache Solr server (Build/Scripts/runTests.sh). Every other
// instance build lacks the plugin, the connection and the index, so this spec self-skips there.
test.describe('User searches products through Solr', () => {
  test.skip(currentCombination !== 'solr', 'products-solr not installed');

  // The shipped Result/Document override renders every product title twice per card (the linked
  // heading and the highlighted content teaser), so results are matched on the heading role to stay
  // unambiguous.
  test('a Solr query renders the matching indexed products', async ({ page }) => {
    // The solr_pi_results plugin (page uid 2, /shop) renders results whenever tx_solr[q] is present.
    await page.goto('/shop?tx_solr%5Bq%5D=Shirt');

    await expect(page.getByRole('heading', { name: 'Blue Cotton Shirt' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Red Cotton Shirt' })).toBeVisible();
  });

  test('a different Solr query returns a different set of products', async ({ page }) => {
    await page.goto('/shop?tx_solr%5Bq%5D=Jeans');

    await expect(page.getByRole('heading', { name: 'Black Denim Jeans' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Blue Denim Jeans' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Blue Cotton Shirt' })).toHaveCount(0);
  });

  test('facets render and the hierarchy facet no longer crashes the results plugin', async ({ page }) => {
    await page.goto('/shop?tx_solr%5Bq%5D=Shirt');

    // The results plugin renders its product cards. This is the primary proof of the categoryTree
    // hierarchy-facet fix: the facet is configured with the shipped demo categories (Clothing > Men >
    // Shirts), and before the partial was switched from Rootline to Hierarchy it rendered
    // f:cObject "lib.tx_solr.pagetitle" - an undefined path that crashed the whole plugin and replaced
    // it with the TYPO3 "Oops" error page. A rendered result set proves that crash is gone.
    await expect(page.locator('.product-search-result').first()).toBeVisible();
    await expect(page.getByText('Oops, an error occurred!')).toHaveCount(0);

    // Flat category facet (#facetcategory): the "Shirts" leaf category, built from the category_stringM
    // SOLR_RELATION on the real category titles. Scoped to its own facet block because "Shirts" also
    // appears as the leaf of the hierarchy facet below. Its presence proves Solr returned faceted counts
    // for the indexed documents (the demo shirts live in Clothing > Men > Shirts).
    await expect(page.locator('#facetcategory').getByRole('link', { name: 'Shirts' })).toBeVisible();

    // Hierarchy (categoryTree) facet (#facetcategoryTree): built by ProductIndexFieldMapper->categoryPaths
    // - a userFunc that now indexes correctly on v14 (it carries #[AsAllowedCallable] and receives the
    // record via the new setContentObjectRenderer() setter) - and rendered by EXT:solr's generic Hierarchy
    // partial. It must show the top ancestor label "Clothing" (from the Clothing > Men > Shirts chain).
    await expect(page.locator('#facetcategoryTree').getByRole('link', { name: 'Clothing' })).toBeVisible();
  });

  test('the attribute facet renders real indexed attribute values', async ({ page }) => {
    // The demo "Variant Tee" (uid 113) is the product carrying article/attribute data (Size: Small,
    // Size: Large). Its attribute_stringM field is produced by ProductIndexFieldMapper->attributeValues,
    // the second userFunc that only indexes once the record is injected via setContentObjectRenderer();
    // a non-empty attribute facet here proves that multi-hop mapping now works on both cores.
    await page.goto('/shop?tx_solr%5Bq%5D=Variant');

    await expect(page.getByRole('heading', { name: 'Variant Tee' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Size: Small' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Size: Large' })).toBeVisible();
  });
});
