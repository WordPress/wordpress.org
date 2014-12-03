<?php
/*
 * Event Taxonomy Archives
 *
 * Displays for event listings like individual WordCamps.
 *
 * @todo cleanup and merge with category.php
 */
global $wp_query, $wptv;
get_header();

// If you're wondering why the below if statement, see category.php

if ( get_query_var( 'paged' ) < 2 && $wp_query->found_posts > 6 ) : ?>
<div class="wptv-hero group">
	<div class="container">

		<?php get_template_part( 'breadcrumbs' ); ?>
		<h2 class="page-title"><?php single_term_title(); ?></h2>

		<?php if ( have_posts() ) : the_post(); ?>

			<div class="main-video">
				<?php $wptv->the_video(); ?>
				<h3>
					<a href="<?php the_permalink(); ?>" rel="bookmark" class="video-title"><?php the_title(); ?></a>
					<?php $wptv->the_event( '<strong class="video-event">', '</strong>' ); ?>
				</h3>
			</div><!-- .main-video -->

		<?php endif; ?>

			<div class="secondary-videos">
			<ul>
				<?php
					for ( $i = 0 ; $i < 5 ; $i++ ) :
						if ( ! have_posts() ) {
							break; // just in case
						}

						the_post();
				?>

				<li class="group">
					<a href="<?php the_permalink(); ?>" rel="bookmark">
						<span class="video-thumbnail">
							<img src="<?php $wptv->the_video( true, true ); ?>" />
						</span>
						<span class="video-title"><?php the_title(); ?></span>
					</a>
				</li>

				<?php endfor; ?>
			</ul>
		</div><!-- .secondary-videos -->
	</div>
</div><!-- .wptv-hero -->

<?php else : // get_query_var(paged) < 2 ?>

<div class="wptv-hero group">
	<div class="container">
		<?php get_template_part( 'breadcrumbs' ); ?>
		<h2 class="page-title"><?php single_term_title(); ?></h2>
	</div>
</div>

<?php endif; // paged ?>

<div class="container">
	<div class="primary-content">

		<ul class="video-list four-col">
			<?php while ( have_posts() ) : the_post(); ?>
				<li>
					<a href="<?php the_permalink(); ?>">
						<span class="video-thumbnail"><?php $wptv->the_video_image(50, null, false); ?></span>
						<span class="video-title"><?php the_title(); ?></span>
					</a>
				</li>
			<?php endwhile; ?>
		</ul>

		<?php get_template_part( 'pagination' ); ?>

	</div><!-- .primary-content -->
	<?php get_sidebar( 'event' ); ?>
</div><!-- .container -->
<?php
get_footer();
