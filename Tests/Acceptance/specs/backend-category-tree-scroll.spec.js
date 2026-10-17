import { test, expect } from '@playwright/test';
import { loginToBackend } from '../helper/backend.js';

test('an expanded category tree taller than the window scrolls inside the navigation area', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 420 });
  await loginToBackend(page);
  await page.goto('/typo3/module/products/management');
  const tree = page.locator('.navigation-tree-container');
  await expect(tree.locator('[role="treeitem"]').first()).toBeVisible();

  const collapsed = tree.locator('[role="treeitem"][aria-expanded="false"]');
  for (let round = 0; round < 50 && (await collapsed.count()) > 0; round++) {
    const node = collapsed.first();
    const name = await node.locator('.node-name').textContent();
    await node.press('ArrowRight');
    await expect(tree.locator('[role="treeitem"][aria-expanded="true"]', { hasText: name })).not.toHaveCount(0);
  }

  // The tree does not stretch the backend beyond the window, so the module menu keeps its own scrolling.
  const shellHeight = await page
    .locator('.scaffold-content')
    .evaluate((element) => element.getBoundingClientRect().height);
  expect(shellHeight).toBeLessThanOrEqual(420);

  const lastNode = tree.locator('[role="treeitem"]').last();
  await tree.hover();
  await page.mouse.wheel(0, 5000);
  await expect(lastNode).toBeInViewport();
});
