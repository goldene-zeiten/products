// Shared checkout navigation for the payment/shipping combination specs. The demo checkout has three
// steps that all submit with a "Continue to payment" button (address -> shipping -> payment), then a
// review page with the final "Order with obligation to pay".

export async function addProductAndOpenCheckout(page, slug = 'soundmax-headphones') {
  await page.goto('/product/' + slug);
  await page.getByRole('button', { name: 'Add to Basket' }).click();
  await page.goto('/checkout');
}

// Fills the address step and advances to the shipping step.
export async function fillAddressAndReachShipping(page, { email = 'combo@example.com', zip = '53113', city = 'Bonn' } = {}) {
  await page.locator('input[name="tx_productscore_checkout[address][email]"]').fill(email);
  await page.locator('input[name="tx_productscore_checkout[address][firstName]"]').fill('Combo');
  await page.locator('input[name="tx_productscore_checkout[address][lastName]"]').fill('Tester');
  await page.locator('input[name="tx_productscore_checkout[address][street]"]').fill('Test Street 1');
  await page.locator('input[name="tx_productscore_checkout[address][zip]"]').fill(zip);
  await page.locator('input[name="tx_productscore_checkout[address][city]"]').fill(city);
  await page.getByRole('button', { name: 'Continue to payment' }).click();
}

// Picks the first offered shipping option and advances to the payment step.
export async function selectFirstShippingAndReachPayment(page) {
  await page.locator('input[name="tx_productscore_checkout[shippingOption]"]').first().check();
  await page.getByRole('button', { name: 'Continue to payment' }).click();
}
