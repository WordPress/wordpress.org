<?php

/**
 * Routes: GP_Sidebar class
 *
 * Manages the sidebar in the translation rows.
 *
 * @package gp-translation-helpers
 * @since 0.0.2
 */
class GP_Sidebar {

	public static function init() {
		add_filter(
			'gp_tmpl_load_locations',
			function ( $locations, $template, $args, $template_path ) {
				if ( 'translation-row-editor-meta' === $template ) {
					array_unshift( $locations, dirname( __FILE__, 2 ) . '/templates/gp-templates-overrides/' );
				}

				return $locations;
			},
			60,
			4
		);
	}
}
