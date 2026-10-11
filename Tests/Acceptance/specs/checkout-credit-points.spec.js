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

// shopper1's basket is shared session state across specs in the e2e-logged-in project; clear it
// first so leftovers from other specs don't skew this test's total/point arithmetic.
async function emptyBasket(page) {
  await page.goto('/basket');
  let removeLink = page.locator('tbody tr').getByRole('button', { name: 'Remove' }).first();
  while ((await removeLink.count()) > 0) {
    await removeLink.click();
    removeLink = page.locator('tbody tr').getByRole('button', { name: 'Remove' }).first();
  }
}

async function goThroughCheckoutToReview(page) {
  await page.goto('/checkout');
  await page.locator('input[name="tx_productscore_checkout[address][email]"]').fill('shopper1@example.com');
  await page.locator('input[name="tx_productscore_checkout[address][firstName]"]').fill('Sam');
  await page.locator('input[name="tx_productscore_checkout[address][lastName]"]').fill('Shopper');
  await page.locator('input[name="tx_productscore_checkout[address][street]"]').fill('Shop Street 5');
  await page.locator('input[name="tx_productscore_checkout[address][zip]"]').fill('54321');
  await page.locator('input[name="tx_productscore_checkout[address][city]"]').fill('Hamburg');
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_productscore_checkout[shippingOption]"]').first().check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_productscore_checkout[paymentMethod]"][value="invoice"]').check();
  await page.getByRole('button', { name: 'Continue to review' }).click();
}

test('spending credit points reduces the order total, and points earned from the order raise the balance', async ({
  page,
}) => {
  // shopper1 starts with 100 points at 0.10 EUR each; spending 50 = 5.00 EUR off
  await emptyBasket(page);
  await page.goto('/product/reward-mug');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await goThroughCheckoutToReview(page);

  await expect(page.getByText('You have 100 credit points available.')).toBeVisible();

  const totalBeforeSpending = await rowValue(page, 'Total Gross');
  await page.locator('#spendPoints').fill('50');
  await page.getByRole('button', { name: 'Apply credit points' }).click();

  // The review shows the total the order will be placed with, so the selected points are already in it
  expect(Math.round((await rowValue(page, 'Discount')) * 100)).toBe(500);
  expect(Math.round((await rowValue(page, 'Total Gross')) * 100)).toBe(Math.round(totalBeforeSpending * 100) - 500);

  await page.locator('#termsAccepted').check();
  await page.getByRole('button', { name: 'Order with obligation to pay' }).click();

  await expect(page.getByText('Your order number is', { exact: false })).toBeVisible();

  // The redemption is listed as a discount and, being a price reduction, lowers net and VAT with the gross
  expect(Math.round((await rowValue(page, 'Discount')) * 100)).toBe(500);
  const totalGross = await rowValue(page, 'Total Gross');
  const totalNet = await rowValue(page, 'Total Net');
  const vatRows = page.locator('tr.order-summary-vat');
  let totalVat = 0;
  for (let index = 0; index < (await vatRows.count()); index++) {
    const match = (await vatRows.nth(index).textContent()).match(/([\d.,]+)\s*EUR/);
    totalVat += parseFloat(match[1].replace(',', ''));
  }
  expect(Math.round((totalNet + totalVat) * 100)).toBe(Math.round(totalGross * 100));

  // Reward Mug earns 30 points/unit: 100 - 50 (spent) + 30 (earned) = 80
  await emptyBasket(page);
  await page.goto('/product/blue-cotton-shirt');
  await page.getByRole('button', { name: 'Add to Basket' }).click();
  await goThroughCheckoutToReview(page);

  await expect(page.getByText('You have 80 credit points available.')).toBeVisible();
});
