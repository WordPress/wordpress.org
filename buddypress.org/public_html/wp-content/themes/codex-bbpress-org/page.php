<?php get_header(); ?>
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
				<h3 id="post-<?php the_ID(); ?>"><?php the_title(); ?></h3>
				<div style="margin-bottom: 20px;"><?php echo codex_get_breadcrumb(); ?></div>
				<?php if ( get_the_content() ) :

						the_content( __( '<p class="serif">Read the rest of this page &rarr;</p>', 'buddypress' ) );

						wp_link_pages( array( 'before' => __( '<p><strong>Pages:</strong> ', 'buddypress' ), 'after' => '</p>', 'next_or_number' => 'number') );

					else : ?>

						<p>This page has no content of its own, but contains some sub-pages listed in the sidebar.</p>
						<p>Please edit this page and add something helpful!</p>

				<?php endif; ?>

<?php endwhile;  endif;?>

				<hr class="hidden" />

<?php
	global $post;

	$args         = array( 'order' => 'ASC', );
	$revisions    = wp_get_post_revisions( get_queried_object_id(), $args );	
	$post_authors = array( $post->post_author => 1 );
	foreach( (array)$revisions as $revision ) {
		$post_authors[$revision->post_author] += 1;
	}
	asort( $post_authors, SORT_NUMERIC );

	global $codex_contributors;
	$codex_contributors = array_reverse( $post_authors, true );
?>
				
<?php locate_template( array( 'sidebar.php' ), true ); ?>

<?php get_footer(); ?>
