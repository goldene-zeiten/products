/**
 * Article Variant Selector
 *
 * Progressively enhances the per-attribute dropdowns on a product detail page: resolves the
 * selected attribute-value combination to an article uid client-side via the page's own variant
 * map and writes it into the hidden "article" field before submit. Without JavaScript, the form
 * still submits the raw attribute-value selections, resolved server-side (ArticleVariantResolver).
 */
export default class ArticleSelector {
  constructor() {
    this.init();
  }

  init() {
    document.querySelectorAll('[data-product-variant-selector]').forEach((container) => {
      this.bind(container);
    });
  }

  bind(container) {
    const map = this.parseMap(container);
    const selects = container.querySelectorAll('.variant-attribute-select');
    const hiddenArticleField = container.querySelector('[data-selected-article]');
    if (!hiddenArticleField || selects.length === 0) return;

    const update = () => this.updateSelectedArticle(selects, map, hiddenArticleField);
    selects.forEach((select) => select.addEventListener('change', update));
    update();
  }

  parseMap(container) {
    try {
      return JSON.parse(container.getAttribute('data-variant-map') || '{}');
    } catch (error) {
      console.error('Invalid variant map', error);
      return {};
    }
  }

  updateSelectedArticle(selects, map, hiddenArticleField) {
    const key = Array.from(selects)
      .map((select) => parseInt(select.value, 10))
      .sort((a, b) => a - b)
      .join(',');
    hiddenArticleField.value = map[key] || '';
  }
}

// Auto-init
new ArticleSelector();
