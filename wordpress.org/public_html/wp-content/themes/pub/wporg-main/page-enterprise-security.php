<?php
/**
 * Template Name: Enterprise-Security
 *
 * Page template for displaying the Enterprise page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

// rm the page-child class from this page
add_filter( 'body_class', function ( $classes ) {
    return array_diff( $classes, array( 'page-child' ) );
} );

// Noindex until ready.
add_filter( 'wporg_noindex_request', '__return_true' );

/* See inc/page-meta-descriptions.php for the meta description for this page. */

get_header();
the_post();
?>



<?php
get_footer();

