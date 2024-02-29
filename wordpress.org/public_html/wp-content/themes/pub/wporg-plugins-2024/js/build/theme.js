/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ "./client/screenshots/image-gallery.js":
/*!*********************************************!*\
  !*** ./client/screenshots/image-gallery.js ***!
  \*********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ ImageGallery)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! prop-types */ "./node_modules/prop-types/index.js");
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(prop_types__WEBPACK_IMPORTED_MODULE_1__);


const MIN_INTERVAL = 500;
function throttle(func, wait) {
  let context, args, result;
  let timeout = null;
  let previous = 0;
  let later = function () {
    previous = new Date().getTime();
    timeout = null;
    result = func.apply(context, args);
    if (!timeout) {
      context = args = null;
    }
  };
  return function () {
    let now = new Date().getTime();
    let remaining = wait - (now - previous);
    context = this;
    args = arguments;
    if (remaining <= 0 || remaining > wait) {
      if (timeout) {
        clearTimeout(timeout);
        timeout = null;
      }
      previous = now;
      result = func.apply(context, args);
      if (!timeout) {
        context = args = null;
      }
    } else if (!timeout) {
      timeout = setTimeout(later, remaining);
    }
    return result;
  };
}

// This is to handle accessing event properties in an asynchronous way
// https://facebook.github.io/react/docs/events.html#syntheticevent
function debounceEventHandler(...args) {
  const throttled = throttle(...args);
  return function (event) {
    if (event) {
      event.persist();
      return throttled(event);
    }
    return throttled();
  };
}
class ImageGallery extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      currentIndex: props.startIndex,
      thumbsTranslateX: 0,
      offsetPercentage: 0,
      galleryWidth: 0,
      thumbnailWidth: 0
    };
  }
  componentWillReceiveProps(nextProps) {
    if (this.props.disableArrowKeys !== nextProps.disableArrowKeys) {
      if (nextProps.disableArrowKeys) {
        window.removeEventListener("keydown", this._handleKeyDown);
      } else {
        window.addEventListener("keydown", this._handleKeyDown);
      }
    }
  }
  componentDidUpdate(prevProps, prevState) {
    if (prevState.thumbnailWidth !== this.state.thumbnailWidth || prevProps.showThumbnails !== this.props.showThumbnails) {
      // Adjust thumbnail container when thumbnail width is adjusted.
      this._setThumbsTranslateX(-this._getThumbsTranslateX(this.state.currentIndex > 0 ? 1 : 0) * this.state.currentIndex);
    }
    if (prevState.currentIndex !== this.state.currentIndex) {
      this._updateThumbnailTranslateX(prevState);
    }
  }
  componentWillMount() {
    this._slideLeft = debounceEventHandler(this._slideLeft.bind(this), MIN_INTERVAL, true);
    this._slideRight = debounceEventHandler(this._slideRight.bind(this), MIN_INTERVAL, true);
    this._handleResize = this._handleResize.bind(this);
    this._handleKeyDown = this._handleKeyDown.bind(this);
    this._thumbnailDelay = 300;
  }
  componentDidMount() {
    // / Delay initial resize to get the accurate this._imageGallery.offsetWidth.
    window.setTimeout(() => this._handleResize(), 500);
    if (!this.props.disableArrowKeys) {
      window.addEventListener("keydown", this._handleKeyDown);
    }
    window.addEventListener("resize", this._handleResize);
  }
  componentWillUnmount() {
    if (!this.props.disableArrowKeys) {
      window.removeEventListener("keydown", this._handleKeyDown);
    }
    window.removeEventListener("resize", this._handleResize);
    if (this._intervalId) {
      window.clearInterval(this._intervalId);
      this._intervalId = null;
    }
  }
  fullScreen() {
    const gallery = this._imageGallery;
    if (gallery.requestFullscreen) {
      gallery.requestFullscreen();
    } else if (gallery.msRequestFullscreen) {
      gallery.msRequestFullscreen();
    } else if (gallery.mozRequestFullScreen) {
      gallery.mozRequestFullScreen();
    } else if (gallery.webkitRequestFullscreen) {
      gallery.webkitRequestFullscreen();
    }
  }
  slideToIndex(index, event) {
    if (event) {
      event.preventDefault();
    }
    let slideCount = this.props.items.length - 1;
    let currentIndex = index;
    if (index < 0) {
      currentIndex = slideCount;
    } else if (index > slideCount) {
      currentIndex = 0;
    }
    this.setState({
      previousIndex: this.state.currentIndex,
      currentIndex: currentIndex,
      offsetPercentage: 0,
      style: {
        transition: "transform 0.45s ease-out"
      }
    });
  }
  getCurrentIndex() {
    return this.state.currentIndex;
  }
  _handleResize() {
    if (this._imageGallery) {
      this.setState({
        galleryWidth: this._imageGallery.offsetWidth
      });
    }
    if (this._imageGalleryThumbnail) {
      this.setState({
        thumbnailWidth: this._imageGalleryThumbnail.offsetWidth
      });
    }
  }
  _handleKeyDown(event) {
    const LEFT_ARROW = 37;
    const RIGHT_ARROW = 39;
    const key = parseInt(event.keyCode || event.which || 0);
    switch (key) {
      case LEFT_ARROW:
        if (this._canSlideLeft() && !this._intervalId) {
          this._slideLeft();
        }
        break;
      case RIGHT_ARROW:
        if (this._canSlideRight() && !this._intervalId) {
          this._slideRight();
        }
        break;
    }
  }
  _handleMouseOverThumbnails(index) {
    if (this.props.slideOnThumbnailHover) {
      this.setState({
        hovering: true
      });
      if (this._thumbnailTimer) {
        window.clearTimeout(this._thumbnailTimer);
        this._thumbnailTimer = null;
      }
      this._thumbnailTimer = window.setTimeout(() => {
        this.slideToIndex(index);
      }, this._thumbnailDelay);
    }
  }
  _handleMouseLeaveThumbnails() {
    if (this._thumbnailTimer) {
      window.clearTimeout(this._thumbnailTimer);
      this._thumbnailTimer = null;
    }
    this.setState({
      hovering: false
    });
  }
  _handleMouseOver() {
    this.setState({
      hovering: true
    });
  }
  _handleMouseLeave() {
    this.setState({
      hovering: false
    });
  }
  _handleImageError(event) {
    if (this.props.defaultImage && -1 === event.target.src.indexOf(this.props.defaultImage)) {
      event.target.src = this.props.defaultImage;
    }
  }
  _canNavigate() {
    return this.props.items.length >= 2;
  }
  _canSlideLeft() {
    return this.props.infinite || this.state.currentIndex > 0;
  }
  _canSlideRight() {
    return this.props.infinite || this.state.currentIndex < this.props.items.length - 1;
  }
  _updateThumbnailTranslateX(prevState) {
    if (this.state.currentIndex === 0) {
      this._setThumbsTranslateX(0);
    } else {
      let indexDifference = Math.abs(prevState.currentIndex - this.state.currentIndex);
      let scrollX = this._getThumbsTranslateX(indexDifference);
      if (scrollX > 0) {
        if (prevState.currentIndex < this.state.currentIndex) {
          this._setThumbsTranslateX(this.state.thumbsTranslateX - scrollX);
        } else if (prevState.currentIndex > this.state.currentIndex) {
          this._setThumbsTranslateX(this.state.thumbsTranslateX + scrollX);
        }
      }
    }
  }
  _setThumbsTranslateX(thumbsTranslateX) {
    this.setState({
      thumbsTranslateX
    });
  }
  _getThumbsTranslateX(indexDifference) {
    if (this.props.disableThumbnailScroll) {
      return 0;
    }
    const {
      thumbnailWidth
    } = this.state;
    if (this._thumbnails) {
      if (this._thumbnails.scrollWidth <= thumbnailWidth) {
        return 0;
      }
      let totalThumbnails = this._thumbnails.children.length;
      // Total scroll-x required to see the last thumbnail.
      let totalScrollX = this._thumbnails.scrollWidth - thumbnailWidth;
      // Scroll-x required per index change.
      let perIndexScrollX = totalScrollX / (totalThumbnails - 1);
      return indexDifference * perIndexScrollX;
    }
  }
  _getAlignmentClassName(index) {
    // LEFT, and RIGHT alignments are necessary for lazyLoad.
    let {
      currentIndex
    } = this.state;
    let alignment = "";
    const LEFT = "left";
    const CENTER = "center";
    const RIGHT = "right";
    switch (index) {
      case currentIndex - 1:
        alignment = ` ${LEFT}`;
        break;
      case currentIndex:
        alignment = ` ${CENTER}`;
        break;
      case currentIndex + 1:
        alignment = ` ${RIGHT}`;
        break;
    }
    if (this.props.items.length >= 3 && this.props.infinite) {
      if (index === 0 && currentIndex === this.props.items.length - 1) {
        // Set first slide as right slide if were sliding right from last slide.
        alignment = ` ${RIGHT}`;
      } else if (index === this.props.items.length - 1 && currentIndex === 0) {
        // Set last slide as left slide if were sliding left from first slide.
        alignment = ` ${LEFT}`;
      }
    }
    return alignment;
  }
  _getTranslateXForTwoSlide(index) {
    // For taking care of infinite swipe when there are only two slides.
    const {
      currentIndex,
      offsetPercentage,
      previousIndex
    } = this.state;
    const baseTranslateX = -100 * currentIndex;
    let translateX = baseTranslateX + index * 100 + offsetPercentage;

    // Keep track of user swiping direction.
    if (offsetPercentage > 0) {
      this.direction = 'left';
    } else if (offsetPercentage < 0) {
      this.direction = 'right';
    }

    // when swiping make sure the slides are on the correct side
    if (currentIndex === 0 && index === 1 && offsetPercentage > 0) {
      translateX = -100 + offsetPercentage;
    } else if (currentIndex === 1 && index === 0 && offsetPercentage < 0) {
      translateX = 100 + offsetPercentage;
    }
    if (currentIndex !== previousIndex) {
      // When swiped move the slide to the correct side.
      if (0 === previousIndex && 0 === index && 0 === offsetPercentage && 'left' === this.direction) {
        translateX = 100;
      } else if (1 === previousIndex && 1 === index && 0 === offsetPercentage && 'right' === this.direction) {
        translateX = -100;
      }
    } else {
      // Keep the slide on the correct slide even when not a swipe.
      if (0 === currentIndex && 1 === index && 0 === offsetPercentage && 'left' === this.direction) {
        translateX = -100;
      } else if (1 === currentIndex && 0 === index && 0 === offsetPercentage && 'right' === this.direction) {
        translateX = 100;
      }
    }
    return translateX;
  }
  _getSlideStyle(index) {
    const {
      currentIndex,
      offsetPercentage
    } = this.state;
    const {
      infinite,
      items
    } = this.props;
    const baseTranslateX = -100 * currentIndex;
    const totalSlides = items.length - 1;

    // calculates where the other slides belong based on currentIndex
    let translateX = baseTranslateX + index * 100 + offsetPercentage;

    // adjust zIndex so that only the current slide and the slide were going
    // to is at the top layer, this prevents transitions from flying in the
    // background when swiping before the first slide or beyond the last slide
    let zIndex = 1;
    if (index === currentIndex) {
      zIndex = 3;
    } else if (index === this.state.previousIndex) {
      zIndex = 2;
    }
    if (infinite && items.length > 2) {
      if (currentIndex === 0 && index === totalSlides) {
        // make the last slide the slide before the first
        translateX = -100 + offsetPercentage;
      } else if (currentIndex === totalSlides && index === 0) {
        // make the first slide the slide after the last
        translateX = 100 + offsetPercentage;
      }
    }

    // Special case when there are only 2 items with infinite on
    if (infinite && items.length === 2) {
      translateX = this._getTranslateXForTwoSlide(index);
    }
    const translate3d = `translate3d(${translateX}%, 0, 0)`;
    return {
      WebkitTransform: translate3d,
      MozTransform: translate3d,
      msTransform: translate3d,
      OTransform: translate3d,
      transform: translate3d,
      zIndex: zIndex
    };
  }
  _getThumbnailStyle() {
    const translate3d = `translate3d(${this.state.thumbsTranslateX}px, 0, 0)`;
    return {
      WebkitTransform: translate3d,
      MozTransform: translate3d,
      msTransform: translate3d,
      OTransform: translate3d,
      transform: translate3d
    };
  }
  _slideLeft(event) {
    this.slideToIndex(this.state.currentIndex - 1, event);
  }
  _slideRight(event) {
    this.slideToIndex(this.state.currentIndex + 1, event);
  }
  _renderItem(item) {
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("figure", {
      className: "image-gallery-image"
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
      href: item.original
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("img", {
      src: item.original,
      alt: item.originalAlt,
      srcSet: item.srcSet,
      sizes: item.sizes,
      onLoad: this.props.onImageLoad,
      onError: this._handleImageError.bind(this)
    })), item.description && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("figcaption", {
      className: "image-gallery-description"
    }, item.description));
  }
  render() {
    const {
      currentIndex
    } = this.state;
    const thumbnailStyle = this._getThumbnailStyle();
    const slideLeft = this._slideLeft.bind(this);
    const slideRight = this._slideRight.bind(this);
    let slides = [];
    let thumbnails = [];
    this.props.items.map((item, index) => {
      const alignment = this._getAlignmentClassName(index);
      const originalClass = item.originalClass ? ` ${item.originalClass}` : '';
      const thumbnailClass = item.thumbnailClass ? ` ${item.thumbnailClass}` : '';
      const renderItem = item.renderItem || this.props.renderItem || this._renderItem.bind(this);
      const slide = (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
        key: index,
        className: 'image-gallery-slide' + alignment + originalClass,
        style: Object.assign(this._getSlideStyle(index), this.state.style),
        onClick: this.props.onClick
      }, renderItem(item));
      if (this.props.lazyLoad) {
        if (alignment) {
          slides.push(slide);
        }
      } else {
        slides.push(slide);
      }
      thumbnails.push((0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("button", {
        type: "button",
        onMouseOver: this._handleMouseOverThumbnails.bind(this, index),
        onMouseLeave: this._handleMouseLeaveThumbnails.bind(this, index),
        key: index,
        className: 'button-link image-gallery-thumbnail' + (currentIndex === index ? ' active' : '') + thumbnailClass,
        onTouchStart: event => this.slideToIndex.call(this, index, event),
        onClick: event => this.slideToIndex.call(this, index, event)
      }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("img", {
        src: item.thumbnail,
        alt: item.thumbnailAlt,
        onError: this._handleImageError.bind(this)
      }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
        className: "image-gallery-thumbnail-label"
      }, item.thumbnailLabel)));
    });
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("section", {
      ref: i => this._imageGallery = i,
      className: "image-gallery"
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      onMouseOver: this._handleMouseOver.bind(this),
      onMouseLeave: this._handleMouseLeave.bind(this),
      className: "image-gallery-content"
    }, this._canNavigate() ? [this.props.showNav && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
      key: "navigation"
    }, this._canSlideLeft() && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("button", {
      type: "button",
      className: "button-link image-gallery-left-nav",
      onClick: slideLeft
    }), this._canSlideRight() && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("button", {
      type: "button",
      className: "button-link image-gallery-right-nav",
      onClick: slideRight
    })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      key: this.state.currentIndex,
      className: "image-gallery-slides"
    }, slides)] : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      className: "image-gallery-slides"
    }, slides), this.props.showIndex && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      className: "image-gallery-index"
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
      className: "image-gallery-index-current"
    }, this.state.currentIndex + 1), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
      className: "image-gallery-index-separator"
    }, this.props.indexSeparator), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
      className: "image-gallery-index-total"
    }, this.props.items.length))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      className: "image-gallery-thumbnails",
      ref: i => this._imageGalleryThumbnail = i
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      ref: t => this._thumbnails = t,
      className: "image-gallery-thumbnails-container",
      style: thumbnailStyle
    }, thumbnails)));
  }
}
ImageGallery.propTypes = {
  items: (prop_types__WEBPACK_IMPORTED_MODULE_1___default().array.isRequired),
  showNav: (prop_types__WEBPACK_IMPORTED_MODULE_1___default().bool),
  lazyLoad: (prop_types__WEBPACK_IMPORTED_MODULE_1___default().bool),
  infinite: (prop_types__WEBPACK_IMPORTED_MODULE_1___default().bool),
  showIndex: (prop_types__WEBPACK_IMPORTED_MODULE_1___default().bool),
  showThumbnails: (prop_types__WEBPACK_IMPORTED_MODULE_1___default().bool),
  slideOnThumbnailHover: (prop_types__WEBPACK_IMPORTED_MODULE_1___default().bool),
  disableThumbnailScroll: (prop_types__WEBPACK_IMPORTED_MODULE_1___default().bool),
  disableArrowKeys: (prop_types__WEBPACK_IMPORTED_MODULE_1___default().bool),
  defaultImage: (prop_types__WEBPACK_IMPORTED_MODULE_1___default().string),
  indexSeparator: (prop_types__WEBPACK_IMPORTED_MODULE_1___default().string),
  startIndex: (prop_types__WEBPACK_IMPORTED_MODULE_1___default().number),
  slideInterval: (prop_types__WEBPACK_IMPORTED_MODULE_1___default().number),
  onClick: (prop_types__WEBPACK_IMPORTED_MODULE_1___default().func),
  onImageLoad: (prop_types__WEBPACK_IMPORTED_MODULE_1___default().func),
  onImageError: (prop_types__WEBPACK_IMPORTED_MODULE_1___default().func),
  renderItem: (prop_types__WEBPACK_IMPORTED_MODULE_1___default().func)
};
ImageGallery.defaultProps = {
  items: [],
  showNav: true,
  lazyLoad: false,
  infinite: true,
  showIndex: false,
  showThumbnails: true,
  slideOnThumbnailHover: false,
  disableThumbnailScroll: false,
  disableArrowKeys: false,
  indexSeparator: " / ",
  startIndex: 0,
  slideInterval: 3000
};

