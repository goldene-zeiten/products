import { test, expect } from '@playwright/test';

test('digital content requires the express waiver at review and then loses the withdrawal right (Art. 16(m))', async ({
  page,
}) => {
  await page.goto('/product/everlasting-digital-download');
  await page.getByRole('button', { name: 'Add to Basket' }).click();

  await page.goto('/checkout');
  await page.locator('input[name="tx_productscore_checkout[address][email]"]').fill('download-buyer@example.com');
  await page.locator('input[name="tx_productscore_checkout[address][firstName]"]').fill('Dana');
  await page.locator('input[name="tx_productscore_checkout[address][lastName]"]').fill('Download');
  await page.locator('input[name="tx_productscore_checkout[address][street]"]').fill('Cloud Street 9');
  await page.locator('input[name="tx_productscore_checkout[address][zip]"]').fill('50667');
  await page.locator('input[name="tx_productscore_checkout[address][city]"]').fill('Cologne');
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_productscore_checkout[shippingOption]"]').first().check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();

  await page.locator('input[name="tx_productscore_checkout[paymentMethod]"][value="invoice"]').check();
  await page.getByRole('button', { name: 'Continue to review' }).click();

  // The express consent + acknowledgment is a separate, mandatory checkbox
  const waiver = page.locator('#withdrawalWaiverAccepted');
  await expect(waiver).toBeVisible();
  await expect(waiver).toHaveAttribute('required', /.*/);

  await page.locator('#termsAccepted').check();
  await waiver.check();
  await page.getByRole('button', { name: 'Order with obligation to pay' }).click();
  await expect(page.getByText('Your order number is', { exact: false })).toBeVisible();

  // With the waiver given, the withdrawal right is gone: no function is offered
  await expect(page.getByRole('link', { name: 'Withdraw from contract here' })).toHaveCount(0);
  await expect(
    page.getByText('All items of this order are exempt from the statutory right of withdrawal', { exact: false }),
  ).toBeVisible();
});
