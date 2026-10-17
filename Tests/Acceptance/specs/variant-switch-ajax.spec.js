import { test, expect } from '@playwright/test';
import { fileURLToPath } from 'node:url';

test('in ajax switch mode a variant change swaps price and stock in place without reloading the page', async ({
  page,
}) => {
  // The acceptance web server routes every request through TYPO3's index.php and serves no static files,
  // so the selector module is delivered from the package directly.
  await page.route('**/JavaScript/frontend/article-selector.js*', (route) =>
    route.fulfill({
      path: fileURLToPath(
        new URL(
          '../../../packages/goldene-zeiten/products-core/Resources/Public/JavaScript/frontend/article-selector.js',
          import.meta.url,
        ),
      ),
      contentType: 'text/javascript',
    }),
  );
  await page.goto('/product/variant-tee');
  await expect(page.getByText('From €25.00')).toBeVisible();

  // The shop runs in the default "reload" mode; the selector reads the mode on every change.
  await page.locator('[data-product-variant-selector]').evaluate((container) => {
    container.dataset.switchMode = 'ajax';
  });
  let navigations = 0;
  page.on('framenavigated', (frame) => {
    if (frame === page.mainFrame()) {
      navigations++;
    }
  });

  const switchResponse = page.waitForResponse((response) => response.request().method() === 'POST');
  await page.locator('.variant-attribute-select').selectOption({ label: 'Large' });
  expect((await switchResponse).status()).toBe(200);

  await expect(page.getByText('€28.00')).toBeVisible();
  await expect(page.getByText('In Stock (4)')).toBeVisible();
  expect(navigations).toBe(0);

  await page.getByRole('button', { name: 'Add to Basket' }).click();
  await page.goto('/basket');
  await expect(page.getByText('Variant Tee - Large')).toBeVisible();
});
