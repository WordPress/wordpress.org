import * as __WEBPACK_EXTERNAL_MODULE__wordpress_interactivity_8e89b257__ from "@wordpress/interactivity";
/******/ var __webpack_modules__ = ({

/***/ "@wordpress/interactivity":
/*!*******************************************!*\
  !*** external "@wordpress/interactivity" ***!
  \*******************************************/
/***/ ((module) => {

var x = y => { var x = {}; __webpack_require__.d(x, y); return x; }
var y = x => () => x
module.exports = __WEBPACK_EXTERNAL_MODULE__wordpress_interactivity_8e89b257__;

/***/ })

/******/ });
/************************************************************************/
/******/ // The module cache
/******/ var __webpack_module_cache__ = {};
/******/ 
/******/ // The require function
/******/ function __webpack_require__(moduleId) {
/******/ 	// Check if module is in cache
/******/ 	var cachedModule = __webpack_module_cache__[moduleId];
/******/ 	if (cachedModule !== undefined) {
/******/ 		return cachedModule.exports;
/******/ 	}
/******/ 	// Create a new module (and put it into the cache)
/******/ 	var module = __webpack_module_cache__[moduleId] = {
/******/ 		// no module.id needed
/******/ 		// no module.loaded needed
/******/ 		exports: {}
/******/ 	};
/******/ 
/******/ 	// Execute the module function
/******/ 	__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 
/******/ 	// Return the exports of the module
/******/ 	return module.exports;
/******/ }
/******/ 
/************************************************************************/
/******/ /* webpack/runtime/make namespace object */
/******/ (() => {
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = (exports) => {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/ })();
/******/ 
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be isolated against other modules in the chunk.
(() => {
/*!********************************************!*\
  !*** ./src/blocks/release-publish/view.js ***!
  \********************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/interactivity */ "@wordpress/interactivity");
/**
 * WordPress dependencies
 */

const {
  state
} = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('wporg/publish-draft', {
  state: {
    get userHasConfirmed() {
      return state.hasConfirmed;
    },
    get isDefaultState() {
      return !state.isPublishing && !state.isPublished;
    },
    get isPublishingState() {
      return state.isPublishing;
    },
    get isPublishedState() {
      return state.isPublished;
    }
  },
  actions: {
    handleReleaseConfirm() {
      state.hasConfirmed = !state.hasConfirmed;
    },
    handleBackClick(event) {
      event.preventDefault();
      state.isCreatingRelease = false;

      // Make user reconfirm.
      state.hasConfirmed = false;
    },
    handlePageReload() {
      window.location.reload();
    },
    *handleSubmit(event) {
      event.preventDefault();
      const {
        pluginSlug,
        nonce,
        apiURL,
        genericErrorMessage,
        tooltipMessage
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();

      // Replicate form validation.
      if (!state.hasConfirmed) {
        const input = document.getElementById('confirm-release');
        input.setCustomValidity(tooltipMessage);
        input.reportValidity();
        return false;
      }
      state.isPublishing = true;
      state.errorMessage = '';
      state.hasError = false;
      try {
        const response = yield fetch(apiURL, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce
          },
          body: JSON.stringify({
            plugin_slug: pluginSlug
          })
        });
        if (!response.ok) {
          try {
            const error = yield response.json();
            throw new Error(error.message);
          } catch (error) {
            if (error instanceof SyntaxError) {
              // Handle cases where json is not returned, like a gateway timeout.
              throw new Error(genericErrorMessage);
            }
            throw error;
          }
        }
        state.isPublished = true;
      } catch (error) {
        state.errorMessage = error.message;
        state.hasError = true;
        state.isPublishing = false;
        state.hasConfirmed = false;
      } finally {
        state.isPublishing = false;
      }
    }
  }
});
})();


//# sourceMappingURL=view.js.map