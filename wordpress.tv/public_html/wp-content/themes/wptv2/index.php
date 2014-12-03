<?php
/*
 * WordCamp.tv Index Fallback
 *
 * It will be weird if somebody sees this (but okay if 404)
 */

get_header();
global $wp_query, $post, $wptv;

if ( have_posts() ) :
?>

	<div class="wptv-hero">
		<h2 class="page-title"><?php esc_html_e( 'Archives', 'wptv' ); ?></h2>
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

							<h5><?php esc_html_e( 'Published', 'wptv' ); ?></h5>
							<p class="video-date"><?php the_date(); ?></p>

							<?php if ( $post->post_excerpt ) : ?>
								<div class="video-description"><?php the_excerpt(); ?></div>
							<?php
								endif;

								$wptv->the_terms( 'event',    '<h5>Event</h5><p class="video-event">',       '<br /> ', '</p>' );
								$wptv->the_terms( 'speakers', '<h5>Speakers</h5><p class="video-speakers">', '<br /> ', '</p>' );
								$wptv->the_terms( 'post_tag', '<h5>Tags</h5><p class="video-tags">',         '<br /> ', '</p>' );
								$wptv->the_terms( 'language', '<h5>Language</h5><p class="video-lang">',     '<br /> ', '</p>' );
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
					Photo animation credit: <a href="http://markjaquith.com/">Mark Jaquith</a>.
				</p>
			</div>
		</div>
	</div><!-- container -->

<?php
endif;

get_footer();
