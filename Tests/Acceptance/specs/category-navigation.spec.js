import { test, expect } from '@playwright/test';

test.describe('User navigates through the categories', () => {
  test("clicking down the tree to a leaf category shows that category's products", async ({ page }) => {
    await page.goto('/categories');

    await page.getByRole('link', { name: 'Clothing', exact: true }).click();
    await page.getByRole('link', { name: 'Men', exact: true }).click();
    await page.getByRole('link', { name: 'Shirts', exact: true }).first().click();

    await expect(page).toHaveURL(/\/categories\/clothing\/men\/mens-shirts$/);
    await expect(page.getByText('Blue Cotton Shirt')).toBeVisible();
    await expect(page.getByText('Red Cotton Shirt')).toBeVisible();
  });

  test('the nested slug is built from the full category ancestry', async ({ page }) => {
    await page.goto('/categories/electronics/phones/smartphones');

    await expect(page.getByText('Photon X100 Smartphone')).toBeVisible();
    await expect(page.getByText('Photon X200 Smartphone')).toBeVisible();
  });

  test('clicking a product from a category listing leads to its detail page', async ({ page }) => {
    await page.goto('/categories/electronics/laptops/ultrabooks');

    await page.getByRole('link', { name: 'Details' }).first().click();

    await expect(page.getByRole('heading', { level: 1 })).toContainText('UltraBook');
  });
});
