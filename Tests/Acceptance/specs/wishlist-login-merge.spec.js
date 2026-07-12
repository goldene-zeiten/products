import { test, expect } from '@playwright/test';

test('guest wishlist is merged into account after login', async ({ page }) => {
  await page.goto('/product/black-denim-jeans');
  await page.getByRole('button', { name: 'Add to wishlist' }).click();
  await expect(page).toHaveURL(/\/wishlist/);
  await expect(page.getByText('Black Denim Jeans')).toBeVisible();

  await page.goto('/login');
  const loginFrame = page.locator('.frame-type-felogin_login');
  await loginFrame.locator('input[name="user"]').fill('shopper1');
  await loginFrame.locator('input[type="password"]').fill('shopper-password');
  await loginFrame.locator('input[type=submit]').click();

  await page.goto('/wishlist');
  await expect(page.getByText('Black Denim Jeans')).toBeVisible();
});
