const EXPANDED_STORAGE_KEY = 'products-category-tree-expanded';
const SELECTED_STORAGE_KEY = 'products-category-tree-selected';

/**
 * Persists expand state (survives browser restarts, like the page tree) and the last
 * selected node (survives a module reload within the same tab), mirroring core's own
 * split between localStorage and sessionStorage for the same two concerns.
 */
export class TreeState {
  getExpanded(): Set<string> {
    try {
      const raw = window.localStorage.getItem(EXPANDED_STORAGE_KEY);
      return new Set(raw ? (JSON.parse(raw) as string[]) : []);
    } catch {
      return new Set();
    }
  }

  setExpanded(expanded: Set<string>): void {
    try {
      window.localStorage.setItem(EXPANDED_STORAGE_KEY, JSON.stringify([...expanded]));
    } catch {
      // Storage unavailable (private browsing, quota) - expand state simply won't persist.
    }
  }

  getSelected(): string | null {
    try {
      return window.sessionStorage.getItem(SELECTED_STORAGE_KEY);
    } catch {
      return null;
    }
  }

  setSelected(identifier: string | null): void {
    try {
      if (identifier === null) {
        window.sessionStorage.removeItem(SELECTED_STORAGE_KEY);
      } else {
        window.sessionStorage.setItem(SELECTED_STORAGE_KEY, identifier);
      }
    } catch {
      // Storage unavailable - selection simply won't survive a reload.
    }
  }
}
