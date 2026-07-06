import type { NodeType, SearchMatch, TreeNode } from './TreeTypes';

const TABLES: Record<NodeType, string> = {
  category: 'tx_products_domain_model_category',
  product: 'tx_products_domain_model_product',
  article: 'tx_products_domain_model_article',
};

function typo3(): Typo3Global {
  return (window.top as unknown as Window).TYPO3;
}

function ajaxUrl(routeIdentifier: string): string {
  return typo3().settings.ajaxUrls[routeIdentifier];
}

export function tableForType(type: NodeType): string {
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
export class CategoryTreeClient {
  async fetchNodes(parentIdentifier: string): Promise<TreeNode[]> {
    const url = new URL(ajaxUrl('products_category_tree_data'), window.location.href);
    url.searchParams.set('parent', parentIdentifier);
    return this.getJson<TreeNode[]>(url, []);
  }

  async filter(query: string): Promise<SearchMatch[]> {
    const url = new URL(ajaxUrl('products_category_tree_filter'), window.location.href);
    url.searchParams.set('query', query);
    return this.getJson<SearchMatch[]>(url, []);
  }

  async fetchRootline(identifier: string): Promise<string[]> {
    const url = new URL(ajaxUrl('products_category_tree_rootline'), window.location.href);
    url.searchParams.set('identifier', identifier);
    return this.getJson<string[]>(url, []);
  }

  async reorder(identifier: string, beforeIdentifier: string | null): Promise<boolean> {
    const body = new URLSearchParams();
    body.set('identifier', identifier);
    body.set('beforeIdentifier', beforeIdentifier ?? '');
    const response = await fetch(ajaxUrl('products_category_tree_reorder'), { method: 'POST', body });
    return response.ok;
  }

  async createCategory(title: string, parentCategoryUid: number): Promise<boolean> {
    const newId = `NEW${Math.floor(Math.random() * 1e9).toString(16)}`;
    const body = new URLSearchParams();
    body.set(`data[${TABLES.category}][${newId}][title]`, title);
    body.set(`data[${TABLES.category}][${newId}][parent_category]`, String(parentCategoryUid));
    return this.submitDataHandler(body);
  }

  async reparentCategory(uid: number, newParentUid: number): Promise<boolean> {
    const body = new URLSearchParams();
    body.set(`data[${TABLES.category}][${uid}][parent_category]`, String(newParentUid));
    return this.submitDataHandler(body);
  }

  async assignProductToCategory(uid: number, categoryUid: number): Promise<boolean> {
    const body = new URLSearchParams();
    body.set(`data[${TABLES.product}][${uid}][categories]`, String(categoryUid));
    return this.submitDataHandler(body);
  }

  private async submitDataHandler(body: URLSearchParams): Promise<boolean> {
    const response = await fetch(ajaxUrl('record_process'), { method: 'POST', body });
    if (!response.ok) {
      return false;
    }
    const result = (await response.json()) as { hasErrors: boolean };
    return !result.hasErrors;
  }

  private async getJson<T>(url: URL, fallback: T): Promise<T> {
    const response = await fetch(url);
    return response.ok ? ((await response.json()) as T) : fallback;
  }
}
