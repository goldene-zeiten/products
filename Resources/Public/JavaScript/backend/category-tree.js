import { LitElement, html, nothing } from 'lit';
import '@typo3/backend/element/icon-element.js';
import { ModuleStateStorage } from '@typo3/backend/storage/module-state-storage.js';
import { ModuleUtility } from '@typo3/backend/module.js';
import ContextMenu from '@typo3/backend/context-menu.js';

/**
 * Custom dataTransfer types identifying a drag originating from one of the
 * toolbar's "new node" drag handles, mirroring PageTree's own newTreenode
 * pattern (see @typo3/backend/enum/data-transfer-types.js) so drop handlers
 * can tell "create a new node here" apart from "move this existing node here".
 */
const NEW_CATEGORY_DATA_TRANSFER_TYPE = 'application/x-goldene-zeiten-new-category';
const NEW_PRODUCT_DATA_TRANSFER_TYPE = 'application/x-goldene-zeiten-new-product';

class ProductsTreeToolbar extends LitElement {
    constructor() {
        super();
        this.searchLabel = 'Search...';
        this.newCategoryLabel = 'New category';
        this.newProductLabel = 'New product';
    }
    createRenderRoot() {
        return this;
    }
    onInput(event) {
        const query = event.target.value;
        this.dispatchEvent(new CustomEvent('tree-search', { detail: query, bubbles: true, composed: true }));
    }
    onDragStart(event, dataTransferType) {
        event.dataTransfer?.setData(dataTransferType, '1');
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
            @dragstart="${(event) => this.onDragStart(event, NEW_CATEGORY_DATA_TRANSFER_TYPE)}"
          >
            <typo3-backend-icon identifier="products-category" size="small"></typo3-backend-icon>
          </div>
          <div
            class="tree-toolbar__menuitem tree-toolbar__drag-node"
            title="${this.newProductLabel}"
            draggable="true"
            aria-hidden="true"
            @dragstart="${(event) => this.onDragStart(event, NEW_PRODUCT_DATA_TRANSFER_TYPE)}"
          >
            <typo3-backend-icon identifier="products-product" size="small"></typo3-backend-icon>
          </div>
        </div>
      </div>
    `;
    }
}
ProductsTreeToolbar.properties = {
    searchLabel: { attribute: 'search-label' },
    newCategoryLabel: { attribute: 'new-category-label' },
    newProductLabel: { attribute: 'new-product-label' },
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
class CategoryTreeClient {
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
 * blocks (typo3-backend-icon, the context menu, the DataHandler AJAX route,
 * and the page tree's own global .node/.node-treelines/.node-toggle CSS
 * classes for a matching look without any bespoke styling).
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
        this.newNodeDraft = null;
        this.shouldFocusDraftInput = false;
        this.storageFolderPid = 0;
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
    updated(changedProperties) {
        super.updated(changedProperties);
        if (this.shouldFocusDraftInput) {
            this.shouldFocusDraftInput = false;
            this.querySelector('[data-new-node-draft-input]')?.focus();
        }
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
        const configuration = await this.client.fetchConfiguration();
        this.storageFolderPid = configuration.storageFolderPid;
        await this.loadRoot();
        await this.restoreExpandedState();
        const state = ModuleStateStorage.current(MODULE_TYPE);
        if (state.identifier) {
            this.selected = state.identifier;
            await this.revealSelected();
            this.navigateContent(state.identifier);
        }
    }
    /**
     * The persisted `expanded` set only records identifiers - it doesn't imply
     * their children were ever fetched into childrenByParent. Without this, a
     * node restored as "expanded" (toggle shows collapse state) would render no
     * children at all after a fresh page load, since renderNode() only shows a
     * branch when both expanded AND its children are cached. Walks down from
     * the roots, loading children for every persisted-expanded node reachable
     * that way (not just the current selection's ancestors).
     */
    async restoreExpandedState() {
        const queue = [...this.rootNodes];
        while (queue.length > 0) {
            const node = queue.shift();
            if (!this.expanded.has(node.identifier)) {
                continue;
            }
            const children = await this.ensureChildrenLoaded(node.identifier);
            queue.push(...children);
        }
        this.requestUpdate();
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
    /**
     * A category drag is droppable on another category (nest) or empty tree
     * background (top-level); a product drag is droppable on a category only -
     * a product MUST have a category, so root/background isn't valid for it.
     */
    resolveNewNodeType(dataTransfer) {
        if (!dataTransfer) {
            return null;
        }
        if (dataTransfer.types.includes(NEW_CATEGORY_DATA_TRANSFER_TYPE)) {
            return 'category';
        }
        if (dataTransfer.types.includes(NEW_PRODUCT_DATA_TRANSFER_TYPE)) {
            return 'product';
        }
        return null;
    }
    canDropNewNode(newNodeType, targetType) {
        if (targetType === 'category') {
            return true;
        }
        return newNodeType === 'category' && targetType === null;
    }
    onDragOver(event, target) {
        const newNodeType = this.resolveNewNodeType(event.dataTransfer);
        if (newNodeType) {
            // Stop here regardless of target validity so hovering an ineligible
            // node can't fall through to the root drop zone's "create at root"
            // handling further up the bubbling chain.
            event.stopPropagation();
            if (!this.canDropNewNode(newNodeType, target.type)) {
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
        const newNodeType = this.resolveNewNodeType(event.dataTransfer);
        if (newNodeType && this.canDropNewNode(newNodeType, null)) {
            event.preventDefault();
        }
    }
    async onRootDrop(event) {
        const newNodeType = this.resolveNewNodeType(event.dataTransfer);
        if (!newNodeType || !this.canDropNewNode(newNodeType, null)) {
            return;
        }
        event.preventDefault();
        await this.startNewNodeDraft(newNodeType, 0, 'root');
    }
    /**
     * Mirrors PageTree's own drag-to-create UX (page-tree-element.js's
     * handleNodeAdd -> editNode): dropping a toolbar drag handle doesn't
     * prompt, it opens an inline text input directly in the tree at the drop
     * location, committed on Enter/blur and cancelled on Escape.
     */
    async startNewNodeDraft(type, parentUid, parentIdentifier) {
        if (parentIdentifier !== 'root') {
            await this.ensureChildrenLoaded(parentIdentifier);
        }
        this.expanded.add(parentIdentifier);
        this.newNodeDraft = { type, parentUid, parentIdentifier };
        this.shouldFocusDraftInput = true;
        this.requestUpdate();
    }
    onDraftKeyDown(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            void this.commitNewNodeDraft(event.target.value);
        }
        else if (event.key === 'Escape') {
            event.preventDefault();
            this.newNodeDraft = null;
            this.requestUpdate();
        }
    }
    async commitNewNodeDraft(title) {
        const draft = this.newNodeDraft;
        if (!draft) {
            return;
        }
        this.newNodeDraft = null;
        const trimmedTitle = title.trim();
        if (trimmedTitle === '') {
            this.requestUpdate();
            return;
        }
        const succeeded = draft.type === 'category'
            ? await this.client.createCategory(trimmedTitle, draft.parentUid, this.storageFolderPid)
            : await this.client.createProduct(trimmedTitle, draft.parentUid, this.storageFolderPid);
        if (succeeded) {
            await this.refreshParent(draft.parentIdentifier);
        }
        else {
            this.notifyError();
            this.requestUpdate();
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
        const newNodeType = this.resolveNewNodeType(event.dataTransfer);
        const dragged = this.dragPayload;
        const position = this.dropTarget?.position ?? null;
        this.dragPayload = null;
        this.dropTarget = null;
        this.requestUpdate();
        if (newNodeType) {
            event.stopPropagation();
            if (this.canDropNewNode(newNodeType, target.type)) {
                await this.startNewNodeDraft(newNodeType, target.uid, target.identifier);
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
            return html `<span class="node-toggle"></span>`;
        }
        const expanded = this.expanded.has(node.identifier);
        const text = expanded ? label('collapse', 'Collapse') : label('expand', 'Expand');
        return html `<button
      type="button"
      class="node-toggle"
      style="background:transparent;border:0;padding:0;color:inherit"
      aria-label="${text}"
      title="${text}"
      tabindex="-1"
      @click="${(event) => {
            event.stopPropagation();
            void this.toggleNode(node);
        }}"
    >
      <typo3-backend-icon
        identifier="${expanded ? 'actions-chevron-down' : 'actions-chevron-end'}"
        size="small"
      ></typo3-backend-icon>
    </button>`;
    }
    /**
     * Mirrors PageTree's node-treelines: one filler slot per ancestor depth
     * (a continuing vertical line if that ancestor still has siblings below it,
     * blank otherwise), followed by this node's own connector (an "L" if it's
     * the last child, a "T" if siblings follow) - all drawn via TYPO3 core's
     * own .node-treeline* classes, not bespoke CSS.
     */
    renderTreelines(ancestorGuides, isLast) {
        return html `<div class="node-treelines">
      ${ancestorGuides.map((hasMoreSiblings) => html `<div class="node-treeline${hasMoreSiblings ? ' node-treeline--line' : ''}"></div>`)}
      <div class="node-treeline node-treeline--${isLast ? 'last' : 'connect'}"></div>
    </div>`;
    }
    nodeClasses(node) {
        const classes = ['node'];
        if (this.selected === node.identifier) {
            classes.push('node-selected');
        }
        if (this.searchActive && this.searchMatches.some((match) => match.identifier === node.identifier)) {
            classes.push('fw-bold');
        }
        if (this.dragPayload?.identifier === node.identifier) {
            classes.push('node-dragging');
        }
        if (this.dropTarget?.identifier === node.identifier) {
            if (this.dropTarget.position === 'before') {
                classes.push('node-dragging-before');
            }
            else if (this.dropTarget.position === 'after') {
                classes.push('node-dragging-after');
            }
            else {
                classes.push('node-active');
            }
        }
        return classes.join(' ');
    }
    renderDraftRow(draft, ancestorGuides) {
        const icon = draft.type === 'category' ? 'products-category' : 'products-product';
        const titleLabel = draft.type === 'category'
            ? label('new_category_title', 'Category title:')
            : label('new_product_title', 'Product title:');
        return html `
      <li>
        <div class="node" style="position:relative;height:32px">
          ${this.renderTreelines(ancestorGuides, true)}
          <span class="node-toggle"></span>
          <div class="node-content">
            <span class="node-icon"><typo3-backend-icon identifier="${icon}" size="small"></typo3-backend-icon></span>
            <div class="node-contentlabel">
              <input
                type="text"
                class="form-control form-control-sm"
                data-new-node-draft-input
                placeholder="${titleLabel}"
                aria-label="${titleLabel}"
                @keydown="${(event) => this.onDraftKeyDown(event)}"
                @blur="${(event) => void this.commitNewNodeDraft(event.target.value)}"
              />
            </div>
          </div>
        </div>
      </li>
    `;
    }
    renderNode(node, ancestorGuides = [], isLast = true) {
        const expanded = this.expanded.has(node.identifier);
        const children = this.childrenByParent.get(node.identifier) ?? [];
        const draft = this.newNodeDraft?.parentIdentifier === node.identifier ? this.newNodeDraft : null;
        const childAncestorGuides = [...ancestorGuides, !isLast];
        return html `
      <li>
        <div
          class="${this.nodeClasses(node)}"
          style="position:relative;height:32px"
          role="treeitem"
          tabindex="0"
          aria-level="${ancestorGuides.length + 1}"
          aria-selected="${this.selected === node.identifier}"
          aria-expanded="${node.hasChildren ? expanded : nothing}"
          draggable="${node.type !== 'article'}"
          @click="${() => this.selectNode(node)}"
          @keydown="${(event) => this.onNodeKeyDown(event, node)}"
          @dragstart="${(event) => this.onDragStart(event, node)}"
          @dragover="${(event) => this.onDragOver(event, node)}"
          @dragleave="${() => this.onDragLeave()}"
          @drop="${(event) => void this.onDrop(event, node)}"
          @contextmenu="${(event) => this.onContextMenu(event, node)}"
        >
          ${this.renderTreelines(ancestorGuides, isLast)} ${this.renderToggle(node)}
          <div class="node-content">
            <span class="node-icon">
              <typo3-backend-icon
                identifier="${node.icon}"
                size="small"
                state="${node.hidden ? 'disabled' : 'default'}"
              ></typo3-backend-icon>
            </span>
            <div class="node-contentlabel">
              <div class="node-name${node.hidden ? ' text-body-secondary fst-italic' : ''}">${node.title}</div>
            </div>
          </div>
        </div>
        ${expanded && (children.length > 0 || draft)
            ? html `<ul class="list-unstyled ps-0" role="group">
                ${children.map((child, index) => this.renderNode(child, childAncestorGuides, index === children.length - 1 && !draft))}
                ${draft ? this.renderDraftRow(draft, childAncestorGuides) : nothing}
              </ul>`
            : nothing}
      </li>
    `;
    }
    onNodeKeyDown(event, node) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            this.selectNode(node);
            return;
        }
        if (!node.hasChildren) {
            return;
        }
        const expanded = this.expanded.has(node.identifier);
        if (event.key === 'ArrowRight' && !expanded) {
            event.preventDefault();
            void this.toggleNode(node);
        }
        else if (event.key === 'ArrowLeft' && expanded) {
            event.preventDefault();
            void this.toggleNode(node);
        }
    }
    render() {
        const rootDraft = this.newNodeDraft?.parentIdentifier === 'root' ? this.newNodeDraft : null;
        return html `
      <div class="tree">
        <goldene-zeiten-products-tree-toolbar
          search-label="${label('search_placeholder', 'Search...')}"
          new-category-label="${label('new_category', 'New category')}"
          new-product-label="${label('new_product', 'New product')}"
          @tree-search="${(event) => this.onSearchInput(event)}"
        ></goldene-zeiten-products-tree-toolbar>
        <div class="navigation-tree-container">
          ${this.searchActive && this.searchMatches.length === 0
            ? html `<p class="text-body-secondary px-2">${label('no_results', 'No matches found.')}</p>`
            : nothing}
          <ul
            class="list-unstyled ps-0"
            style="min-height:2rem"
            role="tree"
            aria-label="${label('tree_label', 'Categories and products')}"
            @dragover="${(event) => this.onRootDragOver(event)}"
            @drop="${(event) => void this.onRootDrop(event)}"
          >
            ${this.rootNodes.map((node, index) => this.renderNode(node, [], index === this.rootNodes.length - 1 && !rootDraft))}
            ${rootDraft ? this.renderDraftRow(rootDraft, []) : nothing}
          </ul>
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
