import AjaxDataHandler from '@typo3/backend/ajax-data-handler.js';
import Notification from '@typo3/backend/notification.js';

const TOGGLE_SELECTOR = '[data-products-visibility-toggle]';
/**
 * Hide/show toggle for this module's own Fluid-rendered rows and its
 * article detail view. Deliberately not core's own record-list toggle
 * (data-datahandler-action="visibility"): that one's global, auto-delegated
 * click listener (@typo3/backend/ajax-data-handler.js) only dispatches a
 * refresh signal for the "pages" table (hardcoded), so reusing it verbatim
 * would silently leave the category tree stale for every other table.
 * Calling AjaxDataHandler.process() directly, with an explicit metadata
 * argument, makes it dispatch the same typo3:datahandler:process event the
 * tree already listens for (see CategoryTree.ts's onDataHandlerProcess) -
 * table/uid keep the same shape as the delete case, just a different action.
 */
class ProductVisibilityToggle {
    constructor() {
        document.addEventListener('click', (event) => void this.handleClick(event));
    }
    async handleClick(event) {
        const button = event.target.closest(TOGGLE_SELECTOR);
        if (!button) {
            return;
        }
        event.preventDefault();
        const { table, uid, field, hidden, errorMessage } = button.dataset;
        if (!table || !uid) {
            return;
        }
        const newValue = hidden === '1' ? '0' : '1';
        const response = await AjaxDataHandler.process({ data: { [table]: { [uid]: { [field ?? 'hidden']: newValue } } } }, { table, uid, action: 'update' });
        if (response.hasErrors) {
            Notification.error(errorMessage ?? 'Could not update visibility.');
            return;
        }
        window.location.reload();
    }
}
var ProductVisibilityToggle_default = new ProductVisibilityToggle();

export { ProductVisibilityToggle_default as default };
//# sourceMappingURL=product-visibility-toggle.js.map
