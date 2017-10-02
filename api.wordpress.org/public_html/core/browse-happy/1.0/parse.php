<?php
/**
 * @package BrowseHappy
 */

require dirname( __FILE__ ) . '/browsers.php';

/**
 * Parses a user agent string into its important parts.
 *
 * @param string $user_agent The user agent string for a browser.
 * @return array {
 *     Array containing data based on the parsing of the user agent.
 *
 *     @type string $platform        The platform running the browser.
 *     @type string $name            The name of the browser.
 *     @type string $version         The reported version of the browser.
 *     @type string $update_url      The URL to obtain the update for the browser.
 *     @type string $img_src         The non-HTTPS URL for the browser's logo image.
 *     @type string $img_src_ssl     The HTTPS URL for the browser's logo image.
 *     @type string $current_version The current latest version of the browser.
 *     @type bool   $upgrade         Is there an update available for the browser?
 *     @type bool   $insecure        Is the browser insecure?
 *     @type bool   $mobile          Is the browser on a mobile platform?
 * }
 */
function browsehappy_parse_user_agent( $user_agent ) {
	$data = array(
		'name'            => '',
		'version'         => '',
		'platform'        => '',
		'update_url'      => '',
		'img_src'         => '',
		'img_src_ssl'     => '',
		'current_version' => '',
		'upgrade'         => false,
		'insecure'        => false,
		'mobile'          => false,
	);

	if ( preg_match(
		'/^.+?(?P<platform>Windows Phone( OS)?|Android|iPhone|iPad|Windows|Linux|Macintosh|RIM Tablet OS|PlayBook)(?: (NT|zvav))*(?: [ix]?[0-9._]+)*(;|\))/im',
		$user_agent,
		$regs
	) ) {
		$data['platform'] = $regs['platform'];
	}

	// Properly set platform if Android is actually being reported.
	if ( 'Linux' === $data['platform'] && false !== strpos( $user_agent, 'Android' ) ) {
		if ( strpos( $user_agent, 'Kindle' ) ) {
			$data['platform'] = 'Fire OS';
		} else {
			$data['platform'] = 'Android';
		}
	} elseif ( 'Windows Phone' === $data['platform'] ) {
		$data['platform'] = 'Windows Phone OS';
	}

	if ( in_array( $data['platform'], array( 'Android', 'Fire OS', 'iPad', 'iPhone', 'PlayBook', 'RIM Tablet OS', 'Windows Phone OS' ) ) ) {
		$data['mobile'] = true;
	}

	preg_match_all(
		'%(?P<name>Opera Mini|Opera|OPR|Edge|Trident|Silk|Camino|Kindle|Firefox|(?:Mobile )?Safari|NokiaBrowser|MSIE|RockMelt|AppleWebKit|Chrome|IEMobile|Version)(?:[/ ])(?P<version>[0-9.]+)%im',
		$user_agent,
		$result,
		PREG_PATTERN_ORDER
	);

	// If Version/x.x.x was specified in UA string store it and ignore it
	if ( $key = array_search( 'Version', $result['name'] ) ) {
		$version = $result['version'][ $key ];
		unset( $result['name'][ $key ] );
		unset( $result['version'][ $key ] );
	}

	// No indentifiers provided
	if ( empty( $result['name'] ) ) {
		$data['name'] = 'unknown';
	}
	// Opera
	elseif (
		false !== ( $key = array_search( 'Opera Mini', $result['name'] ) )
	||
		false !== ( $key = array_search( 'Opera', $result['name'] ) )
	||
		false !== ( $key = array_search( 'OPR', $result['name'] ) )
	) {
		$data['name'] = $result['name'][ $key ];
		if ( 'OPR' === $data['name'] ) {
			$data['name'] = 'Opera';
		} elseif ( 'Opera Mini' === $data['name'] ) {
			$data['mobile'] = true;
		}
		$data['version'] = $result['version'][ $key ];
	}
	// Nokia Browser
	elseif ( false !== ( $key = array_search( 'NokiaBrowser', $result['name'] ) ) ) {
		$data['name']     = 'Nokia Browser';
		$data['version']  = $result['version'][ $key ];
		$data['mobile']   = true;
	}
	// Amazon Silk
	elseif ( false !== ( $key = array_search( 'Silk', $result['name'] ) ) ) {
		$data['name']     = 'Amazon Silk';
		$data['version']  = $result['version'][ $key ];
		$version          = '';
	}
	// Kindle Browser
	elseif ( false !== ( $key = array_search( 'Kindle', $result['name'] ) ) ) {
		$data['name']     = 'Kindle Browser';
		$data['version']  = $result['version'][ $key ];
	}
	// AppleWebKit-emulating browsers
	elseif ( 'AppleWebKit' == $result['name'][0] ) {
		if ( $key = array_search( 'Edge', $result['name'] ) ) {
			$data['name'] = 'Microsoft Edge';
		} elseif ( $key = array_search( 'Mobile Safari', $result['name'] ) ) {
			if ( $key2 = array_search( 'Chrome', $result['name'] ) ) {
				$data['name'] = 'Chrome';
				$version = $result['version'][ $key2 ];
			} elseif ( 'Android' === $data['platform'] ) {
				$data['name'] = 'Android Browser';
			} elseif ( 'Fire OS' === $data['platform'] ) {
				$data['name'] = 'Kindle Browser';
			} else {
				$data['name'] = 'Mobile Safari';
			}
		// } elseif ( ( 'Android' == $data['platform'] && !($key = 0) ) || $key = array_search( 'Chrome', $result['name'] ) ) {
		} elseif ( $key = array_search( 'RockMelt', $result['name'] ) ) {
			$data['name'] = 'RockMelt';
		} elseif ( $key = array_search( 'Chrome', $result['name'] ) ) {
			$data['name'] = 'Chrome';
			$version = '';
		} elseif ( ! empty( $data['platform'] ) && 'PlayBook' == $data['platform'] ) {
			$data['name'] = 'PlayBook';
		} elseif ( $key = array_search( 'Safari', $result['name'] ) ) {
			if ( 'Android' === $data['platform'] ) {
				$data['name'] = 'Android Browser';
			} else {
				$data['name'] = 'Safari';
			}
		} else {
			$key = 0;
			$data['name'] = 'unknown';
			$result['version'][ $key ] = '';
		}
		$data['version'] = $result['version'][ $key ];
	} elseif ( 'MSIE' == $result['name'][0] ) {
		if ( $key = array_search( 'IEMobile', $result['name'] ) ) {
			$data['name'] = 'Internet Explorer Mobile';
		} else {
			$data['name'] = 'Internet Explorer';
			$key = 0;
		}
		$data['version'] = $result['version'][ $key ];
	} elseif ( 'Trident' == $result['name'][0] ) {
		// IE 11 and beyond have switched to Trident
		// http://msdn.microsoft.com/en-us/library/ie/hh869301%28v=vs.85%29.aspx
		if ( $key = array_search( 'IEMobile', $result['name'] ) ) {
			$data['name'] = 'Internet Explorer Mobile';
		} else {
			$data['name'] = 'Internet Explorer';
		}
		if ( '7.0' == $result['version'][0] ) {
			$data['version'] = '11.0';
		}
	} else {
		$data['name'] = $result['name'][0];
		$data['version'] = $result['version'][0];
	}

	// Set the platform for Amazon-related browsers.
	if ( in_array( $data['name'], array( 'Amazon Silk', 'Kindle Browser' ) ) ) {
		$data['platform'] = 'Fire OS';
		$data['mobile']   = true;
	}

	// If Version/x.x.x was specified in UA string
	if ( ! empty( $version ) ) {
		$data['version'] = $version;
	}

	if ( $data['mobile'] ) {
		// Generically set "Mobile" as the platform if a platform hasn't been set.
		if ( ! $data['platform'] ) {
			$data['platform'] = 'Mobile';
		}

		// Don't fetch additional browser data for mobile platform browsers at this time.
		return $data;
	}

	$browser_data            = browsehappy_api_get_browser_data( $data['name'] );
	$data['update_url']      = $browser_data ? $browser_data->url : '';
	$data['img_src']         = $browser_data ? $browser_data->img_src : '';
	$data['img_src_ssl']     = $browser_data ? $browser_data->img_src_ssl : '';
	$data['current_version'] = get_browser_version_from_name( $data['name'] );
	$data['upgrade']         = ( ! empty( $data['current_version'] ) && version_compare( $data['version'], $data['current_version'], '<' ) );

	if ( 'Internet Explorer' === $data['name'] && version_compare( $data['version'], '11', '<' ) ) {
		$data['insecure'] = true;
	} elseif ( 'Firefox' === $data['name'] && version_compare( $data['version'], '52', '<' ) ) {
		$data['insecure'] = true;
	} elseif ( 'Opera' === $data['name'] && version_compare( $data['version'], '12.18', '<' ) ) {
		$data['insecure'] = true;
	} elseif ( 'Safari' === $data['name'] && version_compare( $data['version'], '10', '<' ) ) {
		$data['insecure'] = true;
	}

	return $data;
}

/**
 * Returns the current version for the given browser.
 *
 * @param string $name The name of the browser.
 * @return string      The version for the browser or an empty string if an
 *                     unknown browser.
 */
function get_browser_version_from_name( $name ) {
	$versions = get_browser_current_versions();

	return isset( $versions[ $name ] ) ? $versions[ $name ] : '';
}
