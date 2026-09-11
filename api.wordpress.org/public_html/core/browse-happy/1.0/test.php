<?php

// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Standalone test page; WordPress is not loaded, so the WP sanitizers are unavailable. The value is escaped with htmlspecialchars() on output.
echo htmlspecialchars( $_SERVER['HTTP_USER_AGENT'], ENT_QUOTES ) . "<br/><br/>";

include dirname( __FILE__ ) . '/parse.php';

// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Standalone test page; WordPress is not loaded, so the WP sanitizers are unavailable. The value is escaped with htmlspecialchars() on output.
$output = browsehappy_parse_user_agent( $_SERVER['HTTP_USER_AGENT'] );

foreach ( $output as $k => $v )
	echo htmlspecialchars( $k . ' = ' . ( is_bool( $v ) ? (int) $v : $v ), ENT_QUOTES ) . "<br/>";
