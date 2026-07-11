import { test, expect } from '@playwright/test';

test('a logged-in shopper cannot cancel an order past the withdrawal period', async ({ page }) => {
  // ORD-EXPIRED-1 (see shop-demo.csv) is a fixture order dated 1700000000 (Nov 2023) - far past
  // the default 14-day withdrawal window, but still in a cancellable status ("confirmed") so the
  // window is what blocks it, not the status.
  await page.goto('/order-history');
  await page.locator('tr', { hasText: 'ORD-EXPIRED-1' }).getByRole('link', { name: 'Details' }).click();

  await page.getByRole('link', { name: 'Cancel order' }).click();

  await expect(page.getByRole('heading', { name: /Cancel order/ })).toBeVisible();
  await expect(
    page.getByText('This order can no longer be cancelled online. Please contact us directly.'),
  ).toBeVisible();
  await expect(page.getByLabel('Confirm your email address')).toHaveCount(0);
});
