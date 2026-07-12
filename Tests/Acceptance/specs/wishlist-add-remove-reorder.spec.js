import { test, expect } from '@playwright/test';

test('guest adds products to wishlist, reorders them, and removes them one by one', async ({ page }) => {
  await page.goto('/product/blue-cotton-shirt');
  await page.getByRole('button', { name: 'Add to wishlist' }).click();
  await expect(page).toHaveURL(/\/wishlist/);
  await expect(page.getByText('Blue Cotton Shirt')).toBeVisible();

  await page.goto('/product/red-cotton-shirt');
  await page.getByRole('button', { name: 'Add to wishlist' }).click();
  await expect(page).toHaveURL(/\/wishlist/);

  await expect(page.getByText('Blue Cotton Shirt')).toBeVisible();
  await expect(page.getByText('Red Cotton Shirt')).toBeVisible();

  const titles = page.locator('h5.card-title');
  await expect(titles.nth(0)).toContainText('Blue Cotton Shirt');
  await expect(titles.nth(1)).toContainText('Red Cotton Shirt');

  // "Move up" is hidden for the first item, so use the second (Red) to swap positions
  const redProductCard = page.locator('.card', { has: page.locator('.card-title', { hasText: 'Red Cotton Shirt' }) });
  await redProductCard.getByRole('link', { name: 'Move up' }).click();

  await expect(titles.nth(0)).toContainText('Red Cotton Shirt');
  await expect(titles.nth(1)).toContainText('Blue Cotton Shirt');

  const firstProductCard = page.locator('.card').first();
  await firstProductCard.getByRole('link', { name: 'Remove' }).click();

  await expect(page.getByText('Red Cotton Shirt')).toHaveCount(0);
  await expect(page.getByText('Blue Cotton Shirt')).toBeVisible();

  const lastProductCard = page.locator('.card').first();
  await lastProductCard.getByRole('link', { name: 'Remove' }).click();

  await expect(page.getByText('Your wishlist is empty.')).toBeVisible();
});
