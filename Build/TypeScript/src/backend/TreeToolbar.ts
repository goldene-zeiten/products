import { LitElement, html, nothing } from 'lit';

export class ProductsTreeToolbar extends LitElement {
  static properties = {
    newCategoryUrl: { attribute: 'new-category-url' },
    searchLabel: { attribute: 'search-label' },
    newCategoryLabel: { attribute: 'new-category-label' },
  };

  declare newCategoryUrl: string;
  declare searchLabel: string;
  declare newCategoryLabel: string;

  constructor() {
    super();
    this.newCategoryUrl = '';
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

  render() {
    return html`
      <div class="d-flex gap-2 mb-2">
        <input
          type="search"
          class="form-control form-control-sm"
          placeholder="${this.searchLabel}"
          aria-label="${this.searchLabel}"
          @input="${this.onInput}"
        />
        ${this.newCategoryUrl
          ? html`<a class="btn btn-default btn-sm text-nowrap" href="${this.newCategoryUrl}">${this.newCategoryLabel}</a>`
          : nothing}
      </div>
    `;
  }
}

customElements.define('goldene-zeiten-products-tree-toolbar', ProductsTreeToolbar);
