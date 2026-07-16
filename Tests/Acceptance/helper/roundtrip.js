// Full payment round-trip against the WireMock mock. The gateway approval page is external and cannot
// be driven, so instead we reconstruct the URL the gateway would send the customer back to - from the
// create request the shop actually made to the mock (read back via WireMock's request journal) - and
// drive that, so the shop's return handler captures the payment and finalizes the order.

export const GATEWAY = /checkout\.stripe\.com|sandbox\.paypal\.com|pay\.playground\.klarna\.com/;

// Per gateway: the create endpoint the return URL was sent to, and how to turn the logged create request
// body into the return URL the shop's handleReturn() expects (with the mock's fixed ids substituted for
// the placeholders the real gateway would fill in).
const CREATE = {
  paypal: {
    path: '/payment/paypal/v2/checkout/orders',
    returnUrl: (body) => {
      const url = JSON.parse(body).payment_source.paypal.experience_context.return_url;
      return url + (url.includes('?') ? '&' : '?') + 'token=PAYPAL-ORDER-1';
    },
  },
  stripe: {
    path: '/payment/stripe/v1/checkout/sessions',
    returnUrl: (body) => new URLSearchParams(body).get('success_url').replace('{CHECKOUT_SESSION_ID}', 'cs_test_1'),
  },
  klarna: {
    path: '/payment/klarna/hpp/v1/sessions',
    returnUrl: (body) => JSON.parse(body).merchant_urls.success.replace('{{authorization_token}}', 'auth_token_1'),
  },
};

export async function resetMockJournal(request, mockBaseUrl) {
  await request.delete(`${mockBaseUrl}/__admin/requests`);
}

// The URL the gateway would redirect the customer back to, reconstructed from the shop's create request.
export async function gatewayReturnUrl(request, mockBaseUrl, method) {
  const create = CREATE[method];
  const response = await request.post(`${mockBaseUrl}/__admin/requests/find`, {
    data: { method: 'POST', urlPath: create.path },
  });
  const { requests } = await response.json();
  const body = requests[requests.length - 1].body;
  return create.returnUrl(body);
}
