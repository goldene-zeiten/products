import { LitElement, html, nothing } from 'lit';

/**
 * Minimal category/product/article tree for the "Products" backend module.
 *
 * Deliberately not built on top of TYPO3\CMS\Backend's internal AbstractTree/tree.js
 * class hierarchy (@internal, not a public API and not guaranteed stable across major
 * versions) - this is a small, self-contained component instead: it fetches JSON nodes
 * from the "products_category_tree_data" ajax route, lazily expands them, and renders
 * category/product nodes as plain links so a click performs a normal navigation within
 * the backend content iframe (the same mechanism the core record list itself relies on).
 * Article nodes are informational leaves only; articles are edited from the record list
 * shown for their parent product.
 */
class ProductsCategoryTree extends LitElement {
    static properties = {
        nodes: { state: true },
    };

    constructor() {
        super();
        this.nodes = [];
        this.expanded = new Set();
        this.childNodesByIdentifier = new Map();
    }

    createRenderRoot() {
        return this;
    }

    connectedCallback() {
        super.connectedCallback();
        this.fetchNodes('root').then((nodes) => {
            this.nodes = nodes;
        });
    }

    async fetchNodes(parentIdentifier) {
        const url = new URL(top.TYPO3.settings.ajaxUrls.products_category_tree_data, window.location.href);
        url.searchParams.set('parent', parentIdentifier);
        const response = await fetch(url);
        return response.ok ? response.json() : [];
    }

    async toggleNode(node) {
        if (this.expanded.has(node.identifier)) {
            this.expanded.delete(node.identifier);
        } else {
            this.expanded.add(node.identifier);
            if (!this.childNodesByIdentifier.has(node.identifier)) {
                this.childNodesByIdentifier.set(node.identifier, await this.fetchNodes(node.identifier));
            }
        }
        this.requestUpdate();
    }

    buildNodeUrl(node) {
        const url = new URL(window.location.href);
        url.searchParams.delete('category');
        url.searchParams.delete('product');
        if (node.type === 'category' || node.type === 'product') {
            url.searchParams.set(node.type, String(node.uid));
        }
        return url.toString();
    }

    renderLabel(node) {
        const classes = node.hidden ? 'text-body-secondary fst-italic' : '';
        if (node.type === 'article') {
            return html`<span class="${classes}">${node.title}</span>`;
        }
        return html`<a class="${classes}" href="${this.buildNodeUrl(node)}">${node.title}</a>`;
    }

    renderToggle(node) {
        if (!node.hasChildren) {
            return html`<span class="d-inline-block" style="width:1.5rem"></span>`;
        }
        const expanded = this.expanded.has(node.identifier);
        const label = top.TYPO3.lang[expanded ? 'tree.collapse' : 'tree.expand'];
        return html`<button
            type="button"
            class="btn btn-borderless btn-sm p-0"
            style="width:1.5rem"
            aria-expanded="${expanded}"
            aria-label="${label}"
            title="${label}"
            @click="${() => this.toggleNode(node)}"
        >${expanded ? '−' : '+'}</button>`;
    }

    renderNode(node) {
        const expanded = this.expanded.has(node.identifier);
        const childNodes = this.childNodesByIdentifier.get(node.identifier) ?? [];
        return html`
            <li>
                <div class="d-flex align-items-center gap-1">${this.renderToggle(node)}${this.renderLabel(node)}</div>
                ${expanded && childNodes.length > 0 ? html`<ul class="list-unstyled ps-4">${childNodes.map((child) => this.renderNode(child))}</ul>` : nothing}
            </li>
        `;
    }

    render() {
        return html`<ul class="list-unstyled ps-0">${this.nodes.map((node) => this.renderNode(node))}</ul>`;
    }
}

customElements.define('goldene-zeiten-products-category-tree', ProductsCategoryTree);

export { ProductsCategoryTree };
