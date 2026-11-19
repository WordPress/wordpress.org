<?php
/**
 * Browse Happy user agent parser test page.
 *
 * This is a standalone diagnostic page: WordPress is not loaded, so request data is
 * never slashed and the `esc_*()` escaping helpers are unavailable.
 *
 * phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
 *
 * @package BrowseHappy
 */

include dirname( __FILE__ ) . '/parse.php';
$user_agent = filter_var( $_SERVER['HTTP_USER_AGENT'] ?? '', FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW );

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- htmlspecialchars() escapes; no esc_html() here.
echo htmlspecialchars( $user_agent, ENT_QUOTES ) . '<br/><br/>';

$output = browsehappy_parse_user_agent( $user_agent );

foreach ( $output as $k => $v ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- htmlspecialchars() escapes; no esc_html() here.
	echo htmlspecialchars( $k . ' = ' . ( is_bool( $v ) ? (int) $v : $v ), ENT_QUOTES ) . '<br/>';
}