/***/ }),

/***/ "./client/screenshots/index.js":
/*!*************************************!*\
  !*** ./client/screenshots/index.js ***!
  \*************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "Screenshots": () => (/* binding */ Screenshots),
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _image_gallery__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./image-gallery */ "./client/screenshots/image-gallery.js");

/* global localeData */
/**
 * Internal dependencies.
 */

const Screenshots = ({
  screenshots = []
}) => {
  if (!screenshots) {
    return null;
  }
  const items = screenshots.map(({
    caption,
    src
  }) => ({
    original: src,
    originalAlt: '',
    thumbnail: src,
    thumbnailAlt: caption || '',
    description: caption || false
  }));
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "screenshots",
    className: "plugin-screenshots"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("h2", null, localeData.screenshots), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_image_gallery__WEBPACK_IMPORTED_MODULE_1__["default"], {
    items: items
  }));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Screenshots);

/***/ }),

/***/ "./node_modules/object-assign/index.js":
/*!*********************************************!*\
  !*** ./node_modules/object-assign/index.js ***!
  \*********************************************/
/***/ ((module) => {

"use strict";
/*
object-assign
(c) Sindre Sorhus
@license MIT
*/


/* eslint-disable no-unused-vars */
var getOwnPropertySymbols = Object.getOwnPropertySymbols;
var hasOwnProperty = Object.prototype.hasOwnProperty;
var propIsEnumerable = Object.prototype.propertyIsEnumerable;

function toObject(val) {
	if (val === null || val === undefined) {
		throw new TypeError('Object.assign cannot be called with null or undefined');
	}

	return Object(val);
}

function shouldUseNative() {
	try {
		if (!Object.assign) {
			return false;
		}

		// Detect buggy property enumeration order in older V8 versions.

		// https://bugs.chromium.org/p/v8/issues/detail?id=4118
		var test1 = new String('abc');  // eslint-disable-line no-new-wrappers
		test1[5] = 'de';
		if (Object.getOwnPropertyNames(test1)[0] === '5') {
			return false;
		}

		// https://bugs.chromium.org/p/v8/issues/detail?id=3056
		var test2 = {};
		for (var i = 0; i < 10; i++) {
			test2['_' + String.fromCharCode(i)] = i;
		}
		var order2 = Object.getOwnPropertyNames(test2).map(function (n) {
			return test2[n];
		});
		if (order2.join('') !== '0123456789') {
			return false;
		}

		// https://bugs.chromium.org/p/v8/issues/detail?id=3056
		var test3 = {};
		'abcdefghijklmnopqrst'.split('').forEach(function (letter) {
			test3[letter] = letter;
		});
		if (Object.keys(Object.assign({}, test3)).join('') !==
				'abcdefghijklmnopqrst') {
			return false;
		}

		return true;
	} catch (err) {
		// We don't expect any of the above to throw, but better to be safe.
		return false;
	}
}

module.exports = shouldUseNative() ? Object.assign : function (target, source) {
	var from;
	var to = toObject(target);
	var symbols;

	for (var s = 1; s < arguments.length; s++) {
		from = Object(arguments[s]);

		for (var key in from) {
			if (hasOwnProperty.call(from, key)) {
				to[key] = from[key];
			}
		}

		if (getOwnPropertySymbols) {
			symbols = getOwnPropertySymbols(from);
			for (var i = 0; i < symbols.length; i++) {
				if (propIsEnumerable.call(from, symbols[i])) {
					to[symbols[i]] = from[symbols[i]];
				}
			}
		}
	}

	return to;
};


/***/ }),

