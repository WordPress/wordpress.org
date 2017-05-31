<?php
/**
 * Template Name: WordCamp Schedule
 *
 * A custom page template for the Upcoming WordCamp schedule.
 *
 */

get_header(); ?>

		<div id="container" class="wc-schedule">
			<div id="content" role="main">

				<?php if ( have_posts() ) : the_post(); ?>

				<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
					<h1 class="entry-title"><?php the_title(); ?></h1>
					<div class="entry-content">
						<?php the_content(); ?>
					</div><!-- .entry-content -->
				</div><!-- #post-## -->

				<?php endif; // end of the loop. ?>

					<?php // Get the upcoming approved (published) WordCamps
					if ( function_exists( 'wcpt_has_wordcamps' ) &&
						wcpt_has_wordcamps( array(
							'post_status' => WordCamp_Loader::get_public_post_statuses(),
							'posts_per_page' => -1,
							'meta_key'       => 'Start Date (YYYY-mm-dd)',
							'orderby'        => 'meta_value',
							'order'          => 'ASC',
							'meta_query'     => array( array(
								'key'        => 'Start Date (YYYY-mm-dd)',
								'value'      => strtotime( '-2 days' ),
								'compare'    => '>'
							) )
						) )
					) :
						global $wcpt_template;
						$wordcamps = WordCamp_Central_Theme::group_wordcamps_by_year( $wcpt_template->posts );
					?>

					<?php foreach ( $wordcamps as $year => $posts ) : ?>
						<h3 class="wc-schedule-year"><?php echo esc_html( $year ); ?></h3>

						<ul class="wc-schedule-list">
							<?php foreach ( $posts as $post ) :	setup_postdata( $post ); ?>

								<li>
									<a href="<?php echo esc_url( WordCamp_Central_Theme::get_best_wordcamp_url( $post->ID ) ); ?>">
										<?php if ( has_post_thumbnail() ) : ?>
											<?php the_post_thumbnail( 'wccentral-thumbnail-small', array( 'class' => 'wc-image' ) ); ?>
										<?php else : ?>
											<div class="wc-image wp-post-image wordcamp-placeholder-thumb" title="<?php the_title(); ?>"></div>
										<?php endif; ?>

										<h2 class="wc-title"><?php wcpt_wordcamp_title(); ?></h2>
										<span class="wc-country"><?php wcpt_wordcamp_location( $post->ID ); ?></span>

										<span class="wc-date">
											<?php WordCamp_Central_Theme::the_wordcamp_date( $post->ID, true ); ?>
										</span>
									</a>
								</li>

							<?php endforeach; // $posts as post ?>
						</ul>
					<?php wp_reset_postdata(); endforeach; ?>

				<a href="<?php echo home_url( '/schedule/past-wordcamps/' ); ?>" class="wc-schedule-more">Past WordCamps &rarr;</a>

				<?php endif; // wcpt_has_wordcamps ?>

				<h2>Keeping Track of Upcoming WordCamps</h2>

				<p>In addition to the list above, there are a few other ways you can keep track of upcoming WordCamps:</p>

				<ul>
					<li><strong>RSS Feed</strong> -- Learn <a href="https://central.wordcamp.org/news/2013/12/30/rss-feed-now-available-for-newly-announced-wordcamps/">how to subscribe via RSS</a>.</li>
					<li>
						<strong>Android app</strong> --
						<a href="https://play.google.com/store/apps/details?id=org.wordcamp.android">Install it for free</a>,
						or <a href="https://github.com/wordpress-mobile/WordCamp-Android">contribute on GitHub</a>.
					</li>
					<li><strong>ICS Calendar</strong> -- Add this URL as a remote calendar in your calendar application to subscribe: <?php echo esc_url( site_url( 'calendar.ics' ) ); ?></li>
					<li>
						<strong>JSON API</strong> --
						This can be used by developers of mobile apps, websites, etc: <?php echo esc_url( get_rest_url( null, 'wp/v2/wordcamps' ) ); ?>.
						If you'd like to include meetup events too, then you may want to use <a href="https://codex.wordpress.org/WordPress.org_API#Events">api.wordpress.org/events</a> instead.
					</li>
				</ul>

			</div><!-- #content -->
		</div><!-- #container -->

<?php
	get_sidebar( 'schedule' );
	get_footer();
