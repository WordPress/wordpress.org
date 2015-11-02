<?php

spl_autoload_register( function( $class ) {
	$class = strtolower( $class );
	if ( 0 === strpos( $class, 'trac_notifications_' ) ) {
		require __DIR__ . '/' . str_replace( '_', '-', $class ) . '.php';
	}
});
