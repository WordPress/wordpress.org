<?php
/**
 * The template part for displaying a message that posts cannot be found.
 *
 * Learn more: http://codex.wordpress.org/Template_Hierarchy
 *
 * @package wporg-developer
 */
?>

<section class="no-results not-found">
	<header class="page-header">
		<h1 class="page-title"><?php _e( 'Nothing Found', 'wporg' ); ?></h1>
	</header><!-- .page-header -->

	<div class="reference-landing">
		<div class="search-guide section clear">
			<h4 class="ref-intro">
			<?php if ( is_search() ) {
				_e( 'Sorry, but nothing matched your search terms. Please try again with some different keywords.', 'wporg' );
			} else {
				_e( 'It seems we can&rsquo;t find what you&rsquo;re looking for. Perhaps searching can help.', 'wporg' );
			} ?>
			</h4>
			<?php get_search_form(); ?>
		</div><!-- /search-guide -->
	</div><!-- .reference-landing -->
</section><!-- .no-results -->
