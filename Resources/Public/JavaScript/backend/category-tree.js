import { LitElement, html, nothing } from 'lit';
import '@typo3/backend/element/icon-element.js';
import { ModuleStateStorage } from '@typo3/backend/storage/module-state-storage.js';
import { ModuleUtility } from '@typo3/backend/module.js';
import ContextMenu from '@typo3/backend/context-menu.js';

/**
 * Custom dataTransfer type identifying a drag originating from the toolbar's
 * "new category" drag handle, mirroring PageTree's own newTreenode pattern
 * (see @typo3/backend/enum/data-transfer-types.js) so drop handlers can tell
 * "create a new node here" apart from "move this existing node here".
 */
const NEW_CATEGORY_DATA_TRANSFER_TYPE = 'application/x-goldene-zeiten-new-category';

class ProductsTreeToolbar extends LitElement {
    constructor() {
        super();
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
    onNewCategoryDragStart(event) {
        event.dataTransfer?.setData(NEW_CATEGORY_DATA_TRANSFER_TYPE, '1');
        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'copy';
        }
    }
    render() {
        return html `
      <div class="tree-toolbar">
        <div class="tree-toolbar__menu">
          <div class="tree-toolbar__search">
            <label for="productsTreeToolbarSearch" class="visually-hidden">${this.searchLabel}</label>
            <input
              type="search"
              id="productsTreeToolbarSearch"
              class="form-control form-control-sm search-input"
              placeholder="${this.searchLabel}"
              @input="${this.onInput}"
            />
          </div>
        </div>
        <div class="tree-toolbar__submenu">
          <div
            class="tree-toolbar__menuitem tree-toolbar__drag-node"
            title="${this.newCategoryLabel}"
            draggable="true"
            aria-hidden="true"
            @dragstart="${(event) => this.onNewCategoryDragStart(event)}"
          >
            <typo3-backend-icon identifier="products-category" size="small"></typo3-backend-icon>
          </div>
        </div>
      </div>
    `;
    }
}
ProductsTreeToolbar.properties = {
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
    async createCategory(title, parentCategoryUid) {
        const newId = `NEW${Math.floor(Math.random() * 1e9).toString(16)}`;
        const body = new URLSearchParams();
        body.set(`data[${TABLES.category}][${newId}][title]`, title);
        body.set(`data[${TABLES.category}][${newId}][parent_category]`, String(parentCategoryUid));
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

const EXPANDED_STORAGE_KEY = 'products-category-tree-expanded';
/**
 * Persists expand state in localStorage (survives browser restarts, like the
 * page tree). Selection state is a separate concern handled by TYPO3 core's
 * own ModuleStateStorage (sessionStorage), since this element lives in the
 * persistent navigation slot and TYPO3.Backend.ContentContainer/
 * ModuleStateStorage are the mechanism core itself uses for that.
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
}

const MODULE_TYPE = 'products_management';
function typo3() {
    return window.top.TYPO3;
}
function label(key, fallback) {
    return typo3().lang?.[`tree.${key}`] ?? fallback;
}
/**
 * Category/product/article backend tree, modeled after the page tree's behaviour:
 * lazy-loaded nodes, search-and-reveal, drag & drop, edit/hide/delete/copy via
 * the standard context menu, and expand state that survives a module reload.
 * Deliberately not built on top of TYPO3\CMS\Backend's internal
 * AbstractTree/tree.js hierarchy (@internal, not a public API) - this stays a
 * small, self-contained Lit element instead, reusing only public building
 * blocks (typo3-backend-icon, the context menu, the DataHandler AJAX route).
 *
 * Articles are read-only leaves here (see .claude/TREE.md: "Articles from
 * products are not part of the tree, yet.") - not draggable, no context menu.
 *
 * The context menu's own hide/copy actions (context-menu-actions.js) commit
 * straight through the content iframe or a raw AJAX call with no event this
 * tree can generically listen for (only "delete" dispatches a listenable
 * typo3:datahandler:process event) - toggleNode() therefore always re-fetches
 * on expand rather than trusting the cache, so any such change is visible the
 * next time a branch is opened, not just after a full module reload.
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
        /**
         * The only generically-listenable signal context-menu-actions.js emits for
         * any table (see class doc) - used to drop a deleted node from the cache
         * immediately instead of waiting for its parent to be re-expanded.
         */
        this.onDataHandlerProcess = (event) => {
            const payload = event.detail?.payload;
            if (!payload || payload.action !== 'delete' || payload.table === undefined || payload.uid === undefined) {
                return;
            }
            const type = ['category', 'product', 'article'].find((candidate) => tableForType(candidate) === payload.table);
            if (!type) {
                return;
            }
            const identifier = `${type}-${payload.uid}`;
            const node = this.findCachedNode(identifier);
            this.expanded.delete(identifier);
            this.childrenByParent.delete(identifier);
            if (node) {
                void this.refreshParent(node.parentIdentifier);
            }
        };
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
        document.addEventListener('typo3:datahandler:process', this.onDataHandlerProcess);
        void this.initialize();
    }
    disconnectedCallback() {
        document.removeEventListener('typo3:datahandler:process', this.onDataHandlerProcess);
        super.disconnectedCallback();
    }
    /**
     * This element lives in the persistent .scaffold-content-navigation slot
     * (see navigationComponent in Configuration/Backend/Modules.php), not inside
     * the module's own content iframe, so the selection is restored from
     * ModuleStateStorage (sessionStorage, like PageTree) rather than from
     * window.location - this element's own window never carries ?category=/
     * ?product=, only the separate content iframe does.
     */
    async initialize() {
        await this.loadRoot();
        const state = ModuleStateStorage.current(MODULE_TYPE);
        if (state.identifier) {
            this.selected = state.identifier;
            await this.revealSelected();
            this.navigateContent(state.identifier);
        }
    }
    navigateContent(identifier) {
        const [type, uid] = identifier.split('-');
        if (type !== 'category' && type !== 'product') {
            return;
        }
        const moduleInfo = ModuleUtility.getFromName(MODULE_TYPE);
        if (!moduleInfo) {
            return;
        }
        const separator = moduleInfo.link.includes('?') ? '&' : '?';
        typo3().Backend.ContentContainer.setUrl(`${moduleInfo.link}${separator}${type}=${uid}`);
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
            await this.ensureChildrenLoaded(node.identifier, true);
        }
        this.persistExpanded();
        this.requestUpdate();
    }
    selectNode(node) {
        if (node.type === 'article') {
            return;
        }
        this.selected = node.identifier;
        ModuleStateStorage.update(MODULE_TYPE, node.identifier);
        this.navigateContent(node.identifier);
        this.requestUpdate();
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
    /**
     * Mirrors page-tree-element.js's showContextMenu handler exactly: the
     * standard context menu (RecordProvider) already offers edit/hide/delete/
     * copy/history for any TCA table with no extra registration needed.
     */
    onContextMenu(event, node) {
        if (node.type === 'article') {
            return;
        }
        event.preventDefault();
        ContextMenu.show(tableForType(node.type), String(node.uid), 'tree', '', '', event.currentTarget, event);
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
        if (event.dataTransfer?.types.includes(NEW_CATEGORY_DATA_TRANSFER_TYPE)) {
            // Stop here regardless of target validity so hovering a product/article
            // node can't fall through to the root drop zone's "create at root"
            // handling further up the bubbling chain.
            event.stopPropagation();
            if (target.type !== 'category') {
                return;
            }
            event.preventDefault();
            this.dropTarget = { identifier: target.identifier, position: 'into' };
            this.requestUpdate();
            return;
        }
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
    onRootDragOver(event) {
        if (event.dataTransfer?.types.includes(NEW_CATEGORY_DATA_TRANSFER_TYPE)) {
            event.preventDefault();
        }
    }
    async onRootDrop(event) {
        if (!event.dataTransfer?.types.includes(NEW_CATEGORY_DATA_TRANSFER_TYPE)) {
            return;
        }
        event.preventDefault();
        await this.createCategory(0, 'root');
    }
    async createCategory(parentUid, parentIdentifier) {
        const title = window.prompt(label('new_category_title', 'Category title:'));
        if (!title) {
            return;
        }
        const succeeded = await this.client.createCategory(title, parentUid);
        if (succeeded) {
            await this.refreshParent(parentIdentifier);
        }
        else {
            this.notifyError();
        }
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
        const isNewCategory = event.dataTransfer?.types.includes(NEW_CATEGORY_DATA_TRANSFER_TYPE) ?? false;
        const dragged = this.dragPayload;
        const position = this.dropTarget?.position ?? null;
        this.dragPayload = null;
        this.dropTarget = null;
        this.requestUpdate();
        if (isNewCategory) {
            event.stopPropagation();
            if (target.type === 'category') {
                await this.createCategory(target.uid, target.identifier);
            }
            return;
        }
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
          @contextmenu="${(event) => this.onContextMenu(event, node)}"
        >
          ${this.renderToggle(node)}
          <typo3-backend-icon identifier="${node.icon}" size="small" state="${node.hidden ? 'disabled' : 'default'}"></typo3-backend-icon>
          <a
            href="#"
            class="flex-grow-1${node.hidden ? ' text-body-secondary fst-italic' : ''}"
            @click="${(event) => { event.preventDefault(); this.selectNode(node); }}"
          >${node.title}</a>
        </div>
        ${expanded && children.length > 0
            ? html `<ul class="list-unstyled ps-4">${children.map((child) => this.renderNode(child))}</ul>`
            : nothing}
      </li>
    `;
    }
    render() {
        return html `
      <div class="tree">
        <goldene-zeiten-products-tree-toolbar
          search-label="${label('search_placeholder', 'Search...')}"
          new-category-label="${label('new_category', 'New category')}"
          @tree-search="${(event) => this.onSearchInput(event)}"
        ></goldene-zeiten-products-tree-toolbar>
        <div class="navigation-tree-container">
          ${this.searchActive && this.searchMatches.length === 0
            ? html `<p class="text-body-secondary px-2">${label('no_results', 'No matches found.')}</p>`
            : nothing}
          <ul
            class="list-unstyled ps-0"
            style="min-height:2rem"
            @dragover="${(event) => this.onRootDragOver(event)}"
            @drop="${(event) => void this.onRootDrop(event)}"
          >${this.rootNodes.map((node) => this.renderNode(node))}</ul>
        </div>
      </div>
    `;
    }
}
ProductsCategoryTree.properties = {
    rootNodes: { state: true },
    searchMatches: { state: true },
    searchActive: { state: true },
};
customElements.define('goldene-zeiten-products-category-tree', ProductsCategoryTree);
/**
 * Read by @typo3/backend/viewport/navigation-container.js's showComponent() to
 * know this module exports a Lit custom element (rather than falling back to
 * its legacy AMD-style `.initialize()` path) - required for navigationComponent
 * wiring in Configuration/Backend/Modules.php to find the right tag name.
 */
const navigationComponentName = 'goldene-zeiten-products-category-tree';

export { ProductsCategoryTree, navigationComponentName };
//# sourceMappingURL=category-tree.js.map
