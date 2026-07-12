import { test, expect } from '@playwright/test';

test('a logged-in shopper cannot cancel an order past the withdrawal period', async ({ page }) => {
  // ORD-EXPIRED-1 fixture: status "confirmed" (cancellable) but dated past the 14-day window
  await page.goto('/order-history');
  await page.locator('tr', { hasText: 'ORD-EXPIRED-1' }).getByRole('link', { name: 'Details' }).click();

  await page.getByRole('link', { name: 'Cancel order' }).click();

  await expect(page.getByRole('heading', { name: /Cancel order/ })).toBeVisible();
  await expect(
    page.getByText('This order can no longer be cancelled online. Please contact us directly.'),
  ).toBeVisible();
  await expect(page.getByLabel('Confirm your email address')).toHaveCount(0);
});
