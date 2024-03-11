<?php

global $wp_query;

// If we don't have any posts to display for the archive, then send a 404 status. See #meta4151
if ( ! $wp_query->have_posts() ) {
	status_header( 404 );
	nocache_headers();
}

?>

<main id="main" class="site-main alignwide" role="main">

<header class="page-header">
	<?php
	the_archive_title( '<h1 class="page-title">', '</h1>' );
	the_archive_description( '<div class="taxonomy-description">', '</div>' );
	?>
</header><!-- .page-header -->

<?php
/* Start the Loop */
while ( $wp_query->have_posts() ) {
	the_post();

	get_template_part( 'template-parts/plugin' );
}

if ( ! have_posts() ) {
	get_template_part( 'template-parts/no-results' );
}

the_posts_pagination();

?>

</main><!-- #main -->