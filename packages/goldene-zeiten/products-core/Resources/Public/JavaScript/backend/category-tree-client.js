/*
 * This file is part of the TYPO3 CMS extension "products_core".
 *
 * It is free software; you can redistribute it and/or modify it under the terms
 * of the GNU General Public License, either version 2 of the License, or any
 * later version.
 *
 * Generated from Build/Sources/TypeScript - do not edit directly.
 */
const TABLES = {
    category: 'tx_products_domain_model_category',
    product: 'tx_products_domain_model_product',
    article: 'tx_products_domain_model_article',
};
function typo3() {
    return window.top.TYPO3;
}
function ajaxUrl(routeIdentifier) {
    return typo3().settings.ajaxUrls[routeIdentifier];
}
export function tableForType(type) {
    return TABLES[type];
}
export class CategoryTreeClient {
    async fetchConfiguration() {
        return this.getJson(new URL(ajaxUrl('products_category_tree_configuration'), window.location.href), { storageFolderPid: 0 });
    }
    async fetchNodes(parentIdentifier) {
        const url = new URL(ajaxUrl('products_category_tree_data'), window.location.href);
        url.searchParams.set('parent', parentIdentifier);
        return this.getJson(url, []);
    }
    async filter(query) {
        const url = new URL(ajaxUrl('products_category_tree_filter'), window.location.href);
        url.searchParams.set('query', query);
        return this.getJson(url, []);
    }
    async fetchRootline(identifier) {
        const url = new URL(ajaxUrl('products_category_tree_rootline'), window.location.href);
        url.searchParams.set('identifier', identifier);
        return this.getJson(url, []);
    }
    async reorder(identifier, beforeIdentifier) {
        const body = new URLSearchParams();
        body.set('identifier', identifier);
        body.set('beforeIdentifier', beforeIdentifier ?? '');
        const response = await fetch(ajaxUrl('products_category_tree_reorder'), { method: 'POST', body });
        return response.ok;
    }
    async createCategory(title, parentCategoryUid, storageFolderPid) {
        const newId = `NEW${Math.floor(Math.random() * 1e9).toString(16)}`;
        const body = new URLSearchParams();
        body.set(`data[${TABLES.category}][${newId}][pid]`, String(storageFolderPid));
        body.set(`data[${TABLES.category}][${newId}][title]`, title);
        body.set(`data[${TABLES.category}][${newId}][parent_category]`, String(parentCategoryUid));
        return this.submitDataHandler(body);
    }
    async createProduct(title, categoryUid, storageFolderPid) {
        const newId = `NEW${Math.floor(Math.random() * 1e9).toString(16)}`;
        const body = new URLSearchParams();
        body.set(`data[${TABLES.product}][${newId}][pid]`, String(storageFolderPid));
        body.set(`data[${TABLES.product}][${newId}][title]`, title);
        body.set(`data[${TABLES.product}][${newId}][categories]`, String(categoryUid));
        return this.submitDataHandler(body);
    }
    async reparentCategory(uid, newParentUid) {
        const body = new URLSearchParams();
        body.set(`data[${TABLES.category}][${uid}][parent_category]`, String(newParentUid));
        return this.submitDataHandler(body);
    }
    async assignProductToCategory(uid, categoryUid) {
        const body = new URLSearchParams();
        body.set(`data[${TABLES.product}][${uid}][categories]`, String(categoryUid));
        return this.submitDataHandler(body);
    }
    async submitDataHandler(body) {
        const response = await fetch(ajaxUrl('record_process'), { method: 'POST', body });
        if (!response.ok) {
            return false;
        }
        const result = (await response.json());
        return !result.hasErrors;
    }
    async getJson(url, fallback) {
        const response = await fetch(url);
        return response.ok ? (await response.json()) : fallback;
    }
}
