/**
 * File global.js.
 *
 * Global helper functions such as setting body classes for detected features.
 *
 * @package CampSite_2017
 */

/* global campsiteScreenReaderText */

(function( $ ) {
	var $body = $( 'body' );

	/*
	 * Test if inline SVGs are supported.
	 *
	 * @link https://github.com/Modernizr/Modernizr/
	 */
	function supportsInlineSVG() {
		var div = document.createElement( 'div' );
		div.innerHTML = '<svg/>';

		return 'http://www.w3.org/2000/svg' === ( 'undefined' !== typeof SVGRect && div.firstChild && div.firstChild.namespaceURI );
	}

	/**
	 * Test if an iOS device.
	 */
	function checkiOS() {
		return /iPad|iPhone|iPod/.test( navigator.userAgent ) && ! window.MSStream;
	}

	/*
	 * Test if background-attachment: fixed is supported.
	 *
	 * @link http://stackoverflow.com/questions/14115080/detect-support-for-background-attachment-fixed
	 */
	function supportsFixedBackground() {
		var el = document.createElement( 'div' ),
			isSupported;

		try {
			if ( ! ( 'backgroundAttachment' in el.style ) || checkiOS() ) {
				return false;
			}

			el.style.backgroundAttachment = 'fixed';
			isSupported = ( 'fixed' === el.style.backgroundAttachment );

			return isSupported;
		} catch ( e ) {
			return false;
		}
	}

	$( document ).ready( function () {
		if ( true === supportsInlineSVG() ) {
			document.documentElement.className = document.documentElement.className.replace( /(\s*)no-svg(\s*)/, '$1svg$2' );
		}

		if ( true === supportsFixedBackground() ) {
			document.documentElement.className += ' background-fixed';
		}
	} );

	$( document ).on( 'wp-custom-header-video-loaded', function () {
		$body.addClass( 'has-header-video' );
	} );
})( jQuery );
