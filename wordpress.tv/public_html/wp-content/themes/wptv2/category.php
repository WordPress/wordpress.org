<?php
/*
 * Category Archives
 *
 * Used for categories display, especially the to-do category.
 * Fallback to others as well.
 */

global $wp_query, $wptv;
get_header();

/* The below if statement makes sure the hero is hidden on:
 * - Second, third, etc pages.
 * - If found posts is not more than 6
 */

if ( get_query_var( 'paged' ) < 2 && $wp_query->found_posts > 6 ) : ?>
<div class="wptv-hero group">
	<div class="container">

		<h2 class="page-title"><?php single_term_title(); ?></h2>
		<div class="desc"><?php echo category_description(); ?></div>

		<?php if ( have_posts() ) : the_post(); ?>

			<div class="main-video">
				<?php $wptv->the_video(); ?>
				<h3>
					<a href="<?php the_permalink(); ?>" rel="bookmark" title="<?php echo esc_attr( sprintf( __('Permanent Link to %s', 'wptv'), get_the_title() ) ); ?>" class="video-title"><?php the_title(); ?></a>
					<?php $wptv->the_event( '<strong class="video-event">', '</strong>' ); ?>
				</h3>
			</div><!-- .main-video -->

		<?php endif; // have_posts ?>

		<div class="secondary-videos">
			<ul>
				<?php while ( have_posts() && $wp_query->current_post < 5 ) : the_post(); ?>
				<li class="group">
					<a href="<?php the_permalink(); ?>" rel="bookmark" title="<?php echo esc_attr( sprintf(__('Permanent Link to %s', ''), get_the_title() ) ); ?>" >
						<span class="video-thumbnail">
							<img src="<?php $wptv->the_video( true, true ); ?>" />
						</span>
						<span class="video-title"><?php the_title(); ?></span>
						<?php $wptv->the_event( '<strong class="video-event">', '</strong>' ); ?>
					</a>
				</li>
				<?php endwhile; ?>
			</ul>
		</div><!-- .secondary-videos -->

	</div>
</div><!-- .wptv-hero -->

<?php else : // get_query_var(paged) < 2 ?>

<div class="wptv-hero group">
	<div class="container">
		<h2 class="page-title"><?php single_cat_title(); ?></h2>
		<div class="desc"><?php echo category_description(); ?></div>
	</div>
</div>

<?php endif; // paged ?>

<div class="container">
	<div class="primary-content">

		<?php if ( have_posts() ) : ?>

			<h3><?php esc_html_e( 'Latest Videos', 'wptv' ); ?></h3>
			<ul class="video-list four-col">

				<?php while ( have_posts() ) : the_post() ; ?>
				<li>
					<a href="<?php the_permalink(); ?>">
						<span class="video-thumbnail"><?php $wptv->the_video_image( 50, null, false ); ?></span>
						<span class="video-title"><?php the_title(); ?></span>
					</a>
				</li>
				<?php endwhile; ?>

			</ul>

			<?php get_template_part( 'pagination' ); ?>

		<?php else : // have_posts ?>
			<p><?php esc_html_e( 'Sorry, no posts were found in this category.', 'wptv' ); ?></p>
		<?php endif; ?>

	</div><!-- .primary-content -->
	<?php get_sidebar(); ?>
</div><!-- .container -->

<?php get_footer(); ?>