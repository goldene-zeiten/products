import { test, expect } from '@playwright/test';

test('a ring is ordered in the chosen size with an engraving', async ({ page }) => {
  await page.goto('/product/signet-ring');
  await page.getByLabel('Ring size').selectOption('64');
  await page.getByLabel('Engraving').fill('Anna & Ben');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/product/signet-ring');
  await page.getByLabel('Ring size').selectOption('52');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/basket');
  const engravedLine = page.locator('tr', { hasText: 'Engraving: Anna & Ben' });
  await expect(engravedLine).toContainText('Ring size: 64 (oversize) (+ €15.00)');
  await expect(engravedLine).toContainText('€145.00');
  const plainLine = page.locator('tr', { hasText: 'Ring size: 52' });
  await expect(plainLine).toContainText('€120.00');

  // Each line is changed on its own.
  await plainLine.getByRole('button', { name: 'Remove' }).click();
  await expect(page.locator('tr', { hasText: 'Ring size: 52' })).toHaveCount(0);
  await expect(page.locator('tr', { hasText: 'Engraving: Anna & Ben' })).toBeVisible();

  await page.goto('/checkout');
  await page.locator('input[name="tx_productscore_checkout[address][email]"]').fill('signet-buyer@example.com');
  await page.locator('input[name="tx_productscore_checkout[address][firstName]"]').fill('Anna');
  await page.locator('input[name="tx_productscore_checkout[address][lastName]"]').fill('Ring');
  await page.locator('input[name="tx_productscore_checkout[address][street]"]').fill('Jewel Street 7');
  await page.locator('input[name="tx_productscore_checkout[address][zip]"]').fill('80331');
  await page.locator('input[name="tx_productscore_checkout[address][city]"]').fill('Munich');
  await page.getByRole('button', { name: 'Continue to payment' }).click();
  await page.locator('input[name="tx_productscore_checkout[shippingOption]"]').first().check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();
  await page.locator('input[name="tx_productscore_checkout[paymentMethod]"][value="invoice"]').check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  // The engraving makes the ring personalized goods.
  await expect(page.getByText('Engraving: Anna & Ben')).toBeVisible();
  await expect(
    page.getByText('Your order contains items made to your specifications or clearly personalized', {
      exact: false,
    }),
  ).toBeVisible();

  await page.locator('#termsAccepted').check();
  await page.getByRole('button', { name: 'Order with obligation to pay' }).click();
  await expect(page.getByText('Your order number is', { exact: false })).toBeVisible();
  await expect(page.getByText('Engraving: Anna & Ben')).toBeVisible();
});

test('an engraving with characters an engraver cannot cut is refused', async ({ page }) => {
  await page.goto('/product/signet-ring');
  await page.getByLabel('Ring size').selectOption('54');
  await page.getByLabel('Engraving').fill('Anna <3');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await expect(page.getByText('contains characters that cannot be engraved', { exact: false })).toBeVisible();
  await expect(page.getByText('Your basket is empty', { exact: false })).toBeVisible();
});
