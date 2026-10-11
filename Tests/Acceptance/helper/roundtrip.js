// Full payment round-trip against the WireMock mock. The gateway approval page is external and cannot
// be driven, so instead we reconstruct the URL(s) the gateway would send the customer back to - from the
// create request the shop actually made to the mock (read back via WireMock's request journal) - and
// drive them, so the shop's return handler captures the payment and finalizes the order.
//
// The shop binds what the gateway reports back to the order it is settling (order reference, amount,
// currency), so the static stubs of the shared mock - built for the functional tests' fixed orders - would
// rightly be refused. For each round-trip we therefore register stubs for a fresh gateway id that echo
// this order's own reference, amount and currency from its create request, the way the real gateway
// would, and remove them afterwards.
//
// Most gateways return the customer once; Amazon Pay returns twice (a review leg that bounces back to
// Amazon, then a result leg that completes), so a method yields an ordered list of return URLs to visit.

export const GATEWAY = /checkout\.stripe\.com|sandbox\.paypal\.com|pay\.playground\.klarna\.com|pay\.amazon\./;

const json = (body) => ({
  status: 200,
  headers: { 'Content-Type': 'application/json' },
  jsonBody: body,
});

const stub = (method, urlPath, response) => ({ priority: 1, request: { method, urlPath }, response });

// Per gateway: the create endpoint the return URL was sent to, the stubs answering the calls the return
// handler makes for this order, and the return URL(s) carrying the fresh gateway id.
const CREATE = {
  paypal: {
    path: '/payment/paypal/v2/checkout/orders',
    roundTrip: (body, id) => {
      const created = JSON.parse(body);
      const unit = created.purchase_units[0];
      const paypalOrderId = `PAYPAL-ORDER-${id}`;
      const url = created.payment_source.paypal.experience_context.return_url;
      const captured = {
        id: paypalOrderId,
        status: 'COMPLETED',
        purchase_units: [
          {
            reference_id: unit.reference_id,
            custom_id: unit.custom_id,
            payments: {
              captures: [{ id: `CAPTURE-${id}`, status: 'COMPLETED', custom_id: unit.custom_id, amount: unit.amount }],
            },
          },
        ],
      };
      return {
        stubs: [
          stub('POST', `/payment/paypal/v2/checkout/orders/${paypalOrderId}/capture`, {
            ...json(captured),
            status: 201,
          }),
        ],
        returnUrls: [url + (url.includes('?') ? '&' : '?') + `token=${paypalOrderId}`],
      };
    },
  },
  stripe: {
    path: '/payment/stripe/v1/checkout/sessions',
    roundTrip: (body, id) => {
      const created = new URLSearchParams(body);
      const sessionId = `cs_test_${id}`;
      const session = {
        id: sessionId,
        object: 'checkout.session',
        status: 'complete',
        payment_status: 'paid',
        payment_intent: `pi_test_${id}`,
        client_reference_id: created.get('client_reference_id'),
        amount_total: Number(created.get('line_items[0][price_data][unit_amount]')),
        currency: created.get('line_items[0][price_data][currency]'),
        url: null,
      };
      return {
        stubs: [stub('GET', `/payment/stripe/v1/checkout/sessions/${sessionId}`, json(session))],
        returnUrls: [created.get('success_url').replace('{CHECKOUT_SESSION_ID}', sessionId)],
      };
    },
  },
  klarna: {
    path: '/payment/klarna/hpp/v1/sessions',
    roundTrip: (body) => ({
      stubs: [],
      returnUrls: [JSON.parse(body).merchant_urls.success.replace('{{authorization_token}}', 'auth_token_1')],
    }),
  },
  amazon: {
    path: '/payment/amazon/v2/checkoutSessions',
    roundTrip: (body, id) => {
      const created = JSON.parse(body);
      const sessionId = `amzn_session_${id}`;
      const sessionPath = `/payment/amazon/v2/checkoutSessions/${sessionId}`;
      const session = (state) => ({
        checkoutSessionId: sessionId,
        statusDetails: { state },
        merchantMetadata: created.merchantMetadata,
        paymentDetails: { paymentIntent: 'AuthorizeWithCapture', chargeAmount: created.paymentDetails.chargeAmount },
        webCheckoutDetails: { amazonPayRedirectUrl: `https://pay.amazon.eu/checkout/${sessionId}` },
      });
      const completed = { ...session('Completed'), chargeId: `S01-${id}`, chargePermissionId: `S01-PERMISSION-${id}` };
      const details = created.webCheckoutDetails;
      const withSession = (url) => url + (url.includes('?') ? '&' : '?') + `amazonCheckoutSessionId=${sessionId}`;
      return {
        stubs: [
          stub('GET', sessionPath, json(session('Open'))),
          stub('PATCH', sessionPath, json(session('Open'))),
          stub('POST', `${sessionPath}/complete`, json(completed)),
        ],
        // The review return updates the session and redirects back to Amazon; the result return completes it.
        returnUrls: [withSession(details.checkoutReviewReturnUrl), withSession(details.checkoutResultReturnUrl)],
      };
    },
  },
};

export async function resetMockJournal(request, mockBaseUrl) {
  await request.delete(`${mockBaseUrl}/__admin/requests`);
}

// Registers the gateway stubs for this order and returns the ordered URL(s) the gateway would redirect the
// customer back to, reconstructed from the shop's create request. Visit them in order; the last one lands
// on the shop's order-confirmation page. Pass the returned stub ids to removeGatewayStubs() afterwards.
export async function prepareGatewayReturn(request, mockBaseUrl, method) {
  const create = CREATE[method];
  const response = await request.post(`${mockBaseUrl}/__admin/requests/find`, {
    data: { method: 'POST', urlPath: create.path },
  });
  const { requests } = await response.json();
  const id = `ACC${Date.now()}`;
  const { stubs, returnUrls } = create.roundTrip(requests[requests.length - 1].body, id);
  const stubIds = [];
  for (const mapping of stubs) {
    const created = await request.post(`${mockBaseUrl}/__admin/mappings`, { data: mapping });
    stubIds.push((await created.json()).id);
  }

  return { returnUrls, stubIds };
}

export async function removeGatewayStubs(request, mockBaseUrl, stubIds) {
  for (const stubId of stubIds) {
    await request.delete(`${mockBaseUrl}/__admin/mappings/${stubId}`);
  }
}
