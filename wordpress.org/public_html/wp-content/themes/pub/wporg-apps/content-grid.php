<?php
/**
 * The template used for displaying child page on the grid template.
 *
 * @package wpmobileapps
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

	<div class="col-1-2 feature-text">
		<div>
			<header class="entry-header">
				<h1 class="entry-title"><?php the_title(); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content">
				<?php the_content(); ?>
			</div><!-- .entry-summary -->
		</div>
	</div>

	<div class="col-1-2 feature-image">
		<!-- <div> -->
			<?php echo get_post_meta( $post->ID, 'features_animation', true ); ?>
			<a href="<?php the_permalink(); ?>" title="<?php echo esc_attr( sprintf( __( 'Permalink to %s', 'wpmobileapps' ), the_title_attribute( 'echo=0' ) ) ); ?>" rel="<?php the_ID(); ?>">
				<?php the_post_thumbnail( 'wpmobileapps-grid-thumbnail' ); ?>
			</a>
		<!-- </div> -->
	</div><!-- .entry-thumbnail -->

</article><!-- #post-## -->