/***/ "./node_modules/prop-types/checkPropTypes.js":
/*!***************************************************!*\
  !*** ./node_modules/prop-types/checkPropTypes.js ***!
  \***************************************************/
/***/ ((module, __unused_webpack_exports, __webpack_require__) => {

"use strict";
/**
 * Copyright (c) 2013-present, Facebook, Inc.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */



var printWarning = function() {};

if (true) {
  var ReactPropTypesSecret = __webpack_require__(/*! ./lib/ReactPropTypesSecret */ "./node_modules/prop-types/lib/ReactPropTypesSecret.js");
  var loggedTypeFailures = {};
  var has = Function.call.bind(Object.prototype.hasOwnProperty);

  printWarning = function(text) {
    var message = 'Warning: ' + text;
    if (typeof console !== 'undefined') {
      console.error(message);
    }
    try {
      // --- Welcome to debugging React ---
      // This error was thrown as a convenience so that you can use this stack
      // to find the callsite that caused this warning to fire.
      throw new Error(message);
    } catch (x) {}
  };
}

/**
 * Assert that the values match with the type specs.
 * Error messages are memorized and will only be shown once.
 *
 * @param {object} typeSpecs Map of name to a ReactPropType
 * @param {object} values Runtime values that need to be type-checked
 * @param {string} location e.g. "prop", "context", "child context"
 * @param {string} componentName Name of the component for error messages.
 * @param {?Function} getStack Returns the component stack.
 * @private
 */
function checkPropTypes(typeSpecs, values, location, componentName, getStack) {
  if (true) {
    for (var typeSpecName in typeSpecs) {
      if (has(typeSpecs, typeSpecName)) {
        var error;
        // Prop type validation may throw. In case they do, we don't want to
        // fail the render phase where it didn't fail before. So we log it.
        // After these have been cleaned up, we'll let them throw.
        try {
          // This is intentionally an invariant that gets caught. It's the same
          // behavior as without this statement except with a better message.
          if (typeof typeSpecs[typeSpecName] !== 'function') {
            var err = Error(
              (componentName || 'React class') + ': ' + location + ' type `' + typeSpecName + '` is invalid; ' +
              'it must be a function, usually from the `prop-types` package, but received `' + typeof typeSpecs[typeSpecName] + '`.'
            );
            err.name = 'Invariant Violation';
            throw err;
          }
          error = typeSpecs[typeSpecName](values, typeSpecName, componentName, location, null, ReactPropTypesSecret);
        } catch (ex) {
          error = ex;
        }
        if (error && !(error instanceof Error)) {
          printWarning(
            (componentName || 'React class') + ': type specification of ' +
            location + ' `' + typeSpecName + '` is invalid; the type checker ' +
            'function must return `null` or an `Error` but returned a ' + typeof error + '. ' +
            'You may have forgotten to pass an argument to the type checker ' +
            'creator (arrayOf, instanceOf, objectOf, oneOf, oneOfType, and ' +
            'shape all require an argument).'
          );
        }
        if (error instanceof Error && !(error.message in loggedTypeFailures)) {
          // Only monitor this failure once because there tends to be a lot of the
          // same error.
          loggedTypeFailures[error.message] = true;

          var stack = getStack ? getStack() : '';

          printWarning(
            'Failed ' + location + ' type: ' + error.message + (stack != null ? stack : '')
          );
        }
      }
    }
  }
}

/**
 * Resets warning cache when testing.
 *
 * @private
 */
checkPropTypes.resetWarningCache = function() {
  if (true) {
    loggedTypeFailures = {};
  }
}

module.exports = checkPropTypes;


/***/ }),

