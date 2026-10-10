/*
 * This file is part of the TYPO3 CMS extension "products_core".
 *
 * It is free software; you can redistribute it and/or modify it under the terms
 * of the GNU General Public License, either version 2 of the License, or any
 * later version.
 *
 * Generated from Build/Sources/TypeScript - do not edit directly.
 */
"use strict";
const CONTAINER_SELECTOR = '[data-product-variant-selector]';
const SELECT_SELECTOR = '.variant-attribute-select, .variant-article-select';
const FRAGMENT_SELECTOR = '[data-product-price-and-stock]';
/**
 * Progressively enhances the variant chooser on a product detail page. Without JavaScript the visible
 * "Update" button posts the form and reloads the page. With JavaScript a changed selection posts it right
 * away: in the default "reload" switch mode as a normal submit, in "ajax" mode in the background, swapping
 * in only the price/stock fragment of the answer and falling back to a normal submit when that fails.
 *
 * The background request posts exactly the form a normal submit would, so it carries the same signed
 * form tokens and needs no cHash.
 */
class ArticleSelector {
    constructor() {
        document.querySelectorAll(CONTAINER_SELECTOR).forEach((container) => this.bind(container));
    }
    bind(container) {
        const form = container.querySelector('form');
        if (form === null) {
            return;
        }
        container.querySelectorAll(SELECT_SELECTOR).forEach((select) => {
            select.addEventListener('change', () => {
                if (container.dataset.switchMode === 'ajax') {
                    void this.swapFragment(form);
                }
                else {
                    form.submit();
                }
            });
        });
    }
    async swapFragment(form) {
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
            });
            if (!response.ok) {
                throw new Error(`Variant switch answered with status ${response.status}`);
            }
            const answer = new DOMParser().parseFromString(await response.text(), 'text/html');
            const fragment = answer.querySelector(FRAGMENT_SELECTOR);
            const target = document.querySelector(FRAGMENT_SELECTOR);
            if (fragment === null || target === null) {
                throw new Error('Price/stock fragment not found');
            }
            target.innerHTML = fragment.innerHTML;
            target.dispatchEvent(new CustomEvent('products:article-updated', { bubbles: true }));
        }
        catch (error) {
            console.error('Switching the variant in place failed, reloading instead.', error);
            form.submit();
        }
    }
}
new ArticleSelector();
