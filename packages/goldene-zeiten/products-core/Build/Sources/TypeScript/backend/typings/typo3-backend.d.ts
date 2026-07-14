// TYPO3's backend exposes these modules via its browser importmap (see
// EXT:backend Configuration/JavaScriptModules.php) rather than as npm
// packages, so `npm install` can't provide their types. These ambient
// declarations cover only the surface this extension actually consumes.

// Side-effect-only import: registers the <typo3-backend-icon> custom element.
declare module '@typo3/backend/element/icon-element.js';

declare module '@typo3/backend/storage/module-state-storage.js' {
  interface ModuleState {
    identifier: string;
    treeIdentifier: string | null;
  }

  export class ModuleStateStorage {
    static current(moduleType: string): ModuleState;
    static update(moduleType: string, identifier: string): ModuleState;
    static updateWithTreeIdentifier(moduleType: string, identifier: string, treeIdentifier: string): ModuleState;
  }
}

declare module '@typo3/backend/module.js' {
  interface ModuleInfo {
    link: string;
  }

  export class ModuleUtility {
    static getFromName(name: string): ModuleInfo | null;
  }
}

declare module '@typo3/backend/context-menu.js' {
  interface ContextMenuApi {
    show(
      table: string,
      uid: string,
      context: string,
      iconIdentifier: string,
      enDataParams: string,
      eventSource: HTMLElement,
      originalEvent: Event,
    ): void;
  }

  const contextMenu: ContextMenuApi;
  export default contextMenu;
}

declare module '@typo3/backend/ajax-data-handler.js' {
  interface DataHandlerResponse {
    hasErrors: boolean;
    messages: Array<{ title: string; message: string }>;
  }

  class AjaxDataHandler {
    process(payload: Record<string, unknown>, metadata?: Record<string, unknown>): Promise<DataHandlerResponse>;
  }

  const ajaxDataHandler: AjaxDataHandler;
  export default ajaxDataHandler;
}

declare module '@typo3/backend/notification.js' {
  interface NotificationApi {
    error(title: string, message?: string): void;
  }

  const notification: NotificationApi;
  export default notification;
}
