<?php
/**
 * Debug page for the Browse Happy parser: prints what it makes of the
 * caller's own user agent.
 *
 * Standalone page; WordPress is never loaded here, so its sanitizers and
 * escapers do not exist. Everything printed goes out through htmlspecialchars().
 *
 * phpcs:disable WordPress.Security.ValidatedSanitizedInput, WordPress.Security.EscapeOutput
 *
 * @package BrowseHappy
 */

echo htmlspecialchars( $_SERVER['HTTP_USER_AGENT'], ENT_QUOTES ) . "<br/><br/>";

include dirname( __FILE__ ) . '/parse.php';

$output = browsehappy_parse_user_agent( $_SERVER['HTTP_USER_AGENT'] );

foreach ( $output as $k => $v )
	echo htmlspecialchars( $k . ' = ' . ( is_bool( $v ) ? (int) $v : $v ), ENT_QUOTES ) . "<br/>";
