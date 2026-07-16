// Which payment methods and shipping carriers the current acceptance combination has installed and
// configured. Driven by the PRODUCTS_COMBINATION env var the runner passes (Build/Scripts/runTests.sh
// acceptance case), which mirrors the PRODUCTS_COMBO that setupInstance.sh built the instance for.
//
// Specs use these helpers to self-select: a spec that drives Stripe skips unless Stripe is active; the
// table-rate delivery spec skips when a live carrier supersedes it; the multi-active spec runs only for
// the "all" combination. This keeps one spec suite valid across every per-combination instance build.

const combination = process.env.PRODUCTS_COMBINATION || 'baseline';

const PAYMENTS = {
  baseline: [],
  paypal: ['paypal'],
  stripe: ['stripe'],
  klarna: ['klarna'],
  ups: [],
  dhl: [],
  'dhl-stripe': ['stripe'],
  'ups-paypal': ['paypal'],
  all: ['paypal', 'stripe', 'klarna'],
};

const CARRIERS = {
  baseline: [],
  paypal: [],
  stripe: [],
  klarna: [],
  ups: ['ups'],
  dhl: ['dhl'],
  'dhl-stripe': ['dhl'],
  'ups-paypal': ['ups'],
  all: ['ups', 'dhl'],
};

export const currentCombination = combination;
export const activePayments = PAYMENTS[combination] || [];
export const activeCarriers = CARRIERS[combination] || [];

export const hasPayment = (id) => activePayments.includes(id);
export const hasCarrier = (id) => activeCarriers.includes(id);
export const hasAnyCarrier = () => activeCarriers.length > 0;
export const hasAnyRedirectPayment = () => activePayments.length > 0;
