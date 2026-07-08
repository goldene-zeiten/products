import { test, expect } from '@playwright/test';

test('user removes every item and the basket is really empty afterwards', async ({ page }) => {
  await page.goto('/product/photon-x100-smartphone');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/product/photon-x100-smartphone');
  await page.getByRole('link', { name: 'Photon Phone Case' }).click();
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');
  await page.locator('tr', { hasText: 'Photon X100 Smartphone' }).getByRole('link', { name: 'Remove' }).click();
  await page.locator('tr', { hasText: 'Photon Phone Case' }).getByRole('link', { name: 'Remove' }).click();

  await expect(page.getByText('Your basket is empty.')).toBeVisible();
});
