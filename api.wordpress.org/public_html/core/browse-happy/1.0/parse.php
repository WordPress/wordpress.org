<?php
/**
 * @package BrowseHappy
 */

require dirname( __FILE__ ) . '/browsers.php';

/**
 * Returns an associative array of explicit browser token names and their
 * associated info.
 *
 * Explicit tokens are tokens that, if present, indicate a specific browser.
 *
 * If a browser is not identified by an explicit token, or requires special
 * handling not supported by the default handler, then a new conditional block
 * for the browser instead needs to be added in browsehappy_parse_user_agent().
 *
 * In any case, the browser token name also needs to be added to the regex for
 * browser tokens in browsehappy_parse_user_agent().
 *
 * @return array {
 *     Associative array of browser tokens and their associated data.
 *
 *     @type array $data {
 *         Associative array of browser data. All are optional.
 *
 *         @type string $name        Name of browser, if it differs from the
 *                                   token name. Default is token name.
 *         @type bool   $use_version Should the 'Version' token, if present,
 *                                   supercede the version associated with the
 *                                   browser token? Default false.
 *         @type bool   $mobile      Does the browser signify the platform is
 *                                   mobile (for situations where it may no
 *                                   already be apparent)? Default false.
 *         @type string $platform    The name of the platform, to supercede
 *                                   whatever platform may have been detected.
 *                                   Default empty string.
 *     }
 * }
 */
