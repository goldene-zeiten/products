/**
 * Apple Pay Express Checkout (raw Apple Pay JS `ApplePaySession`)
 *
 * Renders the Apple Pay button (Safari/WebKit only) for the live basket the button plugin rendered, and
 * drives the sheet against the shop's server:
 *
 *  - `onvalidatemerchant` posts Apple's validation URL to the shop, which validates the merchant session
 *    through the processor (the browser cannot - it needs the Apple Pay merchant certificate);
 *  - `onshippingcontactselected` / `onshippingmethodselected` are answered from the core shipping-quote
 *    endpoint (proven by the signed basket token), keeping the sheet total and methods in step;
 *  - `onpaymentauthorized` posts the encrypted token and the buyer's address to the confirm endpoint, which
 *    authorizes the token and creates the paid order, then sends the browser to the thank-you URL.
 *
 * All amounts are integer minor units (cents) on the wire; Apple Pay wants decimal strings, so they are
 * converted at the edge.
 */
export default class ApplePayExpressCheckout {
  constructor(container) {
    this.container = container;
    this.merchantIdentifier = container.getAttribute('data-merchant-identifier');
    this.displayName = container.getAttribute('data-display-name');
    this.countryCode = container.getAttribute('data-country-code');
    this.currency = container.getAttribute('data-currency');
    this.goodsAmount = parseInt(container.getAttribute('data-amount'), 10) || 0;
    this.basketToken = container.getAttribute('data-basket-token');
    this.shippingQuoteUrl = container.getAttribute('data-shipping-quote-url');
    this.validateUrl = container.getAttribute('data-validate-url');
    this.confirmUrl = container.getAttribute('data-confirm-url');
    this.options = [];
    this.selectedShippingOption = '';
    this.totalCents = this.goodsAmount;
    if (this.isAvailable()) {
      this.renderButton();
    }
  }

  isAvailable() {
    return (
      !!window.ApplePaySession && window.ApplePaySession.supportsVersion(3) && window.ApplePaySession.canMakePayments()
    );
  }

  renderButton() {
    const button = document.createElement('button');
    button.className = 'apple-pay-button apple-pay-button-black';
    button.style.cssText =
      '-webkit-appearance: -apple-pay-button; -apple-pay-button-type: buy; width: 100%; min-height: 44px;';
    button.addEventListener('click', () => this.startSession());
    this.container.appendChild(button);
  }

  startSession() {
    const session = new window.ApplePaySession(3, this.paymentRequest());
    session.onvalidatemerchant = (event) => this.onValidateMerchant(session, event);
    session.onshippingcontactselected = (event) => this.onShippingContactSelected(session, event);
    session.onshippingmethodselected = (event) => this.onShippingMethodSelected(session, event);
    session.onpaymentauthorized = (event) => this.onPaymentAuthorized(session, event);
    session.begin();
  }

  paymentRequest() {
    return {
      countryCode: this.countryCode,
      currencyCode: this.currency,
      merchantCapabilities: ['supports3DS'],
      supportedNetworks: ['visa', 'masterCard', 'amex', 'discover'],
      requiredShippingContactFields: ['postalAddress', 'name', 'email'],
      requiredBillingContactFields: ['postalAddress'],
      total: this.total(this.goodsAmount),
    };
  }

  async onValidateMerchant(session, event) {
    const merchantSession = await this.postJson(this.validateUrl, { validationURL: event.validationURL });
    if (merchantSession && !merchantSession.error) {
      session.completeMerchantValidation(merchantSession);
    } else {
      session.abort();
    }
  }

  async onShippingContactSelected(session, event) {
    await this.fetchQuote(event.shippingContact);
    if (this.options.length === 0) {
      session.completeShippingContactSelection({
        newTotal: this.total(this.totalCents),
        newShippingMethods: [],
        errors: [this.unserviceableError()],
      });
      return;
    }
    this.select(this.options[0]);
    session.completeShippingContactSelection({
      newShippingMethods: this.options.map((option) => this.toShippingMethod(option)),
      newTotal: this.total(this.totalCents),
    });
  }

  onShippingMethodSelected(session, event) {
    const option = this.options.find((candidate) => candidate.key === event.shippingMethod.identifier);
    if (option) {
      this.select(option);
    }
    session.completeShippingMethodSelection({ newTotal: this.total(this.totalCents) });
  }

  async onPaymentAuthorized(session, event) {
    const payload = await this.postJson(this.confirmUrl, {
      token: event.payment.token,
      shippingOption: this.selectedShippingOption,
      address: this.address(event.payment.shippingContact || {}),
    });
    const approved = payload && payload.redirectUrl;
    session.completePayment(approved ? window.ApplePaySession.STATUS_SUCCESS : window.ApplePaySession.STATUS_FAILURE);
    if (approved) {
      window.location.assign(payload.redirectUrl);
    }
  }

  select(option) {
    this.selectedShippingOption = option.key;
    this.totalCents = option.orderTotal;
  }

  async fetchQuote(contact) {
    const quote = await this.postJson(this.shippingQuoteUrl, {
      basketToken: this.basketToken,
      country: contact.countryCode || '',
      postalCode: contact.postalCode || '',
      state: contact.administrativeArea || '',
    });
    this.options = quote && Array.isArray(quote.options) ? quote.options : [];
    return quote;
  }

  toShippingMethod(option) {
    return {
      label: option.label,
      detail: option.deliveryEstimate || '',
      amount: this.decimal(option.shippingAmount),
      identifier: option.key,
    };
  }

  total(cents) {
    return { label: this.displayName, amount: this.decimal(cents) };
  }

  unserviceableError() {
    return new window.ApplePayError('shippingContactInvalid', 'postalCode', 'We cannot ship to this address.');
  }

  address(contact) {
    return {
      email: contact.emailAddress || '',
      firstName: contact.givenName || '',
      lastName: contact.familyName || '',
      street: (contact.addressLines || []).filter(Boolean).join(' '),
      postalCode: contact.postalCode || '',
      city: contact.locality || '',
      country: contact.countryCode || '',
      state: contact.administrativeArea || '',
    };
  }

  decimal(cents) {
    return (cents / 100).toFixed(2);
  }

  async postJson(url, body) {
    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
      return await response.json();
    } catch (error) {
      console.error('Apple Pay express request failed:', error);
      return null;
    }
  }
}

document
  .querySelectorAll('[data-apple-pay-express-checkout]')
  .forEach((container) => new ApplePayExpressCheckout(container));