/***/ "./node_modules/prop-types/factoryWithTypeCheckers.js":
/*!************************************************************!*\
  !*** ./node_modules/prop-types/factoryWithTypeCheckers.js ***!
  \************************************************************/
/***/ ((module, __unused_webpack_exports, __webpack_require__) => {

"use strict";
/**
 * Copyright (c) 2013-present, Facebook, Inc.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */



var ReactIs = __webpack_require__(/*! react-is */ "./node_modules/prop-types/node_modules/react-is/index.js");
var assign = __webpack_require__(/*! object-assign */ "./node_modules/object-assign/index.js");

var ReactPropTypesSecret = __webpack_require__(/*! ./lib/ReactPropTypesSecret */ "./node_modules/prop-types/lib/ReactPropTypesSecret.js");
var checkPropTypes = __webpack_require__(/*! ./checkPropTypes */ "./node_modules/prop-types/checkPropTypes.js");

var has = Function.call.bind(Object.prototype.hasOwnProperty);
var printWarning = function() {};

if (true) {
  printWarning = function(text) {
    var message = 'Warning: ' + text;
    if (typeof console !== 'undefined') {
      console.error(message);
    }
    try {
      // --- Welcome to debugging React ---
      // This error was thrown as a convenience so that you can use this stack
      // to find the callsite that caused this warning to fire.
      throw new Error(message);
    } catch (x) {}
  };
}

function emptyFunctionThatReturnsNull() {
  return null;
}

module.exports = function(isValidElement, throwOnDirectAccess) {
  /* global Symbol */
  var ITERATOR_SYMBOL = typeof Symbol === 'function' && Symbol.iterator;
  var FAUX_ITERATOR_SYMBOL = '@@iterator'; // Before Symbol spec.

  /**
   * Returns the iterator method function contained on the iterable object.
   *
   * Be sure to invoke the function with the iterable as context:
   *
   *     var iteratorFn = getIteratorFn(myIterable);
   *     if (iteratorFn) {
   *       var iterator = iteratorFn.call(myIterable);
   *       ...
   *     }
   *
   * @param {?object} maybeIterable
   * @return {?function}
   */
  function getIteratorFn(maybeIterable) {
    var iteratorFn = maybeIterable && (ITERATOR_SYMBOL && maybeIterable[ITERATOR_SYMBOL] || maybeIterable[FAUX_ITERATOR_SYMBOL]);
    if (typeof iteratorFn === 'function') {
      return iteratorFn;
    }
  }

  /**
   * Collection of methods that allow declaration and validation of props that are
   * supplied to React components. Example usage:
   *
   *   var Props = require('ReactPropTypes');
   *   var MyArticle = React.createClass({
   *     propTypes: {
   *       // An optional string prop named "description".
   *       description: Props.string,
   *
   *       // A required enum prop named "category".
   *       category: Props.oneOf(['News','Photos']).isRequired,
   *
   *       // A prop named "dialog" that requires an instance of Dialog.
   *       dialog: Props.instanceOf(Dialog).isRequired
   *     },
   *     render: function() { ... }
   *   });
   *
   * A more formal specification of how these methods are used:
   *
   *   type := array|bool|func|object|number|string|oneOf([...])|instanceOf(...)
   *   decl := ReactPropTypes.{type}(.isRequired)?
   *
   * Each and every declaration produces a function with the same signature. This
   * allows the creation of custom validation functions. For example:
   *
   *  var MyLink = React.createClass({
   *    propTypes: {
   *      // An optional string or URI prop named "href".
   *      href: function(props, propName, componentName) {
   *        var propValue = props[propName];
   *        if (propValue != null && typeof propValue !== 'string' &&
   *            !(propValue instanceof URI)) {
   *          return new Error(
   *            'Expected a string or an URI for ' + propName + ' in ' +
   *            componentName
   *          );
   *        }
   *      }
   *    },
   *    render: function() {...}
   *  });
   *
   * @internal
   */

  var ANONYMOUS = '<<anonymous>>';

  // Important!
  // Keep this list in sync with production version in `./factoryWithThrowingShims.js`.
  var ReactPropTypes = {
    array: createPrimitiveTypeChecker('array'),
    bool: createPrimitiveTypeChecker('boolean'),
    func: createPrimitiveTypeChecker('function'),
    number: createPrimitiveTypeChecker('number'),
    object: createPrimitiveTypeChecker('object'),
    string: createPrimitiveTypeChecker('string'),
    symbol: createPrimitiveTypeChecker('symbol'),

    any: createAnyTypeChecker(),
    arrayOf: createArrayOfTypeChecker,
    element: createElementTypeChecker(),
    elementType: createElementTypeTypeChecker(),
    instanceOf: createInstanceTypeChecker,
    node: createNodeChecker(),
    objectOf: createObjectOfTypeChecker,
    oneOf: createEnumTypeChecker,
    oneOfType: createUnionTypeChecker,
    shape: createShapeTypeChecker,
    exact: createStrictShapeTypeChecker,
  };

  /**
   * inlined Object.is polyfill to avoid requiring consumers ship their own
   * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Object/is
   */
  /*eslint-disable no-self-compare*/
  function is(x, y) {
    // SameValue algorithm
    if (x === y) {
      // Steps 1-5, 7-10
      // Steps 6.b-6.e: +0 != -0
      return x !== 0 || 1 / x === 1 / y;
    } else {
      // Step 6.a: NaN == NaN
      return x !== x && y !== y;
    }
  }
  /*eslint-enable no-self-compare*/

  /**
   * We use an Error-like object for backward compatibility as people may call
   * PropTypes directly and inspect their output. However, we don't use real
   * Errors anymore. We don't inspect their stack anyway, and creating them
   * is prohibitively expensive if they are created too often, such as what
   * happens in oneOfType() for any type before the one that matched.
   */
  function PropTypeError(message) {
    this.message = message;
    this.stack = '';
  }
  // Make `instanceof Error` still work for returned errors.
  PropTypeError.prototype = Error.prototype;

  function createChainableTypeChecker(validate) {
    if (true) {
      var manualPropTypeCallCache = {};
      var manualPropTypeWarningCount = 0;
    }
    function checkType(isRequired, props, propName, componentName, location, propFullName, secret) {
      componentName = componentName || ANONYMOUS;
      propFullName = propFullName || propName;

      if (secret !== ReactPropTypesSecret) {
        if (throwOnDirectAccess) {
          // New behavior only for users of `prop-types` package
          var err = new Error(
            'Calling PropTypes validators directly is not supported by the `prop-types` package. ' +
            'Use `PropTypes.checkPropTypes()` to call them. ' +
            'Read more at http://fb.me/use-check-prop-types'
          );
          err.name = 'Invariant Violation';
          throw err;
        } else if ( true && typeof console !== 'undefined') {
          // Old behavior for people using React.PropTypes
          var cacheKey = componentName + ':' + propName;
          if (
            !manualPropTypeCallCache[cacheKey] &&
            // Avoid spamming the console because they are often not actionable except for lib authors
            manualPropTypeWarningCount < 3
          ) {
            printWarning(
              'You are manually calling a React.PropTypes validation ' +
              'function for the `' + propFullName + '` prop on `' + componentName  + '`. This is deprecated ' +
              'and will throw in the standalone `prop-types` package. ' +
              'You may be seeing this warning due to a third-party PropTypes ' +
              'library. See https://fb.me/react-warning-dont-call-proptypes ' + 'for details.'
            );
            manualPropTypeCallCache[cacheKey] = true;
            manualPropTypeWarningCount++;
          }
        }
      }
      if (props[propName] == null) {
        if (isRequired) {
          if (props[propName] === null) {
            return new PropTypeError('The ' + location + ' `' + propFullName + '` is marked as required ' + ('in `' + componentName + '`, but its value is `null`.'));
          }
          return new PropTypeError('The ' + location + ' `' + propFullName + '` is marked as required in ' + ('`' + componentName + '`, but its value is `undefined`.'));
        }
        return null;
      } else {
        return validate(props, propName, componentName, location, propFullName);
      }
    }

    var chainedCheckType = checkType.bind(null, false);
    chainedCheckType.isRequired = checkType.bind(null, true);

    return chainedCheckType;
  }

  function createPrimitiveTypeChecker(expectedType) {
    function validate(props, propName, componentName, location, propFullName, secret) {
      var propValue = props[propName];
      var propType = getPropType(propValue);
      if (propType !== expectedType) {
        // `propValue` being instance of, say, date/regexp, pass the 'object'
        // check, but we can offer a more precise error message here rather than
        // 'of type `object`'.
        var preciseType = getPreciseType(propValue);

        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of type ' + ('`' + preciseType + '` supplied to `' + componentName + '`, expected ') + ('`' + expectedType + '`.'));
      }
      return null;
    }
    return createChainableTypeChecker(validate);
  }

  function createAnyTypeChecker() {
    return createChainableTypeChecker(emptyFunctionThatReturnsNull);
  }

  function createArrayOfTypeChecker(typeChecker) {
    function validate(props, propName, componentName, location, propFullName) {
      if (typeof typeChecker !== 'function') {
        return new PropTypeError('Property `' + propFullName + '` of component `' + componentName + '` has invalid PropType notation inside arrayOf.');
      }
      var propValue = props[propName];
      if (!Array.isArray(propValue)) {
        var propType = getPropType(propValue);
        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of type ' + ('`' + propType + '` supplied to `' + componentName + '`, expected an array.'));
      }
      for (var i = 0; i < propValue.length; i++) {
        var error = typeChecker(propValue, i, componentName, location, propFullName + '[' + i + ']', ReactPropTypesSecret);
        if (error instanceof Error) {
          return error;
        }
      }
      return null;
    }
    return createChainableTypeChecker(validate);
  }

  function createElementTypeChecker() {
    function validate(props, propName, componentName, location, propFullName) {
      var propValue = props[propName];
      if (!isValidElement(propValue)) {
        var propType = getPropType(propValue);
        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of type ' + ('`' + propType + '` supplied to `' + componentName + '`, expected a single ReactElement.'));
      }
      return null;
    }
    return createChainableTypeChecker(validate);
  }

  function createElementTypeTypeChecker() {
    function validate(props, propName, componentName, location, propFullName) {
      var propValue = props[propName];
      if (!ReactIs.isValidElementType(propValue)) {
        var propType = getPropType(propValue);
        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of type ' + ('`' + propType + '` supplied to `' + componentName + '`, expected a single ReactElement type.'));
      }
      return null;
    }
    return createChainableTypeChecker(validate);
  }

  function createInstanceTypeChecker(expectedClass) {
    function validate(props, propName, componentName, location, propFullName) {
      if (!(props[propName] instanceof expectedClass)) {
        var expectedClassName = expectedClass.name || ANONYMOUS;
        var actualClassName = getClassName(props[propName]);
        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of type ' + ('`' + actualClassName + '` supplied to `' + componentName + '`, expected ') + ('instance of `' + expectedClassName + '`.'));
      }
      return null;
    }
    return createChainableTypeChecker(validate);
  }

  function createEnumTypeChecker(expectedValues) {
    if (!Array.isArray(expectedValues)) {
      if (true) {
        if (arguments.length > 1) {
          printWarning(
            'Invalid arguments supplied to oneOf, expected an array, got ' + arguments.length + ' arguments. ' +
            'A common mistake is to write oneOf(x, y, z) instead of oneOf([x, y, z]).'
          );
        } else {
          printWarning('Invalid argument supplied to oneOf, expected an array.');
        }
      }
      return emptyFunctionThatReturnsNull;
    }

    function validate(props, propName, componentName, location, propFullName) {
      var propValue = props[propName];
      for (var i = 0; i < expectedValues.length; i++) {
        if (is(propValue, expectedValues[i])) {
          return null;
        }
      }

      var valuesString = JSON.stringify(expectedValues, function replacer(key, value) {
        var type = getPreciseType(value);
        if (type === 'symbol') {
          return String(value);
        }
        return value;
      });
      return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of value `' + String(propValue) + '` ' + ('supplied to `' + componentName + '`, expected one of ' + valuesString + '.'));
    }
    return createChainableTypeChecker(validate);
  }

  function createObjectOfTypeChecker(typeChecker) {
    function validate(props, propName, componentName, location, propFullName) {
      if (typeof typeChecker !== 'function') {
        return new PropTypeError('Property `' + propFullName + '` of component `' + componentName + '` has invalid PropType notation inside objectOf.');
      }
      var propValue = props[propName];
      var propType = getPropType(propValue);
      if (propType !== 'object') {
        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of type ' + ('`' + propType + '` supplied to `' + componentName + '`, expected an object.'));
      }
      for (var key in propValue) {
        if (has(propValue, key)) {
          var error = typeChecker(propValue, key, componentName, location, propFullName + '.' + key, ReactPropTypesSecret);
          if (error instanceof Error) {
            return error;
          }
        }
      }
      return null;
    }
    return createChainableTypeChecker(validate);
  }

  function createUnionTypeChecker(arrayOfTypeCheckers) {
    if (!Array.isArray(arrayOfTypeCheckers)) {
       true ? printWarning('Invalid argument supplied to oneOfType, expected an instance of array.') : 0;
      return emptyFunctionThatReturnsNull;
    }

    for (var i = 0; i < arrayOfTypeCheckers.length; i++) {
      var checker = arrayOfTypeCheckers[i];
      if (typeof checker !== 'function') {
        printWarning(
          'Invalid argument supplied to oneOfType. Expected an array of check functions, but ' +
          'received ' + getPostfixForTypeWarning(checker) + ' at index ' + i + '.'
        );
        return emptyFunctionThatReturnsNull;
      }
    }

    function validate(props, propName, componentName, location, propFullName) {
      for (var i = 0; i < arrayOfTypeCheckers.length; i++) {
        var checker = arrayOfTypeCheckers[i];
        if (checker(props, propName, componentName, location, propFullName, ReactPropTypesSecret) == null) {
          return null;
        }
      }

      return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` supplied to ' + ('`' + componentName + '`.'));
    }
    return createChainableTypeChecker(validate);
  }

  function createNodeChecker() {
    function validate(props, propName, componentName, location, propFullName) {
      if (!isNode(props[propName])) {
        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` supplied to ' + ('`' + componentName + '`, expected a ReactNode.'));
      }
      return null;
    }
    return createChainableTypeChecker(validate);
  }

  function createShapeTypeChecker(shapeTypes) {
    function validate(props, propName, componentName, location, propFullName) {
      var propValue = props[propName];
      var propType = getPropType(propValue);
      if (propType !== 'object') {
        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of type `' + propType + '` ' + ('supplied to `' + componentName + '`, expected `object`.'));
      }
      for (var key in shapeTypes) {
        var checker = shapeTypes[key];
        if (!checker) {
          continue;
        }
        var error = checker(propValue, key, componentName, location, propFullName + '.' + key, ReactPropTypesSecret);
        if (error) {
          return error;
        }
      }
      return null;
    }
    return createChainableTypeChecker(validate);
  }

  function createStrictShapeTypeChecker(shapeTypes) {
    function validate(props, propName, componentName, location, propFullName) {
      var propValue = props[propName];
      var propType = getPropType(propValue);
      if (propType !== 'object') {
        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of type `' + propType + '` ' + ('supplied to `' + componentName + '`, expected `object`.'));
      }
      // We need to check all keys in case some are required but missing from
      // props.
      var allKeys = assign({}, props[propName], shapeTypes);
      for (var key in allKeys) {
        var checker = shapeTypes[key];
        if (!checker) {
          return new PropTypeError(
            'Invalid ' + location + ' `' + propFullName + '` key `' + key + '` supplied to `' + componentName + '`.' +
            '\nBad object: ' + JSON.stringify(props[propName], null, '  ') +
            '\nValid keys: ' +  JSON.stringify(Object.keys(shapeTypes), null, '  ')
          );
        }
        var error = checker(propValue, key, componentName, location, propFullName + '.' + key, ReactPropTypesSecret);
        if (error) {
          return error;
        }
      }
      return null;
    }

    return createChainableTypeChecker(validate);
  }

  function isNode(propValue) {
    switch (typeof propValue) {
      case 'number':
      case 'string':
      case 'undefined':
        return true;
      case 'boolean':
        return !propValue;
      case 'object':
        if (Array.isArray(propValue)) {
          return propValue.every(isNode);
        }
        if (propValue === null || isValidElement(propValue)) {
          return true;
        }

        var iteratorFn = getIteratorFn(propValue);
        if (iteratorFn) {
          var iterator = iteratorFn.call(propValue);
          var step;
          if (iteratorFn !== propValue.entries) {
            while (!(step = iterator.next()).done) {
              if (!isNode(step.value)) {
                return false;
              }
            }
          } else {
            // Iterator will provide entry [k,v] tuples rather than values.
            while (!(step = iterator.next()).done) {
              var entry = step.value;
              if (entry) {
                if (!isNode(entry[1])) {
                  return false;
                }
              }
            }
          }
        } else {
          return false;
        }

        return true;
      default:
        return false;
    }
  }

  function isSymbol(propType, propValue) {
    // Native Symbol.
    if (propType === 'symbol') {
      return true;
    }

    // falsy value can't be a Symbol
    if (!propValue) {
      return false;
    }

    // 19.4.3.5 Symbol.prototype[@@toStringTag] === 'Symbol'
    if (propValue['@@toStringTag'] === 'Symbol') {
      return true;
    }

    // Fallback for non-spec compliant Symbols which are polyfilled.
    if (typeof Symbol === 'function' && propValue instanceof Symbol) {
      return true;
    }

    return false;
  }

  // Equivalent of `typeof` but with special handling for array and regexp.
  function getPropType(propValue) {
    var propType = typeof propValue;
    if (Array.isArray(propValue)) {
      return 'array';
    }
    if (propValue instanceof RegExp) {
      // Old webkits (at least until Android 4.0) return 'function' rather than
      // 'object' for typeof a RegExp. We'll normalize this here so that /bla/
      // passes PropTypes.object.
      return 'object';
    }
    if (isSymbol(propType, propValue)) {
      return 'symbol';
    }
    return propType;
  }

  // This handles more types than `getPropType`. Only used for error messages.
  // See `createPrimitiveTypeChecker`.
  function getPreciseType(propValue) {
    if (typeof propValue === 'undefined' || propValue === null) {
      return '' + propValue;
    }
    var propType = getPropType(propValue);
    if (propType === 'object') {
      if (propValue instanceof Date) {
        return 'date';
      } else if (propValue instanceof RegExp) {
        return 'regexp';
      }
    }
    return propType;
  }

  // Returns a string that is postfixed to a warning about an invalid type.
  // For example, "undefined" or "of type array"
  function getPostfixForTypeWarning(value) {
    var type = getPreciseType(value);
    switch (type) {
      case 'array':
      case 'object':
        return 'an ' + type;
      case 'boolean':
      case 'date':
      case 'regexp':
        return 'a ' + type;
      default:
        return type;
    }
  }

  // Returns class name of the object, if any.
  function getClassName(propValue) {
    if (!propValue.constructor || !propValue.constructor.name) {
      return ANONYMOUS;
    }
    return propValue.constructor.name;
  }

  ReactPropTypes.checkPropTypes = checkPropTypes;
  ReactPropTypes.resetWarningCache = checkPropTypes.resetWarningCache;
  ReactPropTypes.PropTypes = ReactPropTypes;

  return ReactPropTypes;
};


/***/ }),

