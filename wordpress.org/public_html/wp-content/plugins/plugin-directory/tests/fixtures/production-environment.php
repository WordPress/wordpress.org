<?php
/**
 * Production environment fixture for shortcode tests.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

namespace WordPressdotorg\Plugin_Directory\Shortcodes;

/**
 * Forces the production path in the shortcode namespace.
 *
 * @return string The environment type.
 */
function wp_get_environment_type() {
	return 'production';
}
