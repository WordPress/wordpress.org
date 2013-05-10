<?php

echo htmlspecialchars( $_SERVER['HTTP_USER_AGENT'], ENT_QUOTES ) . "<br/><br/>";

include dirname( __FILE__ ) . '/parse.php';

$output = browsehappy_parse_user_agent( $_SERVER['HTTP_USER_AGENT'] );

foreach ( $output as $k => $v )
	echo htmlspecialchars( $k . ' = ' . ( is_bool( $v ) ? (int) $v : $v ), ENT_QUOTES ) . "<br/>";
