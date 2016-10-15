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

<main id="main" class="site-main" role="main">

	<section class="error-404 not-found">
		<header class="page-header">
			<h1 class="page-title"><?php _e( 'Oops! That page can&rsquo;t be found.', 'wporg-forums' ); ?></h1>
		</header><!-- .page-header -->

		<div class="page-content">
			<p><?php printf( __( 'Try searching from the field above, or go to the <a href="%s">home page</a>.', 'wporg-forums' ), get_home_url() ); ?></p>
		</div>
	</section>
</main>

<?php get_footer(); ?>
