import { test, expect } from '@playwright/test';

test('visiting products records them in recently-viewed list, and re-visiting moves a product to the front', async ({
  page,
}) => {
  await page.goto('/product/photon-x100-smartphone');
  await page.goto('/product/photon-x200-smartphone');
  await page.goto('/product/soundmax-headphones');

  await page.goto('/recently-viewed');

  await expect(page.getByText('Photon X100 Smartphone')).toBeVisible();
  await expect(page.getByText('Photon X200 Smartphone')).toBeVisible();
  await expect(page.getByText('SoundMax Headphones')).toBeVisible();

  const cardTitles = page.locator('.card-title');
  await expect(cardTitles.nth(0)).toContainText('SoundMax Headphones');
  await expect(cardTitles.nth(1)).toContainText('Photon X200 Smartphone');
  await expect(cardTitles.nth(2)).toContainText('Photon X100 Smartphone');

  await page.goto('/product/photon-x100-smartphone');

  await page.goto('/recently-viewed');

  await expect(cardTitles.nth(0)).toContainText('Photon X100 Smartphone');

  await expect(cardTitles).toHaveCount(3);

  await expect(cardTitles.nth(1)).toContainText('SoundMax Headphones');
  await expect(cardTitles.nth(2)).toContainText('Photon X200 Smartphone');
});
