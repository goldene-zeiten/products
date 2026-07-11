import { test, expect } from '@playwright/test';

/**
 * Parses "12.34 EUR" (or "1,234.56 EUR") out of a summary table row's text content, given the
 * row's leading label (e.g. "Total Gross").
 */
async function rowValue(page, label) {
  const text = await page.locator('tr', { hasText: label }).textContent();
  const match = text.match(/([\d.,]+)\s*EUR/);
  return parseFloat(match[1].replace(',', ''));
}

/**
 * shopper1's basket is server-side session state shared across every spec in the e2e-logged-in
 * project (e.g. basket-usergroup-discount.spec.js adds a Red Cotton Shirt and never removes it) -
 * clear it first so this test's own total/point arithmetic isn't polluted by a sibling spec's
 * leftovers.
 */
async function emptyBasket(page) {
  await page.goto('/basket');
  let removeLink = page.locator('tbody tr').getByRole('link', { name: 'Remove' }).first();
  while (await removeLink.count() > 0) {
    await removeLink.click();
    removeLink = page.locator('tbody tr').getByRole('link', { name: 'Remove' }).first();
  }
}

async function goThroughCheckoutToReview(page) {
  await page.goto('/checkout');
  await page.locator('input[name="tx_products_checkout[address][email]"]').fill('shopper1@example.com');
  await page.locator('input[name="tx_products_checkout[address][firstName]"]').fill('Sam');
  await page.locator('input[name="tx_products_checkout[address][lastName]"]').fill('Shopper');
  await page.locator('input[name="tx_products_checkout[address][street]"]').fill('Shop Street 5');
  await page.locator('input[name="tx_products_checkout[address][zip]"]').fill('54321');
  await page.locator('input[name="tx_products_checkout[address][city]"]').fill('Hamburg');
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_products_checkout[shippingMethod]"]').first().check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_products_checkout[paymentMethod]"]').first().check();
  await page.getByRole('button', { name: 'Continue to review' }).click();
}

test('spending credit points reduces the order total, and points earned from the order raise the balance', async ({ page }) => {
  // shopper1 starts with a balance of 100 points (see shop-demo.csv), worth 0.10 EUR each -
  // spending 50 of them must knock exactly 5.00 EUR off the order total.
  await emptyBasket(page);
  await page.goto('/product/reward-mug');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await goThroughCheckoutToReview(page);

  await expect(page.getByText('You have 100 credit points available.')).toBeVisible();

  await page.locator('#spendPoints').fill('50');
  await page.getByRole('button', { name: 'Place order' }).click();

  await expect(page.getByText('Your order number is', { exact: false })).toBeVisible();

  // The placed order's own Total Net + Tax is the pre-redemption subtotal (goods, wholesale
  // discount and shipping already applied) - crediting points is the only thing that can make the
  // displayed Total Gross come in under that subtotal, so the gap between them is exactly the
  // redemption discount. (The Review step's own Total Gross can't be used as a "before" baseline
  // instead: that partial renders the basket alone, before shipping is added at order placement.)
  const totalNet = await rowValue(page, 'Total Net');
  const totalTax = await rowValue(page, 'Tax');
  const totalGross = await rowValue(page, 'Total Gross');
  const redemptionDiscount = Math.round((totalNet + totalTax - totalGross) * 100);
  expect(redemptionDiscount).toBe(500);

  // Reward Mug earns 30 credit points per unit (perProduct earning mode) - so this order should
  // leave shopper1 with 100 - 50 (spent) + 30 (earned) = 80 points. The balance is only ever
  // rendered on the checkout review step, so get back there via a second, unrelated purchase.
  await emptyBasket(page);
  await page.goto('/product/blue-cotton-shirt');
  await page.getByRole('button', { name: 'Add to Basket' }).click();
  await goThroughCheckoutToReview(page);

  await expect(page.getByText('You have 80 credit points available.')).toBeVisible();
});
