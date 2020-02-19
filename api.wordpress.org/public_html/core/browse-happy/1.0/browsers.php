<?php
/**
 * This holds browser data in a separate file so we can bump version numbers
 * without cluttering the SVN.
 *
 * @package BrowseHappy
 */

/**
 * Returns current version numbers for all browsers.
 *
 * These are for major release branches, not full build numbers.
 * Firefox 3.6, 4, etc., not Chrome 11.0.696.65.
 *
 * @return array Associative array of browser names with their respective
 *               current (or somewhat current) version number.
 */
function get_browser_current_versions() {
	return array(
		'Chrome'            => '18', // Lowest version at the moment (mobile)
		'Firefox'           => '56',
		'Microsoft Edge'    => '15.15063',
		'Opera'             => '12.18',
		'Safari'            => '11',
		'Internet Explorer' => '11',
	);
}

/**
 * Returns browser data for a given browser.
 *
 * @param string|false $browser The name of the browser. Default false.
 * @return false|array {
 *     Array of data about the browser. False if the browser is unknown.
 *
 *     @type string    $name        Name of the browser.
 *     @type string    $wikipedia   Wikipedia name for the browser.
 *     @type string    $url         The home URL for the browser.
 *     @type string    $img_src     The non-HTTPs URL for the browser's logo image.
 *     @type string    $img_src_ssl The HTTPS URL for the browser's logo image.
 * }
 */
function browsehappy_api_get_browser_data( $browser = false ) {

	$http  = 'http://s.w.org/images/browsers/';
	$https = 'https://s.w.org/images/browsers/';

	// Cache buster; increment whenever a browser logo image is updated.
	$cache_buster = 1;

	$data = array(
		'Internet Explorer' => (object) array(
			'name'        => 'Internet Explorer',
			'wikipedia'   => 'Internet_Explorer',
			'url'         => 'https://support.microsoft.com/help/17621/internet-explorer-downloads',
			'img_src'     => $http  . 'ie.png' . "?{$cache_buster}",
			'img_src_ssl' => $https . 'ie.png' . "?{$cache_buster}",
		),
		'Edge' => (object) array(
			'name'        => 'Microsoft Edge',
			'wikipedia'   => 'Microsoft Edge',
			'url'         => 'https://www.microsoft.com/edge',
			'img_src'     => $http  . 'edge.png' . "?{$cache_buster}",
			'img_src_ssl' => $https . 'edge.png' . "?{$cache_buster}",
		),
		'Firefox' => (object) array(
			'name'        => 'Mozilla Firefox',
			'wikipedia'   => 'Firefox',
			'url'         => 'https://www.mozilla.org/firefox/',
			'img_src'     => $http  . 'firefox.png' . "?{$cache_buster}",
			'img_src_ssl' => $https . 'firefox.png' . "?{$cache_buster}",
		),
		'Safari' => (object) array(
			'name'        => 'Safari',
			'wikipedia'   => 'Safari',
			'url'         => 'https://www.apple.com/safari/',
			'img_src'     => $http  . 'safari.png' . "?{$cache_buster}",
			'img_src_ssl' => $https . 'safari.png' . "?{$cache_buster}",
		),
		'Opera' => (object) array(
			'name'        => 'Opera',
			'wikipedia'   => 'Opera',
			'url'         => 'https://www.opera.com/',
			'img_src'     => $http  . 'opera.png' . "?{$cache_buster}",
			'img_src_ssl' => $https . 'opera.png' . "?{$cache_buster}",
		),
		'Chrome' => (object) array(
			'name'        => 'Google Chrome',
			'wikipedia'   => 'Google_Chrome',
			'url'         => 'https://www.google.com/chrome',
			'img_src'     => $http  . 'chrome.png' . "?{$cache_buster}",
			'img_src_ssl' => $https . 'chrome.png' . "?{$cache_buster}",
		),
	);

	if ( false === $browser ) {
		return $data;
	}

	if ( ! isset( $data[ $browser ] ) ) {
		return false;
	}

	return $data[ $browser ];
}
