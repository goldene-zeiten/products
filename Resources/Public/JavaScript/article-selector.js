/**
 * Article Variant Selector
 *
 * Progressively enhances the variant-chooser form on a product detail page: without JavaScript,
 * changing a dropdown does nothing until the visible "Update" button is clicked, which reloads the
 * whole page (server resolves the article via ArticleVariantResolver either way - see
 * ProductController::showAction()). With JavaScript, a change auto-submits that same reload by
 * default, or - in "ajax" switch mode - fetches the same URL and swaps in just the price/stock
 * fragment instead of navigating, falling back to a real submit if the fetch itself fails.
 */
export default class ArticleSelector {
  constructor() {
    this.init();
  }

  init() {
    document.querySelectorAll('[data-product-variant-selector]').forEach((container) => this.bind(container));
  }

  bind(container) {
    const form = container.querySelector('form');
    const selects = container.querySelectorAll('.variant-attribute-select, .variant-article-select');
    if (!form || selects.length === 0) return;

    const ajaxMode = container.getAttribute('data-switch-mode') === 'ajax';
    selects.forEach((select) => {
      select.addEventListener('change', () => {
        if (ajaxMode) {
          this.loadFragmentViaAjax(form);
        } else {
          form.submit();
        }
      });
    });
  }

  async loadFragmentViaAjax(form) {
    const url = `${form.action}?${new URLSearchParams(new FormData(form)).toString()}`;
    try {
      const response = await fetch(url);
      if (!response.ok) throw new Error('Network response was not ok');
      const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
      const fragment = parsed.querySelector('[data-product-price-and-stock]');
      const target = document.querySelector('[data-product-price-and-stock]');
      if (!fragment || !target) throw new Error('Price/stock fragment not found in response');
      target.innerHTML = fragment.innerHTML;
      target.dispatchEvent(new CustomEvent('products:article-updated', { bubbles: true }));
    } catch (error) {
      console.error('Error loading article price/stock via AJAX:', error);
      form.submit();
    }
  }
}

// Auto-init
new ArticleSelector();
