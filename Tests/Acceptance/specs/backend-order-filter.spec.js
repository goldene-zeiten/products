import { test, expect } from '@playwright/test';
import { loginToBackend } from '../helper/backend.js';

test('filtering the order list keeps the module inside the backend frame', async ({ page }) => {
  await loginToBackend(page);
  await page.goto('/typo3/module/products/order');
  const module = page.frameLocator('#typo3-contentIframe');
  await expect(module.locator('select[name="status"]')).toBeVisible();

  await module.locator('select[name="status"]').selectOption('pending');
  await module.getByRole('button', { name: 'Filter' }).click();

  // The filtered list is the module itself, called with its token, not the backend entry point.
  await expect.poll(() => page.frame({ name: 'list_frame' })?.url() ?? '').toContain('status=pending');
  expect(page.frame({ name: 'list_frame' })?.url()).toMatch(/\/typo3\/module\/products\/order\?(.*&)?token=/);
  await expect(module.locator('select[name="status"]')).toHaveValue('pending');
  // The backend shell exists once, in the top document; a module call without its token renders it again
  // inside the content frame.
  await expect(module.locator('#typo3-contentIframe')).toHaveCount(0);
  await expect(page.locator('#typo3-contentIframe')).toHaveCount(1);
});
