<?php

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Standalone script; WordPress is never loaded here, so esc_html() does not exist.
echo htmlspecialchars( $_SERVER['HTTP_USER_AGENT'], ENT_QUOTES ) . "<br/><br/>";

include dirname( __FILE__ ) . '/parse.php';

$output = browsehappy_parse_user_agent( $_SERVER['HTTP_USER_AGENT'] );

foreach ( $output as $k => $v )
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Standalone script; WordPress is never loaded here, so esc_html() does not exist.
	echo htmlspecialchars( $k . ' = ' . ( is_bool( $v ) ? (int) $v : $v ), ENT_QUOTES ) . "<br/>";
