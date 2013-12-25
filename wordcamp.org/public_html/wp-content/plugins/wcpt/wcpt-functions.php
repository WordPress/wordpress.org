<?php

/**
 * wcpt_head ()
 *
 * Add our custom head action to wp_head
 *
 * @package WordCamp Post Type
 * @subpackage Template Tags
 * @since WordCamp Post Type (0.1)
*/
function wcpt_head () {
	do_action( 'wcpt_head' );
}
add_action( 'wp_head', 'wcpt_head' );

/**
 * wcpt_head ()
 *
 * Add our custom head action to wp_head
 *
 * @package WordCamp Post Type
 * @subpackage Template Tags
 * @since WordCamp Post Type (0.1)
 */
function wcpt_footer () {
	do_action( 'wcpt_footer' );
}
add_action( 'wp_footer', 'wcpt_footer' );

/**
 * wcpt_has_access()
 *
 * Make sure user can perform special tasks
 *
 * @package WordCamp Post Type
 * @subpackage Functions
 * @since WordCamp Post Type (0.1)
 *
 * @uses is_super_admin ()
 * @uses apply_filters
 *
 * @return bool $has_access
 */
function wcpt_has_access () {

	if ( is_super_admin () )
		$has_access = true;
	else
		$has_access = false;

	return apply_filters( 'wcpt_has_access', $has_access );
}

/**
 * wcpt_number_format ( $number, $decimals optional )
 *
 * Specific method of formatting numeric values
 *
 * @package WordCamp Post Type
 * @subpackage Functions
 * @since WordCamp Post Type (0.1)
 *
 * @param string $number Number to format
 * @param string $decimals optional Display decimals
 * @return string Formatted string
 */
function wcpt_number_format( $number, $decimals = false ) {
	// If empty, set $number to '0'
	if ( empty( $number ) || !is_numeric( $number ) )
		$number = '0';

	return apply_filters( 'wcpt_number_format', number_format( $number, $decimals ), $number, $decimals );
}

/**
 * wcpt_key_to_str ( $key = '' )
 *
 * Turn meta key into lower case string and transform spaces into underscores
 *
 * @param string $key
 * @return string
 */
function wcpt_key_to_str( $key, $prefix = '' ) {
	return $prefix . str_replace( array( ' ', '.' ), '_', strtolower( $key ) );
}

?>
