import { LitElement, nothing, html } from 'lit';
import '@typo3/backend/element/icon-element.js';

class ProductsTreeToolbar extends LitElement {
    constructor() {
        super();
        this.newCategoryUrl = '';
        this.searchLabel = 'Search...';
        this.newCategoryLabel = 'New category';
    }
    createRenderRoot() {
        return this;
    }
    onInput(event) {
        const query = event.target.value;
        this.dispatchEvent(new CustomEvent('tree-search', { detail: query, bubbles: true, composed: true }));
    }
    render() {
        return html `
      <div class="d-flex gap-2 mb-2">
        <input
          type="search"
          class="form-control form-control-sm"
          placeholder="${this.searchLabel}"
          aria-label="${this.searchLabel}"
          @input="${this.onInput}"
        />
        ${this.newCategoryUrl
            ? html `<a class="btn btn-default btn-sm text-nowrap" href="${this.newCategoryUrl}">${this.newCategoryLabel}</a>`
            : nothing}
      </div>
    `;
    }
}
ProductsTreeToolbar.properties = {
    newCategoryUrl: { attribute: 'new-category-url' },
    searchLabel: { attribute: 'search-label' },
    newCategoryLabel: { attribute: 'new-category-label' },
};
customElements.define('goldene-zeiten-products-tree-toolbar', ProductsTreeToolbar);

const TABLES = {
    category: 'tx_products_domain_model_category',
    product: 'tx_products_domain_model_product',
    article: 'tx_products_domain_model_article',
};
function typo3$1() {
    return window.top.TYPO3;
}
function ajaxUrl(routeIdentifier) {
    return typo3$1().settings.ajaxUrls[routeIdentifier];
}
function tableForType(type) {
    return TABLES[type];
}
/**
 * All reads go through this extension's own AJAX endpoints (CategoryTreeController).
 * Writes reuse TYPO3 core's generic, table-agnostic DataHandler AJAX route
 * ("record_process") wherever DataHandler's own model fits (field updates, delete,
 * copy) - CategoryMountAccessHook enforces mount restrictions there regardless of
 * caller. Sibling reordering goes through a dedicated endpoint instead, since every
 * category/product here shares one flat storage-folder pid and DataHandler's
 * generic cmd[move] can't express "reorder within this parent_category branch".
 */
