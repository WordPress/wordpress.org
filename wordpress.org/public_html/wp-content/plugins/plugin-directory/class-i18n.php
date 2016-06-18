<?php
namespace WordPressdotorg\Plugin_Directory;

/**
 * Various translation functions for the directory.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class i18n {

	/**
	 * Translate a Term Name.
	 *
	 * @param \WP_Term $term The Term object to translate.
	 * @return \WP_Term The term object with a translated `name` field.
	 */
	static function translate_term( $term ) {
		if ( 'plugin_category' == $term->taxonomy ) {
			$term->name = esc_html( translate_with_gettext_context( html_entity_decode( $term->name ), 'Plugin Category Name', 'wporg-plugins' ) );
		} elseif ( 'plugin_section' == $term->taxonomy ) {
			$term->name = esc_html( translate_with_gettext_context( html_entity_decode( $term->name ), 'Plugin Section Name', 'wporg-plugins' ) );
		} elseif ( 'plugin_business_model' == $term->taxonomy ) {
			$term->name = esc_html( translate_with_gettext_context( html_entity_decode( $term->name ), 'Plugin Business Model', 'wporg-plugins' ) );
		}

		return $term;
	}

	/**
	 * A private method to hold a list of the strings contained within the Database.
	 *
	 * This function is never called, and only exists so that out pot tools can detect the strings.
	 * @ignore
	 */
	private function static_strings() {

		// Category Terms.
		_x( 'Accessibility',              'Plugin Category Name', 'wporg-plugins' );
		_x( 'Advertising',                'Plugin Category Name', 'wporg-plugins' );
		_x( 'Analytics',                  'Plugin Category Name', 'wporg-plugins' );
		_x( 'Arts & Entertainment',       'Plugin Category Name', 'wporg-plugins' );
		_x( 'Authentication',             'Plugin Category Name', 'wporg-plugins' );
		_x( 'Business',                   'Plugin Category Name', 'wporg-plugins' );
		_x( 'Calendar & Events',          'Plugin Category Name', 'wporg-plugins' );
		_x( 'Communication',              'Plugin Category Name', 'wporg-plugins' );
		_x( 'Contact Forms',              'Plugin Category Name', 'wporg-plugins' );
		_x( 'Customization',              'Plugin Category Name', 'wporg-plugins' );
		_x( 'Discussion & Community',     'Plugin Category Name', 'wporg-plugins' );
		_x( 'eCommerce',                  'Plugin Category Name', 'wporg-plugins' );
		_x( 'Editor & Writing',           'Plugin Category Name', 'wporg-plugins' );
		_x( 'Education & Support',        'Plugin Category Name', 'wporg-plugins' );
		_x( 'Language Tools',             'Plugin Category Name', 'wporg-plugins' );
		_x( 'Maps & Location',            'Plugin Category Name', 'wporg-plugins' );
		_x( 'Media',                      'Plugin Category Name', 'wporg-plugins' );
		_x( 'Multisite',                  'Plugin Category Name', 'wporg-plugins' );
		_x( 'Performance',                'Plugin Category Name', 'wporg-plugins' );
		_x( 'Ratings & Reviews',          'Plugin Category Name', 'wporg-plugins' );
		_x( 'Security & Spam Protection', 'Plugin Category Name', 'wporg-plugins' );
		_x( 'SEO & Marketing',            'Plugin Category Name', 'wporg-plugins' );
		_x( 'Social & Sharing',           'Plugin Category Name', 'wporg-plugins' );
		_x( 'Taxonomy',                   'Plugin Category Name', 'wporg-plugins' );
		_x( 'User Management',            'Plugin Category Name', 'wporg-plugins' );
		_x( 'Utilities & Tools',          'Plugin Category Name', 'wporg-plugins' );

		// Section Terms.
		_x( 'Adopt Me',     'Plugin Section Name', 'wporg-plugins' );
		_x( 'Beta',         'Plugin Section Name', 'wporg-plugins' );
		_x( 'My Favorites', 'Plugin Section Name', 'wporg-plugins' );
		_x( 'Featured',     'Plugin Section Name', 'wporg-plugins' );
		_x( 'Popular',      'Plugin Section Name', 'wporg-plugins' );
	}
}
