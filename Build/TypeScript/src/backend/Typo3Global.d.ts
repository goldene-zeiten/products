export {};

declare global {
  interface Typo3Global {
    settings: {
      ajaxUrls: Record<string, string>;
    };
    lang: Record<string, string>;
    Backend: {
      ContentContainer: {
        setUrl(url: string): void;
      };
    };
  }

  interface Window {
    TYPO3: Typo3Global;
  }
}
