<?php

echo esc_html( $_SERVER['HTTP_USER_AGENT'] ) . "<br/><br/>";

include dirname( __FILE__ ) . '/parse.php';

$output = browsehappy_parse_user_agent( $_SERVER['HTTP_USER_AGENT'] );

foreach ( $output as $k => $v )
	echo esc_html( $k . ' = ' . ( is_bool( $v ) ? (int) $v : $v ) ) . "<br/>";
