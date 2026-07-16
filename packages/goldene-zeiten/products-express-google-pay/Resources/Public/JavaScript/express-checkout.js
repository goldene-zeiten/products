/**
 * Google Pay Express Checkout (Google Pay API for Web)
 *
 * Renders the Google Pay button for the live basket the button plugin rendered, and drives the sheet
 * against the shop's server:
 *
 *  - `onPaymentDataChanged` (the SHIPPING_ADDRESS / SHIPPING_OPTION callback) is answered from the core
 *    shipping-quote endpoint, proven by the signed basket token, so the sheet shows the shop's own carriers
 *    and total;
 *  - on load, the Google Pay token and the buyer's address are posted to the confirm endpoint, which
 *    authorizes the token through the shop's processor and creates the paid order, then sends the browser
 *    to the thank-you URL.
 *
 * The Google Pay JS SDK is loaded on demand. All amounts are integer minor units (cents) on the wire;
 * Google Pay wants decimal strings, so they are converted at the edge.
 */
export default class GooglePayExpressCheckout {
  constructor(container) {
    this.container = container;
    this.environment = container.getAttribute('data-environment') === 'PRODUCTION' ? 'PRODUCTION' : 'TEST';
    this.merchantId = container.getAttribute('data-merchant-id');
    this.merchantName = container.getAttribute('data-merchant-name');
    this.gateway = container.getAttribute('data-gateway');
    this.gatewayMerchantId = container.getAttribute('data-gateway-merchant-id');
    this.countryCode = container.getAttribute('data-country-code');
    this.currency = container.getAttribute('data-currency');
    this.goodsAmount = parseInt(container.getAttribute('data-amount'), 10) || 0;
    this.basketToken = container.getAttribute('data-basket-token');
    this.shippingQuoteUrl = container.getAttribute('data-shipping-quote-url');
    this.confirmUrl = container.getAttribute('data-confirm-url');
    this.options = [];
    this.selectedShippingOption = '';
    this.loadSdk()
      .then(() => this.init())
      .catch((error) => console.error('Google Pay SDK failed to load:', error));
  }

  loadSdk() {
    return new Promise((resolve, reject) => {
      if (window.google && window.google.payments) {
        resolve();
        return;
      }
      const script = document.createElement('script');
      script.src = 'https://pay.google.com/gp/p/js/pay.js';
      script.onload = resolve;
      script.onerror = reject;
      document.head.appendChild(script);
    });
  }

  async init() {
    this.client = new window.google.payments.api.PaymentsClient({
      environment: this.environment,
      paymentDataCallbacks: { onPaymentDataChanged: (data) => this.onPaymentDataChanged(data) },
    });
    const ready = await this.client.isReadyToPay(this.readyRequest());
    if (ready.result) {
      this.renderButton();
    }
  }

  renderButton() {
    this.container.appendChild(
      this.client.createButton({
        onClick: () => this.onClick(),
        buttonType: 'buy',
        buttonSizeMode: 'fill',
      }),
    );
  }

  async onClick() {
    try {
      const paymentData = await this.client.loadPaymentData(this.paymentDataRequest());
      await this.confirm(paymentData);
    } catch (error) {
      console.error('Google Pay was cancelled or failed:', error);
    }
  }

  async onPaymentDataChanged(intermediate) {
    if (intermediate.callbackTrigger === 'SHIPPING_OPTION') {
      this.selectedShippingOption = intermediate.shippingOptionData.id;
      return { newTransactionInfo: this.transactionInfo(this.selectedTotal()) };
    }
    const options = await this.fetchQuote(intermediate.shippingAddress || {});
    if (options.length === 0) {
      return intermediate.callbackTrigger === 'INITIALIZE'
        ? { newTransactionInfo: this.transactionInfo(this.goodsAmount) }
        : {
            error: {
              reason: 'SHIPPING_ADDRESS_UNSERVICEABLE',
              message: 'We cannot ship to this address.',
              intent: 'SHIPPING_ADDRESS',
            },
          };
    }
    this.selectedShippingOption = options[0].key;
    return {
      newShippingOptionParameters: {
        defaultSelectedOptionId: options[0].key,
        shippingOptions: options.map((option) => ({
          id: option.key,
          label: option.label,
          description: option.deliveryEstimate || '',
        })),
      },
      newTransactionInfo: this.transactionInfo(options[0].orderTotal),
    };
  }

  async confirm(paymentData) {
    const payload = await this.postJson(this.confirmUrl, {
      token: paymentData.paymentMethodData.tokenizationData.token,
      shippingOption: this.selectedShippingOption,
      address: this.address(paymentData),
    });
    if (payload && payload.redirectUrl) {
      window.location.assign(payload.redirectUrl);
    }
  }

  cardPaymentMethod() {
    return {
      type: 'CARD',
      parameters: {
        allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS'],
        allowedCardNetworks: ['AMEX', 'MASTERCARD', 'VISA'],
      },
      tokenizationSpecification: {
        type: 'PAYMENT_GATEWAY',
        parameters: { gateway: this.gateway, gatewayMerchantId: this.gatewayMerchantId },
      },
    };
  }

  readyRequest() {
    return {
      apiVersion: 2,
      apiVersionMinor: 0,
      allowedPaymentMethods: [this.cardPaymentMethod()],
    };
  }

  paymentDataRequest() {
    return {
      apiVersion: 2,
      apiVersionMinor: 0,
      allowedPaymentMethods: [this.cardPaymentMethod()],
      merchantInfo: { merchantId: this.merchantId, merchantName: this.merchantName },
      transactionInfo: this.transactionInfo(this.goodsAmount),
      emailRequired: true,
      shippingAddressRequired: true,
      shippingOptionRequired: true,
      callbackIntents: ['SHIPPING_ADDRESS', 'SHIPPING_OPTION'],
    };
  }

  transactionInfo(totalCents) {
    return {
      countryCode: this.countryCode,
      currencyCode: this.currency,
      totalPriceStatus: 'FINAL',
      totalPrice: (totalCents / 100).toFixed(2),
    };
  }

  selectedTotal() {
    const option = this.options.find((candidate) => candidate.key === this.selectedShippingOption);
    return option ? option.orderTotal : this.goodsAmount;
  }

  async fetchQuote(address) {
    const quote = await this.postJson(this.shippingQuoteUrl, {
      basketToken: this.basketToken,
      country: address.countryCode || '',
      postalCode: address.postalCode || '',
      state: address.administrativeArea || '',
    });
    this.options = quote && Array.isArray(quote.options) ? quote.options : [];
    return this.options;
  }

  address(paymentData) {
    const shipping = paymentData.shippingAddress || {};
    const name = (shipping.name || '').trim().split(' ');
    return {
      email: paymentData.email || '',
      firstName: name.shift() || '',
      lastName: name.join(' '),
      street: [shipping.address1, shipping.address2, shipping.address3].filter(Boolean).join(' '),
      postalCode: shipping.postalCode || '',
      city: shipping.locality || '',
      country: shipping.countryCode || '',
      state: shipping.administrativeArea || '',
    };
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
      console.error('Google Pay express request failed:', error);
      return null;
    }
  }
}

document
  .querySelectorAll('[data-google-pay-express-checkout]')
  .forEach((container) => new GooglePayExpressCheckout(container));
