import { LitElement, html, nothing, TemplateResult } from 'lit';
import type { PropertyValues } from 'lit';
import '@typo3/backend/element/icon-element.js';
import { ModuleStateStorage } from '@typo3/backend/storage/module-state-storage.js';
import { ModuleUtility } from '@typo3/backend/module.js';
import ContextMenu from '@typo3/backend/context-menu.js';
import './TreeToolbar';
import { CategoryTreeClient, tableForType } from './CategoryTreeClient';
import { TreeState } from './TreeState';
import { NEW_CATEGORY_DATA_TRANSFER_TYPE, NEW_PRODUCT_DATA_TRANSFER_TYPE } from './TreeTypes';
import type { DropPosition, NewNodeType, NodeType, SearchMatch, TreeNode } from './TreeTypes';

const MODULE_TYPE = 'products_management';

interface DataHandlerProcessPayload {
  table?: string;
  uid?: string | number;
  action?: string;
}

interface DragPayload {
  identifier: string;
  type: NodeType;
}

function typo3(): Typo3Global {
  return (window.top as unknown as Window).TYPO3;
}

function label(key: string, fallback: string): string {
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
export class ProductsCategoryTree extends LitElement {
  static properties = {
    rootNodes: { state: true },
    searchMatches: { state: true },
    searchActive: { state: true },
  };

  declare rootNodes: TreeNode[];
  declare searchMatches: SearchMatch[];
  declare searchActive: boolean;

  private readonly client = new CategoryTreeClient();
  private readonly state = new TreeState();
  private readonly childrenByParent = new Map<string, TreeNode[]>();
  private expanded = new Set<string>();
  private selected: string | null = null;
  private dragPayload: DragPayload | null = null;
  private dropTarget: { identifier: string; position: DropPosition } | null = null;
  private searchTimer: number | null = null;
  private newNodeDraft: { type: NewNodeType; parentUid: number; parentIdentifier: string } | null = null;
  private shouldFocusDraftInput = false;
  private storageFolderPid = 0;

  constructor() {
    super();
    this.rootNodes = [];
    this.searchMatches = [];
    this.searchActive = false;
  }

  createRenderRoot(): this {
    return this;
  }

  updated(changedProperties: PropertyValues): void {
    super.updated(changedProperties);
    if (this.shouldFocusDraftInput) {
      this.shouldFocusDraftInput = false;
      this.querySelector<HTMLInputElement>('[data-new-node-draft-input]')?.focus();
    }
  }

  connectedCallback(): void {
    super.connectedCallback();
    this.expanded = this.state.getExpanded();
    document.addEventListener('typo3:datahandler:process', this.onDataHandlerProcess);
    void this.initialize();
  }

  disconnectedCallback(): void {
    document.removeEventListener('typo3:datahandler:process', this.onDataHandlerProcess);
    super.disconnectedCallback();
  }

  /**
   * The only generically-listenable signal context-menu-actions.js emits for
   * any table (see class doc) - used to drop a deleted node from the cache
   * immediately instead of waiting for its parent to be re-expanded.
   */
  private readonly onDataHandlerProcess = (event: Event): void => {
    const payload = (event as CustomEvent<{ payload: DataHandlerProcessPayload }>).detail?.payload;
    if (!payload || payload.action !== 'delete' || payload.table === undefined || payload.uid === undefined) {
      return;
    }
    const type = (['category', 'product', 'article'] as const).find(
      (candidate) => tableForType(candidate) === payload.table
    );
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

  /**
   * This element lives in the persistent .scaffold-content-navigation slot
   * (see navigationComponent in Configuration/Backend/Modules.php), not inside
   * the module's own content iframe, so the selection is restored from
   * ModuleStateStorage (sessionStorage, like PageTree) rather than from
   * window.location - this element's own window never carries ?category=/
   * ?product=, only the separate content iframe does.
   */
  private async initialize(): Promise<void> {
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
  private async restoreExpandedState(): Promise<void> {
    const queue = [...this.rootNodes];
    while (queue.length > 0) {
      const node = queue.shift() as TreeNode;
      if (!this.expanded.has(node.identifier)) {
        continue;
      }
      const children = await this.ensureChildrenLoaded(node.identifier);
      queue.push(...children);
    }
    this.requestUpdate();
  }

  private navigateContent(identifier: string): void {
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

  private async revealSelected(): Promise<void> {
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

  private async loadRoot(): Promise<void> {
    this.rootNodes = await this.client.fetchNodes('root');
  }

  private async ensureChildrenLoaded(identifier: string, forceRefresh = false): Promise<TreeNode[]> {
    if (!forceRefresh && this.childrenByParent.has(identifier)) {
      return this.childrenByParent.get(identifier) as TreeNode[];
    }
    const children = await this.client.fetchNodes(identifier);
    this.childrenByParent.set(identifier, children);
    return children;
  }

  private async refreshParent(parentIdentifier: string): Promise<void> {
    if (parentIdentifier === 'root') {
      await this.loadRoot();
    } else {
      await this.ensureChildrenLoaded(parentIdentifier, true);
    }
    this.requestUpdate();
  }

  private persistExpanded(): void {
    this.state.setExpanded(this.expanded);
  }

  private async toggleNode(node: TreeNode): Promise<void> {
    if (!node.hasChildren) {
      return;
    }
    if (this.expanded.has(node.identifier)) {
      this.expanded.delete(node.identifier);
    } else {
      this.expanded.add(node.identifier);
      await this.ensureChildrenLoaded(node.identifier, true);
    }
    this.persistExpanded();
    this.requestUpdate();
  }

  private selectNode(node: TreeNode): void {
    if (node.type === 'article') {
      return;
    }
    this.selected = node.identifier;
    ModuleStateStorage.update(MODULE_TYPE, node.identifier);
    this.navigateContent(node.identifier);
    this.requestUpdate();
  }

  private onSearchInput(event: CustomEvent<string>): void {
    const query = event.detail.trim();
    if (this.searchTimer !== null) {
      window.clearTimeout(this.searchTimer);
    }
    this.searchTimer = window.setTimeout(() => void this.runSearch(query), 250);
  }

  private async runSearch(query: string): Promise<void> {
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
  private onContextMenu(event: MouseEvent, node: TreeNode): void {
    if (node.type === 'article') {
      return;
    }
    event.preventDefault();
    ContextMenu.show(tableForType(node.type), String(node.uid), 'tree', '', '', event.currentTarget as HTMLElement, event);
  }

  private notifyError(): void {
    window.alert(label('error_generic', 'The action could not be completed.'));
  }

  private onDragStart(event: DragEvent, node: TreeNode): void {
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
  private resolveNewNodeType(dataTransfer: DataTransfer | null): NewNodeType | null {
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

  private canDropNewNode(newNodeType: NewNodeType, targetType: NodeType | null): boolean {
    if (targetType === 'category') {
      return true;
    }
    return newNodeType === 'category' && targetType === null;
  }

  private onDragOver(event: DragEvent, target: TreeNode): void {
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

  private onRootDragOver(event: DragEvent): void {
    const newNodeType = this.resolveNewNodeType(event.dataTransfer);
    if (newNodeType && this.canDropNewNode(newNodeType, null)) {
      event.preventDefault();
    }
  }

  private async onRootDrop(event: DragEvent): Promise<void> {
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
  private async startNewNodeDraft(type: NewNodeType, parentUid: number, parentIdentifier: string): Promise<void> {
    if (parentIdentifier !== 'root') {
      await this.ensureChildrenLoaded(parentIdentifier);
    }
    this.expanded.add(parentIdentifier);
    this.newNodeDraft = { type, parentUid, parentIdentifier };
    this.shouldFocusDraftInput = true;
    this.requestUpdate();
  }

  private onDraftKeyDown(event: KeyboardEvent): void {
    if (event.key === 'Enter') {
      event.preventDefault();
      void this.commitNewNodeDraft((event.target as HTMLInputElement).value);
    } else if (event.key === 'Escape') {
      event.preventDefault();
      this.newNodeDraft = null;
      this.requestUpdate();
    }
  }

  private async commitNewNodeDraft(title: string): Promise<void> {
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
    const succeeded =
      draft.type === 'category'
        ? await this.client.createCategory(trimmedTitle, draft.parentUid, this.storageFolderPid)
        : await this.client.createProduct(trimmedTitle, draft.parentUid, this.storageFolderPid);
    if (succeeded) {
      await this.refreshParent(draft.parentIdentifier);
    } else {
      this.notifyError();
      this.requestUpdate();
    }
  }

  private onDragLeave(): void {
    this.dropTarget = null;
    this.requestUpdate();
  }

  private computeDropPosition(event: DragEvent, target: TreeNode): DropPosition {
    const row = event.currentTarget as HTMLElement;
    const rect = row.getBoundingClientRect();
    const ratio = (event.clientY - rect.top) / rect.height;
    if (target.type === 'category' && ratio > 0.25 && ratio < 0.75) {
      return 'into';
    }
    return ratio < 0.5 ? 'before' : 'after';
  }

  private canDrop(draggedType: NodeType, targetType: NodeType, position: DropPosition): boolean {
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

  private async onDrop(event: DragEvent, target: TreeNode): Promise<void> {
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

  private async applyDrop(dragged: DragPayload, target: TreeNode, position: DropPosition): Promise<void> {
    const draggedNode = this.findCachedNode(dragged.identifier);
    const oldParent = draggedNode?.parentIdentifier ?? null;
    const [, draggedUidRaw] = dragged.identifier.split('-');
    const draggedUid = parseInt(draggedUidRaw, 10);
    const succeeded =
      position === 'into'
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
  private async resolveBeforeIdentifier(target: TreeNode, position: DropPosition): Promise<string | null> {
    if (position === 'before') {
      return target.identifier;
    }
    const siblings = await this.ensureChildrenLoaded(target.parentIdentifier);
    const index = siblings.findIndex((sibling) => sibling.identifier === target.identifier);
    return siblings[index + 1]?.identifier ?? null;
  }

  private async applyReparent(type: NodeType, uid: number, targetCategoryUid: number): Promise<boolean> {
    return type === 'category'
      ? this.client.reparentCategory(uid, targetCategoryUid)
      : this.client.assignProductToCategory(uid, targetCategoryUid);
  }

  private findCachedNode(identifier: string): TreeNode | undefined {
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

  private renderToggle(node: TreeNode): TemplateResult {
    if (!node.hasChildren) {
      return html`<span class="d-inline-block" style="width:1.5rem"></span>`;
    }
    const expanded = this.expanded.has(node.identifier);
    const text = expanded ? label('collapse', 'Collapse') : label('expand', 'Expand');
    return html`<button
      type="button"
      class="btn btn-borderless btn-sm p-0"
      style="width:1.5rem"
      aria-expanded="${expanded}"
      aria-label="${text}"
      title="${text}"
      @click="${() => void this.toggleNode(node)}"
    >${expanded ? '−' : '+'}</button>`;
  }

  private rowStateClasses(node: TreeNode): string {
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
      } else if (this.dropTarget.position === 'after') {
        classes.push('border-bottom', 'border-primary');
      } else {
        classes.push('bg-primary-subtle', 'rounded');
      }
    }
    return classes.join(' ');
  }

  private renderDraftRow(draft: { type: NewNodeType; parentUid: number; parentIdentifier: string }): TemplateResult {
    const icon = draft.type === 'category' ? 'products-category' : 'products-product';
    const titleLabel =
      draft.type === 'category' ? label('new_category_title', 'Category title:') : label('new_product_title', 'Product title:');
    return html`
      <li>
        <div class="d-flex align-items-center gap-1">
          <span class="d-inline-block" style="width:1.5rem"></span>
          <typo3-backend-icon identifier="${icon}" size="small"></typo3-backend-icon>
          <input
            type="text"
            class="form-control form-control-sm flex-grow-1"
            data-new-node-draft-input
            placeholder="${titleLabel}"
            aria-label="${titleLabel}"
            @keydown="${(event: KeyboardEvent) => this.onDraftKeyDown(event)}"
            @blur="${(event: FocusEvent) => void this.commitNewNodeDraft((event.target as HTMLInputElement).value)}"
          />
        </div>
      </li>
    `;
  }

  private renderNode(node: TreeNode): TemplateResult {
    const expanded = this.expanded.has(node.identifier);
    const children = this.childrenByParent.get(node.identifier) ?? [];
    const draft = this.newNodeDraft?.parentIdentifier === node.identifier ? this.newNodeDraft : null;
    return html`
      <li>
        <div
          class="${this.rowStateClasses(node)}"
          draggable="${node.type !== 'article'}"
          @dragstart="${(event: DragEvent) => this.onDragStart(event, node)}"
          @dragover="${(event: DragEvent) => this.onDragOver(event, node)}"
          @dragleave="${() => this.onDragLeave()}"
          @drop="${(event: DragEvent) => void this.onDrop(event, node)}"
          @contextmenu="${(event: MouseEvent) => this.onContextMenu(event, node)}"
        >
          ${this.renderToggle(node)}
          <typo3-backend-icon identifier="${node.icon}" size="small" state="${node.hidden ? 'disabled' : 'default'}"></typo3-backend-icon>
          <a
            href="#"
            class="flex-grow-1${node.hidden ? ' text-body-secondary fst-italic' : ''}"
            @click="${(event: Event) => { event.preventDefault(); this.selectNode(node); }}"
          >${node.title}</a>
        </div>
        ${expanded && (children.length > 0 || draft)
          ? html`<ul class="list-unstyled ps-4">
              ${children.map((child) => this.renderNode(child))}
              ${draft ? this.renderDraftRow(draft) : nothing}
            </ul>`
          : nothing}
      </li>
    `;
  }

  render(): TemplateResult {
    return html`
      <div class="tree">
        <goldene-zeiten-products-tree-toolbar
          search-label="${label('search_placeholder', 'Search...')}"
          new-category-label="${label('new_category', 'New category')}"
          new-product-label="${label('new_product', 'New product')}"
          @tree-search="${(event: CustomEvent<string>) => this.onSearchInput(event)}"
        ></goldene-zeiten-products-tree-toolbar>
        <div class="navigation-tree-container">
          ${this.searchActive && this.searchMatches.length === 0
            ? html`<p class="text-body-secondary px-2">${label('no_results', 'No matches found.')}</p>`
            : nothing}
          <ul
            class="list-unstyled ps-0"
            style="min-height:2rem"
            @dragover="${(event: DragEvent) => this.onRootDragOver(event)}"
            @drop="${(event: DragEvent) => void this.onRootDrop(event)}"
          >
            ${this.rootNodes.map((node) => this.renderNode(node))}
            ${this.newNodeDraft?.parentIdentifier === 'root' ? this.renderDraftRow(this.newNodeDraft) : nothing}
          </ul>
        </div>
      </div>
    `;
  }
}

customElements.define('goldene-zeiten-products-category-tree', ProductsCategoryTree);

/**
 * Read by @typo3/backend/viewport/navigation-container.js's showComponent() to
 * know this module exports a Lit custom element (rather than falling back to
 * its legacy AMD-style `.initialize()` path) - required for navigationComponent
 * wiring in Configuration/Backend/Modules.php to find the right tag name.
 */
export const navigationComponentName = 'goldene-zeiten-products-category-tree';
