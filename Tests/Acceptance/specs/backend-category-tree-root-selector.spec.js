import { test, expect } from '@playwright/test';
import { loginToBackend } from '../helper/backend.js';

test('a tree with several root categories shows one root at a time and remembers it', async ({ page }) => {
  await loginToBackend(page);
  await page.goto('/typo3/module/products/management');
  const navigation = page.locator('goldene-zeiten-products-category-tree');
  const rootSelect = navigation.locator('select[data-tree-root-selector]');
  const topLevelNames = navigation.locator('[role="treeitem"][aria-level="1"] .node-name');

  await expect(rootSelect).toBeVisible();
  await expect(rootSelect.locator('option')).toHaveText(['Clothing', 'Electronics']);
  await expect(topLevelNames).toHaveText(['Clothing']);

  await rootSelect.selectOption({ label: 'Electronics' });
  await expect(topLevelNames).toHaveText(['Electronics']);

  await page.reload();
  await expect(navigation.locator('select[data-tree-root-selector]')).toHaveValue('category-5');
  await expect(topLevelNames).toHaveText(['Electronics']);
});
