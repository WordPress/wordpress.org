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
/*!*****************************************!*\
  !*** ./src/blocks/release-page/view.js ***!
  \*****************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/interactivity */ "@wordpress/interactivity");
/**
 * WordPress dependencies
 */

const {
  state
} = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('async-action-block', {
  state: {
    get btnText() {
      const {
        btnDefaultText,
        btnLoadingText
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      return state.isWaiting ? btnLoadingText : btnDefaultText;
    }
  },
  actions: {
    handlePreSubmit(event) {
      event.preventDefault();
      state.preSubmitting = true;
      const element = document.querySelector('.wp-block-wporg-release-page');
      if (element) {
        element.scrollIntoView({
          behavior: 'instant',
          block: 'center'
        });
      }
    },
    handleBackClick(event) {
      event.preventDefault();
      state.preSubmitting = false;
    },
    *handleSubmit(event) {
      // Prevent default form submission
      event.preventDefault();
      debugger;

      // Set waiting state
      state.isWaiting = true;
      state.errorMessage = '';
      const {
        pluginSlug,
        nonce,
        homeUrl
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      try {
        const response = yield fetch('/plugins/wp-json/plugins/v2/plugin/clapback/release', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce
          },
          body: JSON.stringify({
            plugin_slug: pluginSlug
          })
        });
        const data = yield response.json();

        // Update state based on response
        if (data.success) {} else {
          state.errorMessage = data.message || 'Action failed';
        }
      } catch (error) {
        state.errorMessage = 'Network error occurred';
      } finally {
        state.isWaiting = false;
      }
    }
  }
});
})();


//# sourceMappingURL=view.js.map