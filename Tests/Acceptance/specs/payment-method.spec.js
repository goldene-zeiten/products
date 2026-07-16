import { test, expect } from '@playwright/test';
import { hasAnyRedirectPayment, hasPayment } from '../helper/combination.js';
import {
  addProductAndOpenCheckout,
  fillAddressAndReachShipping,
  selectFirstShippingAndReachPayment,
} from '../helper/checkout.js';
import { GATEWAY, resetMockJournal, gatewayReturnUrls } from '../helper/roundtrip.js';

const MOCK = process.env.MOCK_BASE_URL;

test.beforeEach(() => {
  test.skip(!hasAnyRedirectPayment(), 'No redirect payment method configured in this combination.');
});

for (const method of ['paypal', 'stripe', 'klarna', 'amazon']) {
  test(`${method} takes the order through the gateway round-trip to a placed order`, async ({ page, request }) => {
    test.skip(!hasPayment(method), `${method} is not active in this combination.`);

    await addProductAndOpenCheckout(page);
    await fillAddressAndReachShipping(page, { email: `${method}@example.com` });
    await selectFirstShippingAndReachPayment(page);

    const radio = page.locator(`input[name="tx_productscore_checkout[paymentMethod]"][value="${method}"]`);
    await expect(radio).toBeVisible();
    await radio.check();
    await page.getByRole('button', { name: 'Continue to review' }).click();
    await page.locator('#termsAccepted').check();

    // Isolate this order's create request, and short-circuit the (unreachable) external gateway URL so
    // the browser does not hang trying to load it; record navigations to assert the redirect happened.
    await resetMockJournal(request, MOCK);
    const navigations = [];
    page.on('request', (navigationRequest) => {
      if (navigationRequest.isNavigationRequest()) {
        navigations.push(navigationRequest.url());
      }
    });
    await page.route(GATEWAY, (route) =>
      route.fulfill({ status: 200, contentType: 'text/plain', body: 'MOCK GATEWAY' }),
    );

    await page.getByRole('button', { name: 'Order with obligation to pay' }).click();
    await page.waitForLoadState('networkidle').catch(() => {});

    // The shop created the order and redirected the customer to the gateway to approve.
    const gateway = navigations.find((url) => GATEWAY.test(url));
    expect(
      gateway,
      `Expected a redirect to the ${method} gateway. Navigations seen: ${JSON.stringify(navigations)}. Final URL: ${page.url()}`,
    ).toBeTruthy();

    // Come back from the gateway the way it would return the customer; the shop captures and finalizes.
    // A two-hop gateway (Amazon Pay) returns twice - the review leg bounces back to the gateway (caught by
    // the route above), the result leg lands on the confirmation page.
    const returnUrls = await gatewayReturnUrls(request, MOCK, method);
    for (const returnUrl of returnUrls) {
      await page.goto(returnUrl);
    }

    await expect(page.getByText('Your order number is', { exact: false })).toBeVisible();
  });
}
