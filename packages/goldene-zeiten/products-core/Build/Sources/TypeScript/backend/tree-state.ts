const EXPANDED_STORAGE_KEY = 'products-category-tree-expanded';

/**
 * Persists expand state in localStorage (survives browser restarts, like the
 * page tree). Selection state is a separate concern handled by TYPO3 core's
 * own ModuleStateStorage (sessionStorage), since this element lives in the
 * persistent navigation slot and TYPO3.Backend.ContentContainer/
 * ModuleStateStorage are the mechanism core itself uses for that.
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
}
