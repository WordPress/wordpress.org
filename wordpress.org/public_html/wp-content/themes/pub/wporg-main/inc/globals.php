<?php

/**
 * File used for functions that need to be in the global namespace.
 * No namespace declaration should be in this file
 */

/**
 * Adjust noindexing for robots tags
 */
function wporg_meta_robots( $robots ) {
	$template = get_page_template_slug();

	// noindex the enterprise pages until they are complete
	if ( strpos( $template, 'enterprise' ) !== false ) {
		return 'noindex';
	}

	return '';
}

