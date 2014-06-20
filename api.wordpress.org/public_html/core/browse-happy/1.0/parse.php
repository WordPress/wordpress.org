<?php
/**
 * @package BrowseHappy
 */

require dirname( __FILE__ ) . '/browsers.php';

/**
* Parses a user agent string into its important parts
*
* @param string $user_agent
*
* @return array containing platform, name, and version
*/
function browsehappy_parse_user_agent( $user_agent ) {
	$data = array();

    if ( preg_match('/^.+?(?P<platform>Android|iPhone|iPad|Windows|Linux|Macintosh|Windows Phone OS|RIM Tablet OS|PlayBook)(?: NT)*(?: [ix]?[0-9._]+)*(;|\))/im', $user_agent, $regs ) )
        $data['platform'] = $regs['platform'];

    preg_match_all( '%(?P<name>Trident|Camino|Kindle|Firefox|(?:Mobile )?Safari|MSIE|RockMelt|AppleWebKit|Chrome|IEMobile|Opera|Version)(?:[/ ])(?P<version>[0-9.]+)%im', $user_agent, $result, PREG_PATTERN_ORDER );
    
    // If Version/x.x.x was specified in UA string store it and ignore it
    if ( $key = array_search( 'Version', $result['name'] ) ) {
        $version = $result['version'][ $key ];
        unset( $result['name'][ $key ] );
        unset( $result['version'][ $key ] );
    }

    if ( 'AppleWebKit' == $result['name'][0] ) {
        if ( $key = array_search( 'Mobile Safari', $result['name'] ) ) {
            $data['name'] = 'Mobile Safari';
        // } elseif ( ( 'Android' == $data['platform'] && !($key = 0) ) || $key = array_search( 'Chrome', $result['name'] ) ) {
        } elseif ( $key = array_search( 'RockMelt', $result['name'] ) ) {
            $data['name'] = 'RockMelt';
        } elseif ( $key = array_search( 'Chrome', $result['name'] ) ) {
            $data['name'] = 'Chrome';
        } elseif ( ! empty( $data['platform'] ) && 'PlayBook' == $data['platform'] ) {
            $data['name'] = 'PlayBook';
        } elseif ( $key = array_search( 'Kindle', $result['name'] ) ) {
            $data['name'] = 'Kindle';
        } elseif ( $key = array_search( 'Safari', $result['name'] ) ) {
            $data['name'] = 'Safari';
        } else {
            $key = 0;
            $data['name'] = 'webkit';
        }
        $data['version'] = $result['version'][ $key ];
    } elseif ( $key = array_search( 'Opera', $result['name'] ) ) {
        $data['name'] = $result['name'][$key];
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
    	$data['name'] = 'Internet Explorer';
    	if( '7.0' == $result['version'][0] ) {
			$data['version'] = '11';
		}
	} else {
        $data['name'] = $result['name'][0];
        $data['version'] = $result['version'][0];
    }

    if ( in_array( $data['name'], array( 'Kindle' ) ) )
        $data['platform'] = $data['name'];

    if ( in_array( $data['platform'], array( 'Android', 'iPad', 'iPhone' ) ) )
	$data['name'] = $data['platform'];

    // If Version/x.x.x was specified in UA string
    if ( ! empty( $version ) )
        $data['version'] = $version;

	$browser_data = browsehappy_api_get_browser_data( $data['name'] );
	$data['update_url']  = $browser_data ? $browser_data->url : '';
	$data['img_src']     = $browser_data ? $browser_data->img_src : '';
	$data['img_src_ssl'] = $browser_data ? $browser_data->img_src_ssl : '';

	$data['current_version'] = get_browser_version_from_name( $data['name'] );
	$data['upgrade']         = ( !empty( $data['current_version'] ) && version_compare( $data['version'], $data['current_version'], '<' ) );
	$data['insecure']        = ( 'Internet Explorer' == $data['name'] && version_compare( $data['version'], '8', '<' ) );

	return $data;
}

function get_browser_version_from_name( $name ) {
	$versions = get_browser_current_versions();

	return isset( $versions[ $name ] ) ? $versions[ $name ] : '';
}