/***/ "./node_modules/prop-types/index.js":
/*!******************************************!*\
  !*** ./node_modules/prop-types/index.js ***!
  \******************************************/
/***/ ((module, __unused_webpack_exports, __webpack_require__) => {

/**
 * Copyright (c) 2013-present, Facebook, Inc.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

if (true) {
  var ReactIs = __webpack_require__(/*! react-is */ "./node_modules/prop-types/node_modules/react-is/index.js");

  // By explicitly using `prop-types` you are opting into new development behavior.
  // http://fb.me/prop-types-in-prod
  var throwOnDirectAccess = true;
  module.exports = __webpack_require__(/*! ./factoryWithTypeCheckers */ "./node_modules/prop-types/factoryWithTypeCheckers.js")(ReactIs.isElement, throwOnDirectAccess);
} else {}


/***/ }),

/***/ "./node_modules/prop-types/lib/ReactPropTypesSecret.js":
/*!*************************************************************!*\
  !*** ./node_modules/prop-types/lib/ReactPropTypesSecret.js ***!
  \*************************************************************/
/***/ ((module) => {

"use strict";
/**
 * Copyright (c) 2013-present, Facebook, Inc.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */



var ReactPropTypesSecret = 'SECRET_DO_NOT_PASS_THIS_OR_YOU_WILL_BE_FIRED';

module.exports = ReactPropTypesSecret;


/***/ }),

