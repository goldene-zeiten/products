import { LitElement, html, nothing, TemplateResult } from 'lit';
import '@typo3/backend/element/icon-element.js';
import './TreeToolbar';
import { CategoryTreeClient } from './CategoryTreeClient';
import { TreeState } from './TreeState';
import type { DropPosition, NodeType, SearchMatch, TreeNode } from './TreeTypes';

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
 * lazy-loaded nodes, search-and-reveal, drag & drop, inline actions, and expand
 * state that survives a module reload. Deliberately not built on top of
 * TYPO3\CMS\Backend's internal AbstractTree/tree.js hierarchy (@internal, not a
 * public API) - this stays a small, self-contained Lit element instead, reusing
 * only public building blocks (typo3-backend-icon, the DataHandler AJAX route).
 *
 * Articles are read-only leaves here (see .claude/TREE.md: "Articles from
 * products are not part of the tree, yet.") - not draggable, no inline actions.
 */
export class ProductsCategoryTree extends LitElement {
  static properties = {
    newCategoryUrl: { attribute: 'new-category-url' },
    rootNodes: { state: true },
    searchMatches: { state: true },
    searchActive: { state: true },
  };

  declare newCategoryUrl: string;
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

  constructor() {
    super();
    this.newCategoryUrl = '';
    this.rootNodes = [];
    this.searchMatches = [];
    this.searchActive = false;
  }

  createRenderRoot(): this {
    return this;
  }

  connectedCallback(): void {
    super.connectedCallback();
    this.expanded = this.state.getExpanded();
    this.selected = this.state.getSelected();
    void this.initialize();
  }

  private async initialize(): Promise<void> {
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

  private hasSelectionInUrl(): boolean {
    const params = new URLSearchParams(window.location.search);
    return params.has('category') || params.has('product');
  }

  private currentSelectionIdentifier(): string | null {
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
      await this.ensureChildrenLoaded(node.identifier);
    }
    this.persistExpanded();
    this.requestUpdate();
  }

  private navigateTo(identifier: string): void {
    const [type, uid] = identifier.split('-');
    const url = new URL(window.location.href);
    url.searchParams.delete('category');
    url.searchParams.delete('product');
    if (type === 'category' || type === 'product') {
      url.searchParams.set(type, uid);
    }
    window.location.href = url.toString();
  }

  private selectNode(node: TreeNode): void {
    if (node.type === 'article') {
      return;
    }
    this.state.setSelected(node.identifier);
    this.navigateTo(node.identifier);
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

  private async onToggleHidden(node: TreeNode): Promise<void> {
    const succeeded = await this.client.setHidden(node.type, node.uid, !node.hidden);
    if (succeeded) {
      await this.refreshParent(node.parentIdentifier);
    } else {
      this.notifyError();
    }
  }

  private async onDelete(node: TreeNode): Promise<void> {
    if (!window.confirm(label('delete_confirm', 'Are you sure you want to delete "%s"?').replace('%s', node.title))) {
      return;
    }
    const succeeded = await this.client.deleteRecord(node.type, node.uid);
    if (succeeded) {
      this.expanded.delete(node.identifier);
      this.childrenByParent.delete(node.identifier);
      await this.refreshParent(node.parentIdentifier);
    } else {
      this.notifyError();
    }
  }

  private async onCopy(node: TreeNode): Promise<void> {
    const parentUid = this.parentUidForCopy(node);
    const succeeded = await this.client.copyRecord(node.type, node.uid, parentUid);
    if (succeeded) {
      await this.refreshParent(node.parentIdentifier);
    } else {
      this.notifyError();
    }
  }

  private parentUidForCopy(node: TreeNode): number {
    if (node.parentIdentifier === 'root') {
      return 0;
    }
    const [, uid] = node.parentIdentifier.split('-');
    return parseInt(uid, 10);
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

  private onDragOver(event: DragEvent, target: TreeNode): void {
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

  private renderActions(node: TreeNode): TemplateResult | typeof nothing {
    if (node.type === 'article') {
      return nothing;
    }
    const toggleLabel = node.hidden ? label('enable', 'Enable') : label('disable', 'Disable');
    return html`
      <span class="d-flex gap-1 ms-auto">
        <button type="button" class="btn btn-borderless btn-sm p-0" title="${toggleLabel}" aria-label="${toggleLabel}"
          @click="${(event: Event) => { event.stopPropagation(); void this.onToggleHidden(node); }}">
          <typo3-backend-icon identifier="${node.hidden ? 'actions-eye' : 'actions-ban'}" size="small"></typo3-backend-icon>
        </button>
        <button type="button" class="btn btn-borderless btn-sm p-0" title="${label('copy', 'Copy')}" aria-label="${label('copy', 'Copy')}"
          @click="${(event: Event) => { event.stopPropagation(); void this.onCopy(node); }}">
          <typo3-backend-icon identifier="actions-copy" size="small"></typo3-backend-icon>
        </button>
        <button type="button" class="btn btn-borderless btn-sm p-0" title="${label('delete', 'Delete')}" aria-label="${label('delete', 'Delete')}"
          @click="${(event: Event) => { event.stopPropagation(); void this.onDelete(node); }}">
          <typo3-backend-icon identifier="actions-delete" size="small"></typo3-backend-icon>
        </button>
      </span>
    `;
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

  private renderNode(node: TreeNode): TemplateResult {
    const expanded = this.expanded.has(node.identifier);
    const children = this.childrenByParent.get(node.identifier) ?? [];
    return html`
      <li>
        <div
          class="${this.rowStateClasses(node)}"
          draggable="${node.type !== 'article'}"
          @dragstart="${(event: DragEvent) => this.onDragStart(event, node)}"
          @dragover="${(event: DragEvent) => this.onDragOver(event, node)}"
          @dragleave="${() => this.onDragLeave()}"
          @drop="${(event: DragEvent) => void this.onDrop(event, node)}"
        >
          ${this.renderToggle(node)}
          <typo3-backend-icon identifier="${node.icon}" size="small" state="${node.hidden ? 'disabled' : 'default'}"></typo3-backend-icon>
          <a
            href="#"
            class="flex-grow-1${node.hidden ? ' text-body-secondary fst-italic' : ''}"
            @click="${(event: Event) => { event.preventDefault(); this.selectNode(node); }}"
          >${node.title}</a>
          ${this.renderActions(node)}
        </div>
        ${expanded && children.length > 0
          ? html`<ul class="list-unstyled ps-4">${children.map((child) => this.renderNode(child))}</ul>`
          : nothing}
      </li>
    `;
  }

  render(): TemplateResult {
    return html`
      <goldene-zeiten-products-tree-toolbar
        new-category-url="${this.newCategoryUrl}"
        search-label="${label('search_placeholder', 'Search...')}"
        new-category-label="${label('new_category', 'New category')}"
        @tree-search="${(event: CustomEvent<string>) => this.onSearchInput(event)}"
      ></goldene-zeiten-products-tree-toolbar>
      ${this.searchActive && this.searchMatches.length === 0
        ? html`<p class="text-body-secondary">${label('no_results', 'No matches found.')}</p>`
        : nothing}
      <ul class="list-unstyled ps-0">${this.rootNodes.map((node) => this.renderNode(node))}</ul>
    `;
  }
}

customElements.define('goldene-zeiten-products-category-tree', ProductsCategoryTree);
