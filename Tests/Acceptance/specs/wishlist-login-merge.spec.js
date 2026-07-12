import { test, expect } from '@playwright/test';

test('guest wishlist is merged into account after login', async ({ page }) => {
  // As a guest, add a product to the wishlist (stored in FE session only) - WishlistController
  // has no content element of its own on the product detail page, so submitting "add" redirects
  // the browser straight to the dedicated /wishlist page, not back to the product page.
  await page.goto('/product/black-denim-jeans');
  await page.getByRole('button', { name: 'Add to wishlist' }).click();
  await expect(page).toHaveURL(/\/wishlist/);
  await expect(page.getByText('Black Denim Jeans')).toBeVisible();

  // Log in as shopper1 (the AfterUserLoggedInEvent listener will merge the session wishlist
  // into the account's persisted wishlist automatically)
  await page.goto('/login');
  const loginFrame = page.locator('.frame-type-felogin_login');
  await loginFrame.locator('input[name="user"]').fill('shopper1');
  await loginFrame.locator('input[type="password"]').fill('shopper-password');
  await loginFrame.locator('input[type=submit]').click();

  // After login, navigate to the wishlist and verify the product we added as a guest is now there
  // This proves the session wishlist was merged into the persisted account wishlist
  await page.goto('/wishlist');
  await expect(page.getByText('Black Denim Jeans')).toBeVisible();
});