/***/ "./node_modules/prop-types/node_modules/react-is/cjs/react-is.development.js":
/*!***********************************************************************************!*\
  !*** ./node_modules/prop-types/node_modules/react-is/cjs/react-is.development.js ***!
  \***********************************************************************************/
/***/ ((__unused_webpack_module, exports) => {

"use strict";
/** @license React v16.13.1
 * react-is.development.js
 *
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */





if (true) {
  (function() {
'use strict';

// The Symbol used to tag the ReactElement-like types. If there is no native Symbol
// nor polyfill, then a plain number is used for performance.
var hasSymbol = typeof Symbol === 'function' && Symbol.for;
var REACT_ELEMENT_TYPE = hasSymbol ? Symbol.for('react.element') : 0xeac7;
var REACT_PORTAL_TYPE = hasSymbol ? Symbol.for('react.portal') : 0xeaca;
var REACT_FRAGMENT_TYPE = hasSymbol ? Symbol.for('react.fragment') : 0xeacb;
var REACT_STRICT_MODE_TYPE = hasSymbol ? Symbol.for('react.strict_mode') : 0xeacc;
var REACT_PROFILER_TYPE = hasSymbol ? Symbol.for('react.profiler') : 0xead2;
var REACT_PROVIDER_TYPE = hasSymbol ? Symbol.for('react.provider') : 0xeacd;
var REACT_CONTEXT_TYPE = hasSymbol ? Symbol.for('react.context') : 0xeace; // TODO: We don't use AsyncMode or ConcurrentMode anymore. They were temporary
// (unstable) APIs that have been removed. Can we remove the symbols?

var REACT_ASYNC_MODE_TYPE = hasSymbol ? Symbol.for('react.async_mode') : 0xeacf;
var REACT_CONCURRENT_MODE_TYPE = hasSymbol ? Symbol.for('react.concurrent_mode') : 0xeacf;
var REACT_FORWARD_REF_TYPE = hasSymbol ? Symbol.for('react.forward_ref') : 0xead0;
var REACT_SUSPENSE_TYPE = hasSymbol ? Symbol.for('react.suspense') : 0xead1;
var REACT_SUSPENSE_LIST_TYPE = hasSymbol ? Symbol.for('react.suspense_list') : 0xead8;
var REACT_MEMO_TYPE = hasSymbol ? Symbol.for('react.memo') : 0xead3;
var REACT_LAZY_TYPE = hasSymbol ? Symbol.for('react.lazy') : 0xead4;
var REACT_BLOCK_TYPE = hasSymbol ? Symbol.for('react.block') : 0xead9;
var REACT_FUNDAMENTAL_TYPE = hasSymbol ? Symbol.for('react.fundamental') : 0xead5;
var REACT_RESPONDER_TYPE = hasSymbol ? Symbol.for('react.responder') : 0xead6;
var REACT_SCOPE_TYPE = hasSymbol ? Symbol.for('react.scope') : 0xead7;

function isValidElementType(type) {
  return typeof type === 'string' || typeof type === 'function' || // Note: its typeof might be other than 'symbol' or 'number' if it's a polyfill.
  type === REACT_FRAGMENT_TYPE || type === REACT_CONCURRENT_MODE_TYPE || type === REACT_PROFILER_TYPE || type === REACT_STRICT_MODE_TYPE || type === REACT_SUSPENSE_TYPE || type === REACT_SUSPENSE_LIST_TYPE || typeof type === 'object' && type !== null && (type.$$typeof === REACT_LAZY_TYPE || type.$$typeof === REACT_MEMO_TYPE || type.$$typeof === REACT_PROVIDER_TYPE || type.$$typeof === REACT_CONTEXT_TYPE || type.$$typeof === REACT_FORWARD_REF_TYPE || type.$$typeof === REACT_FUNDAMENTAL_TYPE || type.$$typeof === REACT_RESPONDER_TYPE || type.$$typeof === REACT_SCOPE_TYPE || type.$$typeof === REACT_BLOCK_TYPE);
}

function typeOf(object) {
  if (typeof object === 'object' && object !== null) {
    var $$typeof = object.$$typeof;

    switch ($$typeof) {
      case REACT_ELEMENT_TYPE:
        var type = object.type;

        switch (type) {
          case REACT_ASYNC_MODE_TYPE:
          case REACT_CONCURRENT_MODE_TYPE:
          case REACT_FRAGMENT_TYPE:
          case REACT_PROFILER_TYPE:
          case REACT_STRICT_MODE_TYPE:
          case REACT_SUSPENSE_TYPE:
            return type;

          default:
            var $$typeofType = type && type.$$typeof;

            switch ($$typeofType) {
              case REACT_CONTEXT_TYPE:
              case REACT_FORWARD_REF_TYPE:
              case REACT_LAZY_TYPE:
              case REACT_MEMO_TYPE:
              case REACT_PROVIDER_TYPE:
                return $$typeofType;

              default:
                return $$typeof;
            }

        }

      case REACT_PORTAL_TYPE:
        return $$typeof;
    }
  }

  return undefined;
} // AsyncMode is deprecated along with isAsyncMode

var AsyncMode = REACT_ASYNC_MODE_TYPE;
var ConcurrentMode = REACT_CONCURRENT_MODE_TYPE;
var ContextConsumer = REACT_CONTEXT_TYPE;
var ContextProvider = REACT_PROVIDER_TYPE;
var Element = REACT_ELEMENT_TYPE;
var ForwardRef = REACT_FORWARD_REF_TYPE;
var Fragment = REACT_FRAGMENT_TYPE;
var Lazy = REACT_LAZY_TYPE;
var Memo = REACT_MEMO_TYPE;
var Portal = REACT_PORTAL_TYPE;
var Profiler = REACT_PROFILER_TYPE;
var StrictMode = REACT_STRICT_MODE_TYPE;
var Suspense = REACT_SUSPENSE_TYPE;
var hasWarnedAboutDeprecatedIsAsyncMode = false; // AsyncMode should be deprecated

function isAsyncMode(object) {
  {
    if (!hasWarnedAboutDeprecatedIsAsyncMode) {
      hasWarnedAboutDeprecatedIsAsyncMode = true; // Using console['warn'] to evade Babel and ESLint

      console['warn']('The ReactIs.isAsyncMode() alias has been deprecated, ' + 'and will be removed in React 17+. Update your code to use ' + 'ReactIs.isConcurrentMode() instead. It has the exact same API.');
    }
  }

  return isConcurrentMode(object) || typeOf(object) === REACT_ASYNC_MODE_TYPE;
}
function isConcurrentMode(object) {
  return typeOf(object) === REACT_CONCURRENT_MODE_TYPE;
}
function isContextConsumer(object) {
  return typeOf(object) === REACT_CONTEXT_TYPE;
}
function isContextProvider(object) {
  return typeOf(object) === REACT_PROVIDER_TYPE;
}
function isElement(object) {
  return typeof object === 'object' && object !== null && object.$$typeof === REACT_ELEMENT_TYPE;
}
function isForwardRef(object) {
  return typeOf(object) === REACT_FORWARD_REF_TYPE;
}
function isFragment(object) {
  return typeOf(object) === REACT_FRAGMENT_TYPE;
}
function isLazy(object) {
  return typeOf(object) === REACT_LAZY_TYPE;
}
function isMemo(object) {
  return typeOf(object) === REACT_MEMO_TYPE;
}
function isPortal(object) {
  return typeOf(object) === REACT_PORTAL_TYPE;
}
function isProfiler(object) {
  return typeOf(object) === REACT_PROFILER_TYPE;
}
function isStrictMode(object) {
  return typeOf(object) === REACT_STRICT_MODE_TYPE;
}
function isSuspense(object) {
  return typeOf(object) === REACT_SUSPENSE_TYPE;
}

exports.AsyncMode = AsyncMode;
exports.ConcurrentMode = ConcurrentMode;
exports.ContextConsumer = ContextConsumer;
exports.ContextProvider = ContextProvider;
exports.Element = Element;
exports.ForwardRef = ForwardRef;
exports.Fragment = Fragment;
exports.Lazy = Lazy;
exports.Memo = Memo;
exports.Portal = Portal;
exports.Profiler = Profiler;
exports.StrictMode = StrictMode;
exports.Suspense = Suspense;
exports.isAsyncMode = isAsyncMode;
exports.isConcurrentMode = isConcurrentMode;
exports.isContextConsumer = isContextConsumer;
exports.isContextProvider = isContextProvider;
exports.isElement = isElement;
exports.isForwardRef = isForwardRef;
exports.isFragment = isFragment;
exports.isLazy = isLazy;
exports.isMemo = isMemo;
exports.isPortal = isPortal;
exports.isProfiler = isProfiler;
exports.isStrictMode = isStrictMode;
exports.isSuspense = isSuspense;
exports.isValidElementType = isValidElementType;
exports.typeOf = typeOf;
  })();
}


/***/ }),

