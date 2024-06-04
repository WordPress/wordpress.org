<?php
namespace WordPressdotorg\Plugin_Directory;

/**
 * Various translation functions for the directory.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class I18n {

	/**
	 * Translates term names and descriptions.
	 *
	 * @param \WP_Term $term The Term object to translate.
	 * @return \WP_Term The term object with a translated `name` and/or `description` field.
	 */
	public static function translate_term( $term ) {
		if ( 'en_US' == get_locale() ) {
			return $term;
		}

		if ( 'plugin_category' == $term->taxonomy ) {
			$term->name = esc_html( translate_with_gettext_context( html_entity_decode( $term->name ), 'Plugin Category Name', 'wporg-plugins' ) );
		} elseif ( 'plugin_section' == $term->taxonomy ) {
			$original   = $term->name;
			$term->name = esc_html( translate_with_gettext_context( html_entity_decode( $term->name ), 'Plugin Section Name', 'wporg-plugins' ) );

			// Some strings already exist without the context, try those.
			if ( $term->name === $original ) {
				$term->name = esc_html( translate( html_entity_decode( $term->name ), 'wporg-plugins' ) );
			}

			if ( $term->description ) {
				$term->description = esc_html( translate_with_gettext_context( html_entity_decode( $term->description ), 'Plugin Section Description', 'wporg-plugins' ) );
			}
		} elseif ( 'plugin_business_model' == $term->taxonomy ) {
			$term->name = esc_html( translate_with_gettext_context( html_entity_decode( $term->name ), 'Plugin Business Model', 'wporg-plugins' ) );

			if ( $term->description ) {
				// Without context, as that's how it's been used for a while.
				$term->description = esc_html( translate( html_entity_decode( $term->description ), 'wporg-plugins' ) );
			}
		}

		return $term;
	}

	/**
	 * A private method to hold a list of the strings contained within the Database.
	 *
	 * This function is never called, and only exists so that out pot tools can detect the strings.
	 *
	 * @ignore
	 */
	private function static_strings() {

		// Category terms.
		_x( 'Accessibility', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'Advertising', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'Analytics', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'Arts & Entertainment', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'Authentication', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'Business', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'Calendar & Events', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'Communication', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'Contact Forms', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'Customization', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'Discussion & Community', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'eCommerce', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'Editor & Writing', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'Education & Support', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'Language Tools', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'Maps & Location', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'Media', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'Multisite', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'Performance', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'Ratings & Reviews', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'Security & Spam Protection', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'SEO & Marketing', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'Social & Sharing', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'Taxonomy', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'User Management', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'Utilities & Tools', 'Plugin Category Name', 'wporg-plugins' );

		// Section terms.
		_x( 'Adopt me', 'Plugin Section Name', 'wporg-plugins' );
		_x( 'Beta plugins', 'Plugin Section Name', 'wporg-plugins' );
		_x( 'Block-Enabled plugins', 'Plugin Section Name', 'wporg-plugins' );
		_x( 'My favorites', 'Plugin Section Name', 'wporg-plugins' );
		_x( 'Featured plugins', 'Plugin Section Name', 'wporg-plugins' );
		_x( 'Popular plugins', 'Plugin Section Name', 'wporg-plugins' );
		/* translators: Block plugins are single-block plugins, not block-enabled plugins. */
		_x( 'Block plugins', 'Plugin Section Name', 'wporg-plugins' );
		_x( 'New plugins', 'Plugin Section Name', 'wporg-plugins' );
		_x( 'Recently updated plugins', 'Plugin Section Name', 'wporg-plugins' );
		_x( 'Preview-Enabled plugins', 'Plugin Section Name', 'wporg-plugins' );

		// Section descriptions.
		_x( 'Plugins that have been offered for adoption by others.', 'Plugin Section Description', 'wporg-plugins' );
		_x( 'Beta plugins are in development for possible inclusion in a future version of WordPress.', 'Plugin Section Description', 'wporg-plugins' );
		_x( 'The below plugins have been marked as favorites.', 'Plugin Section Description', 'wporg-plugins' );
		_x( 'Plugins that offer blocks for the block-based editor.', 'Plugin Section Description', 'wporg-plugins' );
		_x( 'Block Plugins as used in the Block Directory.', 'Plugin Section Description', 'wporg-plugins' );

		// Business model descriptions.
		__( 'These plugins are developed and supported by a community.', 'wporg-plugins' );
		__( 'These plugins are free, but also have paid versions available.', 'wporg-plugins' );
	}
}
