import { test, expect } from '@playwright/test';
import { hasAnyCarrier, hasCarrier } from '../helper/combination.js';
import { addProductAndOpenCheckout, fillAddressAndReachShipping } from '../helper/checkout.js';

// Only runs in a combination that installed and configured a live carrier (against the WireMock mock).
test.beforeEach(() => {
  test.skip(!hasAnyCarrier(), 'No live carrier configured in this combination.');
});

test('a live carrier rate is offered at the shipping step', async ({ page }) => {
  await addProductAndOpenCheckout(page);
  await fillAddressAndReachShipping(page, { email: 'carrier@example.com' });

  if (hasCarrier('dhl')) {
    await expect(page.getByText('EXPRESS WORLDWIDE', { exact: false }).first()).toBeVisible();
  }
  if (hasCarrier('ups')) {
    // Several UPS services are offered, so match the first.
    await expect(page.getByText('UPS', { exact: false }).first()).toBeVisible();
  }
});

test('an unserviceable destination falls back to table-rate shipping', async ({ page }) => {
  // Only meaningful when DHL Express is the sole carrier: with another live carrier (UPS) also active,
  // that carrier fills the gap for the unserviceable lane instead of the table-rate fallback.
  test.skip(!(hasCarrier('dhl') && !hasCarrier('ups')), 'The no-rate fallback needs DHL Express as the only carrier.');

  await addProductAndOpenCheckout(page);
  // The DHL mock returns no rate for postcode 00000, so the built-in table-rate carrier must fill in
  // and the checkout never dead-ends.
  await fillAddressAndReachShipping(page, { email: 'norate@example.com', zip: '00000' });

  await expect(page.getByText('Standard Shipping', { exact: false })).toBeVisible();
});
