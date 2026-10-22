<?php
use function WordPressdotorg\Make\Breathe_2024\{ breathe_content_nav };

if ( ! is_single() ) {
	breathe_content_nav( 'nav-below' );
}
