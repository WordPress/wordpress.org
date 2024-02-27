<?php
/**
 * The template for displaying 404 pages (not found).
 *
 * @link https://codex.wordpress.org/Creating_an_Error_404_Page
 *
 * @package WordPressdotorg\Forums
 */

namespace WordPressdotorg\Forums;

get_header(); ?>

<main id="main" class="wp-block-group alignfull site-main is-layout-constrained wp-block-group-is-layout-constrained" role="main">

		<div class="wp-block-group alignwide is-layout-flow wp-block-group-is-layout-flow">

	<section class="error-404 not-found">
		<header class="page-header">
			<h1 class="page-title"><?php _e( 'Oops! That page can&rsquo;t be found.', 'wporg-forums' ); ?></h1>
		</header><!-- .page-header -->

		<div class="page-content">
			<p><?php printf( __( 'Try searching from the field above, or go to the <a href="%s">home page</a>.', 'wporg-forums' ), get_home_url() ); ?></p>
		</div>
	</section>

	</div>

</main>

<?php get_footer(); ?>
