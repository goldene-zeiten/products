import { test, expect } from '@playwright/test';

test('visiting products records them in recently-viewed list, and re-visiting moves a product to the front', async ({
  page,
}) => {
  // Visit three products in sequence
  await page.goto('/product/photon-x100-smartphone');
  await page.goto('/product/photon-x200-smartphone');
  await page.goto('/product/soundmax-headphones');

  // Go to recently-viewed page
  await page.goto('/recently-viewed');

  // Assert all three product titles are visible
  await expect(page.getByText('Photon X100 Smartphone')).toBeVisible();
  await expect(page.getByText('Photon X200 Smartphone')).toBeVisible();
  await expect(page.getByText('SoundMax Headphones')).toBeVisible();

  // Assert order is most-recently-viewed first: SoundMax, Photon X200, Photon X100
  const cardTitles = page.locator('.card-title');
  await expect(cardTitles.nth(0)).toContainText('SoundMax Headphones');
  await expect(cardTitles.nth(1)).toContainText('Photon X200 Smartphone');
  await expect(cardTitles.nth(2)).toContainText('Photon X100 Smartphone');

  // Re-visit the first product (should move it to the front)
  await page.goto('/product/photon-x100-smartphone');

  // Go back to recently-viewed
  await page.goto('/recently-viewed');

  // Assert Photon X100 is now first
  await expect(cardTitles.nth(0)).toContainText('Photon X100 Smartphone');

  // Assert still only 3 products (no duplicate entry)
  await expect(cardTitles).toHaveCount(3);

  // Assert new order: Photon X100, SoundMax, Photon X200
  await expect(cardTitles.nth(1)).toContainText('SoundMax Headphones');
  await expect(cardTitles.nth(2)).toContainText('Photon X200 Smartphone');
});
