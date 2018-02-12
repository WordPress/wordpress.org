<?php

namespace WordCamp\Autoloader;
defined( 'WPINC' ) or die();

spl_autoload_register( __NAMESPACE__ . '\autoload' );

/**
 * Autoload Utility classes
 *
 * @param string $class_name
 */
function autoload( $class_name ) {
	if ( false === strpos( $class_name, 'WordCamp\Utilities' ) ) {
		return;
	}

	$file_name = str_replace( 'WordCamp\Utilities\\', '', $class_name );
	$file_name = str_replace( '_', '-', strtolower( $file_name ) );

	require_once( sprintf( '%s/utilities/class-%s.php', __DIR__, $file_name ) );
}
