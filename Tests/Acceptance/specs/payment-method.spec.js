import { test, expect } from '@playwright/test';
import { hasAnyRedirectPayment, hasPayment } from '../helper/combination.js';
import {
  addProductAndOpenCheckout,
  fillAddressAndReachShipping,
  selectFirstShippingAndReachPayment,
} from '../helper/checkout.js';

// The external gateway approval pages the shop redirects to. They are fake mock URLs, so the specs
// intercept the navigation rather than actually leaving for the real gateway.
const GATEWAY = /checkout\.stripe\.com|sandbox\.paypal\.com|pay\.playground\.klarna\.com/;

test.beforeEach(() => {
  test.skip(!hasAnyRedirectPayment(), 'No redirect payment method configured in this combination.');
});

for (const method of ['paypal', 'stripe', 'klarna']) {
  test(`the ${method} method is offered and redirects to the gateway on order placement`, async ({ page }) => {
    test.skip(!hasPayment(method), `${method} is not active in this combination.`);

    await addProductAndOpenCheckout(page);
    await fillAddressAndReachShipping(page, { email: `${method}@example.com` });
    await selectFirstShippingAndReachPayment(page);

    const radio = page.locator(`input[name="tx_productscore_checkout[paymentMethod]"][value="${method}"]`);
    await expect(radio).toBeVisible();
    await radio.check();
    await page.getByRole('button', { name: 'Continue to review' }).click();
    await page.locator('#termsAccepted').check();

    // Record every top-level navigation, and short-circuit the (unreachable) gateway URL so the browser
    // does not hang trying to load it.
    const navigations = [];
    page.on('request', (request) => {
      if (request.isNavigationRequest()) {
        navigations.push(request.url());
      }
    });
    await page.route(GATEWAY, (route) =>
      route.fulfill({ status: 200, contentType: 'text/plain', body: 'MOCK GATEWAY' }),
    );

    await page.getByRole('button', { name: 'Order with obligation to pay' }).click();
    await page.waitForLoadState('networkidle').catch(() => {});

    const gateway = navigations.find((url) => GATEWAY.test(url));
    expect(
      gateway,
      `Expected a redirect to the ${method} gateway. Navigations seen: ${JSON.stringify(navigations)}. Final URL: ${page.url()}`,
    ).toBeTruthy();
  });
}