function browsehappy_get_explicit_browser_tokens() {
	 return array(
		'Camino'          => array(),
		'Chromium'        => array(),
		'Edge'            => array(
			'name'        => 'Microsoft Edge',
		),
		'Kindle'          => array(
			'name'        => 'Kindle Browser',
			'use_version' => true,
		),
		'Konqueror'       => array(),
		'konqueror'       => array(
			'name'        => 'Konqueror',
		),
		'NokiaBrowser'    => array(
			'name'        => 'Nokia Browser',
			'mobile'      => true,
		),
		'Opera Mini'      => array( // Must be before 'Opera'
			'mobile'      => true,
			'use_version' => true,
		),
		'Opera'           => array(
			'use_version' => true,
		),
		'OPR'             => array(
			'name'        => 'Opera',
			'use_version' => true,
		),
		'PaleMoon'        => array(
			'name'        => 'Pale Moon',
		),
		'QQBrowser'       => array(
			'name'        => 'QQ Browser',
		),
		'RockMelt'        => array(),
		'SamsungBrowser'  => array(
			'name'        => 'Samsung Browser',
		),
		'SeaMonkey'       => array(),
		'Silk'            => array(
			'name'        => 'Amazon Silk',
		),
		'S40OviBrowser'   => array(
			'name'        => 'Ovi Browser',
			'mobile'      => true,
			'platform'    => 'Symbian',
		),
		'UCBrowser'       => array( // Must be before 'UCWEB'
			'name'        => 'UC Browser',
		),
		'UCWEB'           => array(
			'name'        => 'UC Browser',
		),
		'Vivaldi'         => array(),
		'IEMobile'        => array( // Keep last just in case
			'name'        => 'Internet Explorer Mobile',
		),
	);
}

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
	$mobile_device = '';

	// Identify platform/OS in user-agent string.
	if ( preg_match(
		'/(?P<platform>'                                          // Capture subpattern matches into 'platform' array
		.     'Windows Phone( OS)?|Symbian|SymbOS|Android|iPhone' // Platform tokens
		.     '|iPad|Windows|Linux|Macintosh|FreeBSD|OpenBSD'     // More platform tokens
		.     '|SunOS|RIM Tablet OS|PlayBook'                     // More platform tokens
		. ')'
		. '(?:'
		.     ' (NT|amd64|armv7l|zvav)'                           // Possibly followed by specific modifiers/specifiers
		. ')*'
		. '(?:'
		.     ' [ix]?[0-9._]+'                                    // Possibly followed by architecture modifier (e.g. x86_64)
		.     '(\-[0-9a-z\.\-]+)?'                                // Possibly followed by a hypenated version number
		. ')*'
		. '(;|\))'                                                // Ending in a semi-colon or close parenthesis
		. '/im',                                                  // Case insensitive, multiline
		$user_agent,
		$regs
	) ) {
		$data['platform'] = $regs['platform'];
	}

	// Find tokens of interest in user-agent string.
	preg_match_all(
		  '%(?P<name>'                                            // Capture subpattern matches into the 'name' array
		.     'Opera Mini|Opera|OPR|Edge|UCBrowser|UCWEB'         // Browser tokens
		.     '|QQBrowser|SymbianOS|Symbian|S40OviBrowser'        // More browser tokens
		.     '|Trident|Silk|Konqueror|PaleMoon|Puffin'           // More browser tokens
		.     '|SeaMonkey|Vivaldi|Camino|Chromium|Kindle|Firefox' // More browser tokens
		.     '|SamsungBrowser|(?:Mobile )?Safari|NokiaBrowser'   // More browser tokens
		.     '|MSIE|RockMelt|AppleWebKit|Chrome|IEMobile'        // More browser tokens
		.     '|Version'                                          // Version token
		. ')'
		. '(?:'
		.     '[/ ]'                                              // Forward slash or space
		. ')'
		. '(?P<version>'                                          // Capture subpattern matches into 'version' array
		.     '[0-9.]+'                                           // One or more numbers and/or decimal points
		. ')'
		. '%im',                                                  // Case insensitive, multiline
		$user_agent,
		$result,
		PREG_PATTERN_ORDER
	);

	// Create associative array with tokens as keys and versions as values.
	$tokens = array_combine( array_reverse( $result['name'] ), array_reverse( $result['version'] ) );

	// Properly set platform if Android is actually being reported.
	if ( 'Linux' === $data['platform'] && false !== strpos( $user_agent, 'Android' ) ) {
		if ( strpos( $user_agent, 'Kindle' ) ) {
			$data['platform'] = 'Fire OS';
		} else {
			$data['platform'] = 'Android';
		}
	}
	// Normalize Windows Phone OS name when "OS" is omitted.
	elseif ( 'Windows Phone' === $data['platform'] ) {
		$data['platform'] = 'Windows Phone OS';
	}
	// Standardize Symbian OS name.
	elseif (
		in_array( $data['platform'], array( 'Symbian', 'SymbOS' ) )
	||
		! empty( $tokens['SymbianOS'] )
	||
		! empty( $tokens['Symbian'] )
	) {
		if ( ! in_array( $data['platform'], array( 'Symbian', 'SymbOS' ) ) ) {
			unset( $tokens['SymbianOS'] );
			unset( $tokens['Symbian'] );
		}
		$data['platform'] = 'Symbian';
	}
	// Generically detect some mobile devices.
	elseif (
		! $data['platform']
	&&
		preg_match( '/BlackBerry|Nokia|SonyEricsson/', $user_agent, $matches )
	) {
		$data['platform'] = 'Mobile';
		$mobile_device    = $matches[0];
	}

	// Flag known mobile platforms as mobile.
	if ( in_array( $data['platform'], array( 'Android', 'Fire OS', 'iPad', 'iPhone', 'Mobile', 'PlayBook', 'RIM Tablet OS', 'Symbian', 'Windows Phone OS' ) ) ) {
		$data['mobile'] = true;
	}

	// If Version/x.x.x was specified in UA string store it and ignore it
	if ( ! empty( $tokens['Version'] ) ) {
		$version = $tokens['Version'];
		unset( $tokens['Version'] );
	}

	$explicit_tokens = browsehappy_get_explicit_browser_tokens();

	// No indentifiers provided
	if ( ! $tokens ) {
		if ( 'BlackBerry' === $mobile_device ) {
			$data['name'] = 'BlackBerry Browser';
		} else {
			$data['name'] = 'unknown';
		}
	}
	// Explicitly identified browser (info defined above in $explicit_tokens).
	elseif ( $found = array_intersect( array_keys( $explicit_tokens ), array_keys( $tokens ) ) ) {
		$token = reset( $found );

		$data['name']    = $explicit_tokens[ $token ]['name'] ?? $token;
		$data['version'] = $tokens[ $token ];
		if ( empty( $explicit_tokens[ $token ]['use_version'] ) ) {
			$version = '';
		}
		if ( ! empty( $explicit_tokens[ $token ]['mobile'] ) ) {
			$data['mobile'] = true;
		}
		if ( ! empty( $explicit_tokens[ $token ]['platform'] ) ) {
			$data['platform'] = $explicit_tokens[ $token ]['platform'];
		}
	}
	// Puffin
	elseif ( ! empty( $tokens['Puffin'] ) ) {
		$data['name']     = 'Puffin';
		$data['version']  = $tokens['Puffin'];
		$version          = '';
		// If not an already-identified mobile platform, set it as such.
		if ( ! $data['mobile'] ) {
			$data['mobile']   = true;
			$data['platform'] = '';
		}
	}
	// Trident (Internet Explorer)
	elseif ( ! empty( $tokens['Trident'] ) ) {
		// IE 8-10 more reliably report version via Trident token than MSIE token.
		// IE 11 uses Trident token without an MSIE token.
		// https://msdn.microsoft.com/library/hh869301(v=vs.85).aspx
		$data['name'] = 'Internet Explorer';
		$trident_ie_mapping = array(
			'4.0' => '8.0',
			'5.0' => '9.0',
			'6.0' => '10.0',
			'7.0' => '11.0',
		);
		$ver = $tokens['Trident'];
		$data['version'] = $trident_ie_mapping[ $ver ] ?? $ver;
	}
	// Internet Explorer (pre v8.0)
	elseif ( ! empty( $tokens['MSIE'] ) ) {
		$data['name'] = 'Internet Explorer';
		$data['version'] = $tokens['MSIE'];
	}
	// AppleWebKit-emulating browsers
	elseif ( ! empty( $tokens['AppleWebKit'] ) ) {
		if ( ! empty( $tokens['Mobile Safari'] ) ) {
			if ( ! empty( $tokens['Chrome'] ) ) {
				$data['name'] = 'Chrome';
				$version = $tokens['Chrome'];
			} elseif ( 'Android' === $data['platform'] ) {
				$data['name'] = 'Android Browser';
			} elseif ( 'Fire OS' === $data['platform'] ) {
				$data['name'] = 'Kindle Browser';
			} elseif ( false !== strpos( $user_agent, 'BlackBerry' ) || false !== strpos( $user_agent, 'BB10' ) ) {
				$data['name']   = 'BlackBerry Browser';
				$data['mobile'] = true;

				if ( false !== stripos( $user_agent, 'BB10' ) ) {
					$tokens['Mobile Safari'] = '';
					$version = '';
				}
			} else {
				$data['name'] = 'Mobile Safari';
			}
		} elseif ( ! empty( $tokens['Chrome'] ) ) {
			$data['name'] = 'Chrome';
			$version = '';
		} elseif ( ! empty( $data['platform'] ) && 'PlayBook' == $data['platform'] ) {
			$data['name'] = 'PlayBook';
		} elseif ( ! empty( $tokens['Safari'] ) ) {
			if ( 'Android' === $data['platform'] ) {
				$data['name'] = 'Android Browser';
			} elseif ( 'Symbian' === $data['platform'] ) {
				$data['name'] = 'Nokia Browser';
				$tokens['Safari'] = '';
			} else {
				$data['name'] = 'Safari';
			}
		} else {
			$data['name'] = 'unknown';
			$tokens['AppleWebKit'] = '';
			$version = '';
		}
		$data['version'] = $tokens[ $data['name'] ] ?? '';
	}
	// Fall back to whatever is being reported.
	else {
		$ordered_tokens = array_reverse( $tokens );
		$data['version'] = reset( $ordered_tokens );
		$data['name'] = key( $ordered_tokens );
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

	if ( 'Internet Explorer' === $data['name'] ) {
		$data['insecure'] = true;
		$data['upgrade']  = true;
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
