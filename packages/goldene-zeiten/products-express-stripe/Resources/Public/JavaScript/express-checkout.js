/**
 * Stripe Express Checkout Element
 *
 * Mounts Stripe's Express Checkout Element (Apple Pay / Google Pay / PayPal / Amazon Pay / Link) for the
 * live basket the button plugin rendered, and drives the wallet round-trip against the shop's own server:
 *
 *  - the wallet's live shipping-rate callbacks (`shippingaddresschange` / `shippingratechange`) are answered
 *    from the core express shipping-quote endpoint, proven by the signed basket token - so the options and
 *    costs the sheet shows are the shop's, never the client's;
 *  - on `confirm`, a PaymentMethod is created from the wallet and posted to the confirm endpoint, which
 *    settles the PaymentIntent and creates the order server-side, then answers with the thank-you URL the
 *    browser is sent to.
 *
 * All amounts are integer minor units (cents); the element's own `amount` is kept in step with the chosen
 * shipping via `elements.update()` so the sheet total matches what the server will charge.
 */
export default class StripeExpressCheckout {
  constructor(container) {
    this.container = container;
    this.publishableKey = container.getAttribute('data-publishable-key');
    this.goodsAmount = parseInt(container.getAttribute('data-amount'), 10) || 0;
    this.currency = container.getAttribute('data-currency');
    this.basketToken = container.getAttribute('data-basket-token');
    this.shippingQuoteUrl = container.getAttribute('data-shipping-quote-url');
    this.confirmUrl = container.getAttribute('data-confirm-url');
    this.quoteOptions = [];
    this.selectedShippingOption = '';
    this.whenStripeReady(() => this.mount());
  }

  whenStripeReady(callback) {
    if (window.Stripe && this.publishableKey) {
      callback();
      return;
    }
    let attempts = 0;
    const timer = setInterval(() => {
      if (window.Stripe && this.publishableKey) {
        clearInterval(timer);
        callback();
      } else if (++attempts > 50) {
        clearInterval(timer);
      }
    }, 100);
  }

  mount() {
    this.stripe = window.Stripe(this.publishableKey);
    this.elements = this.stripe.elements({ mode: 'payment', amount: this.goodsAmount, currency: this.currency });
    const element = this.elements.create('expressCheckout');
    element.on('click', (event) => this.onClick(event));
    element.on('shippingaddresschange', (event) => this.onShippingAddressChange(event));
    element.on('shippingratechange', (event) => this.onShippingRateChange(event));
    element.on('confirm', (event) => this.onConfirm(event));
    element.mount(this.container);
  }

  onClick(event) {
    event.resolve({
      emailRequired: true,
      shippingAddressRequired: true,
      shippingRates: [{ id: 'pending', displayName: '…', amount: 0 }],
      lineItems: this.lineItems(0),
    });
  }

  async onShippingAddressChange(event) {
    const quote = await this.fetchQuote(event.address);
    if (!quote || this.quoteOptions.length === 0) {
      event.reject();
      return;
    }
    const chosen = this.quoteOptions[0];
    this.selectedShippingOption = chosen.key;
    this.elements.update({ amount: this.goodsAmount + chosen.shippingAmount });
    event.resolve({
      shippingRates: this.quoteOptions.map((option) => this.toShippingRate(option)),
      lineItems: this.lineItems(chosen.shippingAmount),
    });
  }

  onShippingRateChange(event) {
    const option = this.quoteOptions.find((candidate) => candidate.key === event.shippingRate.id);
    if (!option) {
      event.reject();
      return;
    }
    this.selectedShippingOption = option.key;
    this.elements.update({ amount: this.goodsAmount + option.shippingAmount });
    event.resolve({ lineItems: this.lineItems(option.shippingAmount) });
  }

  async onConfirm(event) {
    const { error: submitError } = await this.elements.submit();
    if (submitError) {
      return;
    }
    const { paymentMethod, error } = await this.stripe.createPaymentMethod({ elements: this.elements });
    if (error || !paymentMethod) {
      return;
    }
    try {
      const response = await fetch(this.confirmUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: this.confirmBody(paymentMethod.id, event),
      });
      const payload = await response.json();
      if (response.ok && payload.redirectUrl) {
        window.location.assign(payload.redirectUrl);
      }
    } catch (fetchError) {
      console.error('Stripe express confirm failed:', fetchError);
    }
  }

  async fetchQuote(address) {
    try {
      const response = await fetch(this.shippingQuoteUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          basketToken: this.basketToken,
          country: address.country || '',
          postalCode: address.postal_code || '',
          state: address.state || '',
        }),
      });
      if (!response.ok) {
        return null;
      }
      const quote = await response.json();
      this.quoteOptions = Array.isArray(quote.options) ? quote.options : [];
      return quote;
    } catch (fetchError) {
      console.error('Stripe express shipping quote failed:', fetchError);
      return null;
    }
  }

  toShippingRate(option) {
    return { id: option.key, displayName: option.label, amount: option.shippingAmount };
  }

  lineItems(shippingAmount) {
    return [
      { name: 'Subtotal', amount: this.goodsAmount },
      { name: 'Shipping', amount: shippingAmount },
    ];
  }

  confirmBody(paymentMethodId, event) {
    const shippingAddress = event.shippingAddress || {};
    const address = shippingAddress.address || {};
    const billingDetails = event.billingDetails || {};
    const nameParts = (shippingAddress.name || billingDetails.name || '').trim().split(' ');
    return new URLSearchParams({
      paymentMethodId,
      shippingOption: this.selectedShippingOption,
      email: billingDetails.email || '',
      firstName: nameParts.shift() || '',
      lastName: nameParts.join(' '),
      street: [address.line1, address.line2].filter(Boolean).join(' '),
      postalCode: address.postal_code || '',
      city: address.city || '',
      country: address.country || '',
      state: address.state || '',
    });
  }
}

document
  .querySelectorAll('[data-stripe-express-checkout]')
  .forEach((container) => new StripeExpressCheckout(container));
