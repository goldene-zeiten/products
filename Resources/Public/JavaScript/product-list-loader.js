/**
 * Product List Loader
 * 
 * Progressively enhances the product list by loading it via AJAX if configured.
 */
export default class ProductListLoader {
    constructor() {
        this.init();
    }

    init() {
        document.querySelectorAll('[data-products-ajax-uri]').forEach(container => {
            this.load(container);
        });
    }

    async load(container) {
        const uri = container.getAttribute('data-products-ajax-uri');
        if (!uri) return;

        try {
            const response = await fetch(uri);
            if (!response.ok) throw new Error('Network response was not ok');
            
            const html = await response.text();
            container.innerHTML = html;
            container.removeAttribute('data-products-ajax-uri');
            
            // Dispatch event for other scripts to know the content was loaded
            container.dispatchEvent(new CustomEvent('products:list-loaded', { bubbles: true }));
        } catch (error) {
            console.error('Error loading product list via AJAX:', error);
        }
    }
}

// Auto-init
new ProductListLoader();
