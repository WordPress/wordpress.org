<?php
/**
 * Stand-in for the Theme Check plugin's interface, which the test environment does not load.
 *
 * @package theme-directory
 */

declare( strict_types = 1 );

/**
 * The interface every Theme Check check implements.
 */
// phpcs:ignore PEAR.NamingConventions.ValidClassName.StartWithCapital -- Named by the Theme Check plugin.
interface themecheck {

	/**
	 * Runs the check against a theme.
	 *
	 * @param array $php_files   The theme's PHP files.
	 * @param array $css_files   The theme's CSS files.
	 * @param array $other_files The theme's remaining files.
	 * @return bool Whether the theme passed.
	 */
	public function check( $php_files, $css_files, $other_files );

	/**
	 * Returns the messages the check produced.
	 *
	 * @return array
	 */
	public function getError(); // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Named by the Theme Check plugin.
}
