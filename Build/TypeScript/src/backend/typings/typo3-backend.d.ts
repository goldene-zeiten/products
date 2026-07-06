// TYPO3's backend exposes these modules via its browser importmap (see
// EXT:backend Configuration/JavaScriptModules.php) rather than as npm
// packages, so `npm install` can't provide their types. This is a
// side-effect-only import (it registers the <typo3-backend-icon> custom
// element), so an empty ambient module declaration is enough.
declare module '@typo3/backend/element/icon-element.js';
