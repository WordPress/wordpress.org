<?php
/**
 * WordCamp.tv Index Fallback
 *
 * It will be weird if somebody sees this (but okay if 404)
 *
 * @global WP_Query          $wp_query
 * @global WP_Post           $post
 * @global WordPressTV_Theme $wptv
 */

get_header();
global $wp_query, $post, $wptv;

if ( have_posts() ) :
?>

	<div class="wptv-hero">
		<h1 class="page-title"><?php esc_html_e( 'Archives', 'wptv' ); ?></h1>
	</div>
	<div class="container">
		<div class="primary-content">

			<ul class="archive video-list">

				<?php while ( have_posts() ) : the_post(); ?>
				<li>
					<a href="<?php the_permalink(); ?>" class="video-thumbnail">
						<?php $wptv->the_video_image( 50, null, false ); ?>
					</a>

					<div class="video-description">
						<div class="video-info">

							<h2 class="video-meta-heading"><?php esc_html_e( 'Published', 'wptv' ); ?></h2>
							<p class="video-date"><?php the_date(); ?></p>

							<?php if ( $post->post_excerpt ) : ?>
								<div class="video-description"><?php the_excerpt(); ?></div>
							<?php
								endif;

								$wptv->the_terms( 'event',    '<h2 class="video-meta-heading">Event</h2><p class="video-event">',       '<br /> ', '</p>' );
								$wptv->the_terms( 'speakers', '<h2 class="video-meta-heading">Speakers</h2><p class="video-speakers">', '<br /> ', '</p>' );
								$wptv->the_terms( 'post_tag', '<h2 class="video-meta-heading">Tags</h2><p class="video-tags">',         '<br /> ', '</p>' );
								$wptv->the_terms( 'language', '<h2 class="video-meta-heading">Language</h2><p class="video-lang">',     '<br /> ', '</p>' );
							?>
						</div><!-- .video-info -->
					</div>
				</li>
				<?php endwhile; // have_posts ?>

			</ul><!-- .archive.video-list -->

			<?php get_template_part( 'pagination' ); ?>

		</div>

		<div class="secondary-content">
			<?php get_sidebar(); ?>
		</div><!-- .secondary-content -->
	</div><!-- .container -->

<?php else : // have_posts ?>

	<div class="wptv-hero">
		<h2 class="page-title"><?php esc_html_e( 'Whoops!', 'wptv' ); ?></h2>
	</div>
	<div class="container">
		<div class="primary-content">

			<div class="fourOHfour">
				<h2 class="center"><?php esc_html_e( 'Uh oh, someone made a mistake!' ); ?></h2>
				<p><?php esc_html_e( 'These sorts of things happen&hellip;' ); ?></p>
				<p class="center"><?php esc_html_e( 'Try searching for what you were looking for.' ); ?></p>
				<p><?php echo get_search_form(); ?></p>
				<p><?php printf ( __( 'Or, <a href="%s">visit the homepage</a> to start a fresh journey.', 'wptv' ), '/' ); ?></p>
				<p>
					<img src="<?php echo get_stylesheet_directory_uri(); ?>/i/michael-pick-stashes-a-guinness.gif" alt="" /><br />
					Photo animation credit: <a href="https://markjaquith.com/">Mark Jaquith</a>.
				</p>
			</div>
		</div>
	</div><!-- container -->

<?php
endif;

get_footer();
