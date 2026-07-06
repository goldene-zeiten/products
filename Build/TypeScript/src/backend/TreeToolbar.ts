import { LitElement, html } from 'lit';
import '@typo3/backend/element/icon-element.js';
import { NEW_CATEGORY_DATA_TRANSFER_TYPE, NEW_PRODUCT_DATA_TRANSFER_TYPE } from './TreeTypes';

export class ProductsTreeToolbar extends LitElement {
  static properties = {
    searchLabel: { attribute: 'search-label' },
    newCategoryLabel: { attribute: 'new-category-label' },
    newProductLabel: { attribute: 'new-product-label' },
  };

  declare searchLabel: string;
  declare newCategoryLabel: string;
  declare newProductLabel: string;

  constructor() {
    super();
    this.searchLabel = 'Search...';
    this.newCategoryLabel = 'New category';
    this.newProductLabel = 'New product';
  }

  createRenderRoot(): this {
    return this;
  }

  private onInput(event: InputEvent): void {
    const query = (event.target as HTMLInputElement).value;
    this.dispatchEvent(new CustomEvent<string>('tree-search', { detail: query, bubbles: true, composed: true }));
  }

  private onDragStart(event: DragEvent, dataTransferType: string): void {
    event.dataTransfer?.setData(dataTransferType, '1');
    if (event.dataTransfer) {
      event.dataTransfer.effectAllowed = 'copy';
    }
  }

  render() {
    return html`
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
            @dragstart="${(event: DragEvent) => this.onDragStart(event, NEW_CATEGORY_DATA_TRANSFER_TYPE)}"
          >
            <typo3-backend-icon identifier="products-category" size="small"></typo3-backend-icon>
          </div>
          <div
            class="tree-toolbar__menuitem tree-toolbar__drag-node"
            title="${this.newProductLabel}"
            draggable="true"
            aria-hidden="true"
            @dragstart="${(event: DragEvent) => this.onDragStart(event, NEW_PRODUCT_DATA_TRANSFER_TYPE)}"
          >
            <typo3-backend-icon identifier="products-product" size="small"></typo3-backend-icon>
          </div>
        </div>
      </div>
    `;
  }
}

customElements.define('goldene-zeiten-products-tree-toolbar', ProductsTreeToolbar);
