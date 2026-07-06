import { LitElement, html } from 'lit';
import '@typo3/backend/element/icon-element.js';
import { NEW_CATEGORY_DATA_TRANSFER_TYPE } from './TreeTypes';

export class ProductsTreeToolbar extends LitElement {
  static properties = {
    searchLabel: { attribute: 'search-label' },
    newCategoryLabel: { attribute: 'new-category-label' },
  };

  declare searchLabel: string;
  declare newCategoryLabel: string;

  constructor() {
    super();
    this.searchLabel = 'Search...';
    this.newCategoryLabel = 'New category';
  }

  createRenderRoot(): this {
    return this;
  }

  private onInput(event: InputEvent): void {
    const query = (event.target as HTMLInputElement).value;
    this.dispatchEvent(new CustomEvent<string>('tree-search', { detail: query, bubbles: true, composed: true }));
  }

  private onNewCategoryDragStart(event: DragEvent): void {
    event.dataTransfer?.setData(NEW_CATEGORY_DATA_TRANSFER_TYPE, '1');
    if (event.dataTransfer) {
      event.dataTransfer.effectAllowed = 'copy';
    }
  }

  render() {
    return html`
      <div class="d-flex gap-2 mb-2 align-items-center">
        <input
          type="search"
          class="form-control form-control-sm"
          placeholder="${this.searchLabel}"
          aria-label="${this.searchLabel}"
          @input="${this.onInput}"
        />
        <div
          class="btn btn-default btn-sm text-nowrap"
          draggable="true"
          title="${this.newCategoryLabel}"
          aria-label="${this.newCategoryLabel}"
          @dragstart="${(event: DragEvent) => this.onNewCategoryDragStart(event)}"
        >
          <typo3-backend-icon identifier="products-category" size="small"></typo3-backend-icon>
        </div>
      </div>
    `;
  }
}

customElements.define('goldene-zeiten-products-tree-toolbar', ProductsTreeToolbar);
