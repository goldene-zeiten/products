/*
 * This file is part of the TYPO3 CMS extension "products_basket_summary".
 *
 * It is free software; you can redistribute it and/or modify it under the terms
 * of the GNU General Public License, either version 2 of the License, or any
 * later version.
 *
 * Generated from Build/Sources/TypeScript - do not edit directly.
 */
"use strict";
/**
 * <products-basket-summary endpoint="/products/basket-summary"> keeps the basket badge inside it up to date
 * on cached pages: it asks the endpoint for the visitor's item count and total, writes the count into the
 * [data-basket-count] element (hidden while the basket is empty) and the formatted total into
 * [data-basket-total] and the link title. A "products:basket-updated" event on the window refreshes it.
 */
class BasketSummaryElement extends HTMLElement {
    constructor() {
        super(...arguments);
        this.refresh = () => {
            void this.load();
        };
    }
    connectedCallback() {
        window.addEventListener('products:basket-updated', this.refresh);
        void this.load();
    }
    disconnectedCallback() {
        window.removeEventListener('products:basket-updated', this.refresh);
    }
    async load() {
        const endpoint = this.getAttribute('endpoint');
        if (endpoint === null || endpoint === '') {
            return;
        }
        try {
            const response = await fetch(endpoint, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (!response.ok) {
                return;
            }
            this.render((await response.json()));
        }
        catch {
            // The server-rendered badge stays as it is.
        }
    }
    render(summary) {
        const count = this.querySelector('[data-basket-count]');
        if (count !== null) {
            count.textContent = String(summary.count);
            count.hidden = summary.count === 0;
        }
        const total = this.formatTotal(summary);
        const totalElement = this.querySelector('[data-basket-total]');
        if (totalElement !== null) {
            totalElement.textContent = total;
        }
        this.querySelector('a')?.setAttribute('title', total);
    }
    formatTotal(summary) {
        const locale = document.documentElement.lang !== '' ? document.documentElement.lang : undefined;
        try {
            return new Intl.NumberFormat(locale, { style: 'currency', currency: summary.currency }).format(Number(summary.total));
        }
        catch {
            return `${summary.total} ${summary.currency}`;
        }
    }
}
if (customElements.get('products-basket-summary') === undefined) {
    customElements.define('products-basket-summary', BasketSummaryElement);
}