/***/ "./node_modules/prop-types/node_modules/react-is/index.js":
/*!****************************************************************!*\
  !*** ./node_modules/prop-types/node_modules/react-is/index.js ***!
  \****************************************************************/
/***/ ((module, __unused_webpack_exports, __webpack_require__) => {

"use strict";


if (false) {} else {
  module.exports = __webpack_require__(/*! ./cjs/react-is.development.js */ "./node_modules/prop-types/node_modules/react-is/cjs/react-is.development.js");
}


/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

"use strict";
module.exports = window["React"];

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["element"];

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be in strict mode.
(() => {
"use strict";
/*!*************************!*\
  !*** ./client/build.js ***!
  \*************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _screenshots__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./screenshots */ "./client/screenshots/index.js");

/**
 * External dependencies.
 */


/**
 * Internal dependencies.
 */

function initializeScreenshots(id) {
  const container = document.getElementById(id);
  if (!container) {
    return;
  }
  const elements = container.querySelectorAll('figure');
  const images = [];
  for (let i = 0; i < elements.length; i++) {
    const caption = elements[i].querySelector('figcaption');
    const item = {
      src: elements[i].querySelector('img.screenshot').src,
      caption: caption ? caption.textContent : ''
    };
    images.push(item);
  }
  if (images.length > 0) {
    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.render)((0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_screenshots__WEBPACK_IMPORTED_MODULE_2__["default"], {
      screenshots: images
    }), container);
  }
}
initializeScreenshots('screenshots');
})();

/******/ })()
;
//# sourceMappingURL=theme.js.map