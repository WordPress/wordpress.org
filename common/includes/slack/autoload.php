<?php

spl_autoload_register( function( $class ) {
    if ( 0 === strpos( $class, 'Dotorg\\Slack\\' ) ) {
        require __DIR__ . '/' . strtolower( strtr( $class, array( 'Dotorg\\Slack\\' => '', '_' => '-', '\\' => '/' ) ) ) . '.php';
    }
});