class CategoryTreeClient {
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
    async setHidden(type, uid, hidden) {
        const body = new URLSearchParams();
        body.set(`data[${tableForType(type)}][${uid}][hidden]`, hidden ? '1' : '0');
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
    async deleteRecord(type, uid) {
        const body = new URLSearchParams();
        body.set(`cmd[${tableForType(type)}][${uid}][delete]`, '1');
        return this.submitDataHandler(body);
    }
    async copyRecord(type, uid, targetPid) {
        const body = new URLSearchParams();
        body.set(`cmd[${tableForType(type)}][${uid}][copy]`, String(targetPid));
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

const EXPANDED_STORAGE_KEY = 'products-category-tree-expanded';
const SELECTED_STORAGE_KEY = 'products-category-tree-selected';
/**
 * Persists expand state (survives browser restarts, like the page tree) and the last
 * selected node (survives a module reload within the same tab), mirroring core's own
 * split between localStorage and sessionStorage for the same two concerns.
 */
class TreeState {
    getExpanded() {
        try {
            const raw = window.localStorage.getItem(EXPANDED_STORAGE_KEY);
            return new Set(raw ? JSON.parse(raw) : []);
        }
        catch {
            return new Set();
        }
    }
    setExpanded(expanded) {
        try {
            window.localStorage.setItem(EXPANDED_STORAGE_KEY, JSON.stringify([...expanded]));
        }
        catch {
            // Storage unavailable (private browsing, quota) - expand state simply won't persist.
        }
    }
    getSelected() {
        try {
            return window.sessionStorage.getItem(SELECTED_STORAGE_KEY);
        }
        catch {
            return null;
        }
    }
    setSelected(identifier) {
        try {
            if (identifier === null) {
                window.sessionStorage.removeItem(SELECTED_STORAGE_KEY);
            }
            else {
                window.sessionStorage.setItem(SELECTED_STORAGE_KEY, identifier);
            }
        }
        catch {
            // Storage unavailable - selection simply won't survive a reload.
        }
    }
}

function typo3() {
    return window.top.TYPO3;
}
function label(key, fallback) {
    return typo3().lang?.[`tree.${key}`] ?? fallback;
}
/**
 * Category/product/article backend tree, modeled after the page tree's behaviour:
 * lazy-loaded nodes, search-and-reveal, drag & drop, inline actions, and expand
 * state that survives a module reload. Deliberately not built on top of
 * TYPO3\CMS\Backend's internal AbstractTree/tree.js hierarchy (@internal, not a
 * public API) - this stays a small, self-contained Lit element instead, reusing
 * only public building blocks (typo3-backend-icon, the DataHandler AJAX route).
 *
 * Articles are read-only leaves here (see .claude/TREE.md: "Articles from
 * products are not part of the tree, yet.") - not draggable, no inline actions.
 */
class ProductsCategoryTree extends LitElement {
    constructor() {
        super();
        this.client = new CategoryTreeClient();
        this.state = new TreeState();
        this.childrenByParent = new Map();
        this.expanded = new Set();
        this.selected = null;
        this.dragPayload = null;
        this.dropTarget = null;
        this.searchTimer = null;
        this.newCategoryUrl = '';
        this.rootNodes = [];
        this.searchMatches = [];
        this.searchActive = false;
    }
    createRenderRoot() {
        return this;
    }
    connectedCallback() {
        super.connectedCallback();
        this.expanded = this.state.getExpanded();
        this.selected = this.state.getSelected();
        void this.initialize();
    }
    async initialize() {
        await this.loadRoot();
        if (this.hasSelectionInUrl()) {
            this.selected = this.currentSelectionIdentifier();
            this.state.setSelected(this.selected);
            await this.revealSelected();
            return;
        }
        if (this.selected) {
            this.navigateTo(this.selected);
        }
    }
    hasSelectionInUrl() {
        const params = new URLSearchParams(window.location.search);
        return params.has('category') || params.has('product');
    }
    currentSelectionIdentifier() {
        const params = new URLSearchParams(window.location.search);
        const category = params.get('category');
        const product = params.get('product');
        if (category) {
            return `category-${category}`;
        }
        if (product) {
            return `product-${product}`;
        }
        return null;
    }
    async revealSelected() {
        if (!this.selected) {
            return;
        }
        const ancestors = await this.client.fetchRootline(this.selected);
        for (const ancestor of ancestors) {
            await this.ensureChildrenLoaded(ancestor);
            this.expanded.add(ancestor);
        }
        this.persistExpanded();
        this.requestUpdate();
    }
    async loadRoot() {
        this.rootNodes = await this.client.fetchNodes('root');
    }
    async ensureChildrenLoaded(identifier, forceRefresh = false) {
        if (!forceRefresh && this.childrenByParent.has(identifier)) {
            return this.childrenByParent.get(identifier);
        }
        const children = await this.client.fetchNodes(identifier);
        this.childrenByParent.set(identifier, children);
        return children;
    }
    async refreshParent(parentIdentifier) {
        if (parentIdentifier === 'root') {
            await this.loadRoot();
        }
        else {
            await this.ensureChildrenLoaded(parentIdentifier, true);
        }
        this.requestUpdate();
    }
    persistExpanded() {
        this.state.setExpanded(this.expanded);
    }
    async toggleNode(node) {
        if (!node.hasChildren) {
            return;
        }
        if (this.expanded.has(node.identifier)) {
            this.expanded.delete(node.identifier);
        }
        else {
            this.expanded.add(node.identifier);
            await this.ensureChildrenLoaded(node.identifier);
        }
        this.persistExpanded();
        this.requestUpdate();
    }
    navigateTo(identifier) {
        const [type, uid] = identifier.split('-');
        const url = new URL(window.location.href);
        url.searchParams.delete('category');
        url.searchParams.delete('product');
        if (type === 'category' || type === 'product') {
            url.searchParams.set(type, uid);
        }
        window.location.href = url.toString();
    }
    selectNode(node) {
        if (node.type === 'article') {
            return;
        }
        this.state.setSelected(node.identifier);
        this.navigateTo(node.identifier);
    }
    onSearchInput(event) {
        const query = event.detail.trim();
        if (this.searchTimer !== null) {
            window.clearTimeout(this.searchTimer);
        }
        this.searchTimer = window.setTimeout(() => void this.runSearch(query), 250);
    }
    async runSearch(query) {
        if (query === '') {
            this.searchActive = false;
            this.searchMatches = [];
            return;
        }
        const matches = await this.client.filter(query);
        for (const match of matches) {
            for (const ancestor of match.ancestors) {
                await this.ensureChildrenLoaded(ancestor);
                this.expanded.add(ancestor);
            }
        }
        this.persistExpanded();
        this.searchMatches = matches;
        this.searchActive = true;
        this.requestUpdate();
    }
    async onToggleHidden(node) {
        const succeeded = await this.client.setHidden(node.type, node.uid, !node.hidden);
        if (succeeded) {
            await this.refreshParent(node.parentIdentifier);
        }
        else {
            this.notifyError();
        }
    }
    async onDelete(node) {
        if (!window.confirm(label('delete_confirm', 'Are you sure you want to delete "%s"?').replace('%s', node.title))) {
            return;
        }
        const succeeded = await this.client.deleteRecord(node.type, node.uid);
        if (succeeded) {
            this.expanded.delete(node.identifier);
            this.childrenByParent.delete(node.identifier);
            await this.refreshParent(node.parentIdentifier);
        }
        else {
            this.notifyError();
        }
    }
    async onCopy(node) {
        const parentUid = this.parentUidForCopy(node);
        const succeeded = await this.client.copyRecord(node.type, node.uid, parentUid);
        if (succeeded) {
            await this.refreshParent(node.parentIdentifier);
        }
        else {
            this.notifyError();
        }
    }
    parentUidForCopy(node) {
        if (node.parentIdentifier === 'root') {
            return 0;
        }
        const [, uid] = node.parentIdentifier.split('-');
        return parseInt(uid, 10);
    }
    notifyError() {
        window.alert(label('error_generic', 'The action could not be completed.'));
    }
    onDragStart(event, node) {
        if (node.type === 'article') {
            event.preventDefault();
            return;
        }
        this.dragPayload = { identifier: node.identifier, type: node.type };
        event.dataTransfer?.setData('text/plain', node.identifier);
        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
        }
    }
    onDragOver(event, target) {
        if (!this.dragPayload || this.dragPayload.identifier === target.identifier) {
            return;
        }
        const position = this.computeDropPosition(event, target);
        if (!this.canDrop(this.dragPayload.type, target.type, position)) {
            return;
        }
        event.preventDefault();
        this.dropTarget = { identifier: target.identifier, position };
        this.requestUpdate();
    }
    onDragLeave() {
        this.dropTarget = null;
        this.requestUpdate();
    }
    computeDropPosition(event, target) {
        const row = event.currentTarget;
        const rect = row.getBoundingClientRect();
        const ratio = (event.clientY - rect.top) / rect.height;
        if (target.type === 'category' && ratio > 0.25 && ratio < 0.75) {
            return 'into';
        }
        return ratio < 0.5 ? 'before' : 'after';
    }
    canDrop(draggedType, targetType, position) {
        if (draggedType === 'article' || targetType === 'article') {
            return false;
        }
        if (draggedType === 'category') {
            return targetType === 'category';
        }
        if (targetType === 'category') {
            return position === 'into';
        }
        return position !== 'into';
    }
    async onDrop(event, target) {
        event.preventDefault();
        const dragged = this.dragPayload;
        const position = this.dropTarget?.position ?? null;
        this.dragPayload = null;
        this.dropTarget = null;
        this.requestUpdate();
        if (!dragged || !position || !this.canDrop(dragged.type, target.type, position)) {
            return;
        }
        await this.applyDrop(dragged, target, position);
    }
    async applyDrop(dragged, target, position) {
        const draggedNode = this.findCachedNode(dragged.identifier);
        const oldParent = draggedNode?.parentIdentifier ?? null;
        const [, draggedUidRaw] = dragged.identifier.split('-');
        const draggedUid = parseInt(draggedUidRaw, 10);
        const succeeded = position === 'into'
            ? await this.applyReparent(dragged.type, draggedUid, target.uid)
            : await this.client.reorder(dragged.identifier, await this.resolveBeforeIdentifier(target, position));
        if (!succeeded) {
            this.notifyError();
            return;
        }
        if (oldParent) {
            await this.refreshParent(oldParent);
        }
        await this.refreshParent(position === 'into' ? target.identifier : target.parentIdentifier);
    }
    /**
     * "Before" places the dragged node right in front of the target. "After" is
     * expressed the same way server-side (there is no afterIdentifier), so it
     * resolves to "before whatever currently follows target" (or the end).
     */
    async resolveBeforeIdentifier(target, position) {
        if (position === 'before') {
            return target.identifier;
        }
        const siblings = await this.ensureChildrenLoaded(target.parentIdentifier);
        const index = siblings.findIndex((sibling) => sibling.identifier === target.identifier);
        return siblings[index + 1]?.identifier ?? null;
    }
    async applyReparent(type, uid, targetCategoryUid) {
        return type === 'category'
            ? this.client.reparentCategory(uid, targetCategoryUid)
            : this.client.assignProductToCategory(uid, targetCategoryUid);
    }
    findCachedNode(identifier) {
        for (const node of this.rootNodes) {
            if (node.identifier === identifier) {
                return node;
            }
        }
        for (const children of this.childrenByParent.values()) {
            const found = children.find((child) => child.identifier === identifier);
            if (found) {
                return found;
            }
        }
        return undefined;
    }
    renderToggle(node) {
        if (!node.hasChildren) {
            return html `<span class="d-inline-block" style="width:1.5rem"></span>`;
        }
        const expanded = this.expanded.has(node.identifier);
        const text = expanded ? label('collapse', 'Collapse') : label('expand', 'Expand');
        return html `<button
      type="button"
      class="btn btn-borderless btn-sm p-0"
      style="width:1.5rem"
      aria-expanded="${expanded}"
      aria-label="${text}"
      title="${text}"
      @click="${() => void this.toggleNode(node)}"
    >${expanded ? '−' : '+'}</button>`;
    }
    renderActions(node) {
        if (node.type === 'article') {
            return nothing;
        }
        const toggleLabel = node.hidden ? label('enable', 'Enable') : label('disable', 'Disable');
        return html `
      <span class="d-flex gap-1 ms-auto">
        <button type="button" class="btn btn-borderless btn-sm p-0" title="${toggleLabel}" aria-label="${toggleLabel}"
          @click="${(event) => { event.stopPropagation(); void this.onToggleHidden(node); }}">
          <typo3-backend-icon identifier="${node.hidden ? 'actions-eye' : 'actions-ban'}" size="small"></typo3-backend-icon>
        </button>
        <button type="button" class="btn btn-borderless btn-sm p-0" title="${label('copy', 'Copy')}" aria-label="${label('copy', 'Copy')}"
          @click="${(event) => { event.stopPropagation(); void this.onCopy(node); }}">
          <typo3-backend-icon identifier="actions-copy" size="small"></typo3-backend-icon>
        </button>
        <button type="button" class="btn btn-borderless btn-sm p-0" title="${label('delete', 'Delete')}" aria-label="${label('delete', 'Delete')}"
          @click="${(event) => { event.stopPropagation(); void this.onDelete(node); }}">
          <typo3-backend-icon identifier="actions-delete" size="small"></typo3-backend-icon>
        </button>
      </span>
    `;
    }
    rowStateClasses(node) {
        const classes = ['d-flex', 'align-items-center', 'gap-1'];
        if (this.selected === node.identifier) {
            classes.push('bg-primary-subtle', 'rounded');
        }
        if (this.searchActive && this.searchMatches.some((match) => match.identifier === node.identifier)) {
            classes.push('fw-bold');
        }
        if (this.dropTarget?.identifier === node.identifier) {
            if (this.dropTarget.position === 'before') {
                classes.push('border-top', 'border-primary');
            }
            else if (this.dropTarget.position === 'after') {
                classes.push('border-bottom', 'border-primary');
            }
            else {
                classes.push('bg-primary-subtle', 'rounded');
            }
        }
        return classes.join(' ');
    }
    renderNode(node) {
        const expanded = this.expanded.has(node.identifier);
        const children = this.childrenByParent.get(node.identifier) ?? [];
        return html `
      <li>
        <div
          class="${this.rowStateClasses(node)}"
          draggable="${node.type !== 'article'}"
          @dragstart="${(event) => this.onDragStart(event, node)}"
          @dragover="${(event) => this.onDragOver(event, node)}"
          @dragleave="${() => this.onDragLeave()}"
          @drop="${(event) => void this.onDrop(event, node)}"
        >
          ${this.renderToggle(node)}
          <typo3-backend-icon identifier="${node.icon}" size="small" state="${node.hidden ? 'disabled' : 'default'}"></typo3-backend-icon>
          <a
            href="#"
            class="flex-grow-1${node.hidden ? ' text-body-secondary fst-italic' : ''}"
            @click="${(event) => { event.preventDefault(); this.selectNode(node); }}"
          >${node.title}</a>
          ${this.renderActions(node)}
        </div>
        ${expanded && children.length > 0
            ? html `<ul class="list-unstyled ps-4">${children.map((child) => this.renderNode(child))}</ul>`
            : nothing}
      </li>
    `;
    }
    render() {
        return html `
      <goldene-zeiten-products-tree-toolbar
        new-category-url="${this.newCategoryUrl}"
        search-label="${label('search_placeholder', 'Search...')}"
        new-category-label="${label('new_category', 'New category')}"
        @tree-search="${(event) => this.onSearchInput(event)}"
      ></goldene-zeiten-products-tree-toolbar>
      ${this.searchActive && this.searchMatches.length === 0
            ? html `<p class="text-body-secondary">${label('no_results', 'No matches found.')}</p>`
            : nothing}
      <ul class="list-unstyled ps-0">${this.rootNodes.map((node) => this.renderNode(node))}</ul>
    `;
    }
}
ProductsCategoryTree.properties = {
    newCategoryUrl: { attribute: 'new-category-url' },
    rootNodes: { state: true },
    searchMatches: { state: true },
    searchActive: { state: true },
};
customElements.define('goldene-zeiten-products-category-tree', ProductsCategoryTree);

export { ProductsCategoryTree };
//# sourceMappingURL=category-tree.js.map
