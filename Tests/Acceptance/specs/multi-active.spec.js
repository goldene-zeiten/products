import { test, expect } from '@playwright/test';
import { currentCombination } from '../helper/combination.js';
import {
  addProductAndOpenCheckout,
  fillAddressAndReachShipping,
  selectFirstShippingAndReachPayment,
} from '../helper/checkout.js';

// Side effects of having several payment methods and both carriers active at once - only the "all"
// combination installs everything together.
test.beforeEach(() => {
  test.skip(currentCombination !== 'all', 'Multi-active side effects are only tested in the "all" combination.');
});

test('every configured payment method is offered together with invoice', async ({ page }) => {
  await addProductAndOpenCheckout(page);
  await fillAddressAndReachShipping(page, { email: 'multi@example.com' });
  await selectFirstShippingAndReachPayment(page);

  for (const value of ['invoice', 'paypal', 'stripe', 'klarna']) {
    await expect(page.locator(`input[name="tx_productscore_checkout[paymentMethod]"][value="${value}"]`)).toBeVisible();
  }
});

test('both live carriers quote at the shipping step without suppressing each other', async ({ page }) => {
  await addProductAndOpenCheckout(page);
  await fillAddressAndReachShipping(page, { email: 'multiship@example.com' });

  await expect(page.getByText('EXPRESS WORLDWIDE', { exact: false }).first()).toBeVisible();
  await expect(page.getByText('UPS', { exact: false }).first()).toBeVisible();
});
