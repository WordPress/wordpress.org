<?php
/**
 * The main template file.
 *
 * @package wporg-login
 */

// Silence is Golden. If we're at this point, then no template exists for the given request yet.
if ( ! function_exists( 'wp_safe_redirect' ) ) exit;
wp_safe_redirect( '/' );

