<?php
/**
 * Template Name: WordCamp Home
 *
 * The home page template.
 */

get_header(); ?>

	<div id="wc-content-blocks" class="group">


		<div class="wc-upcoming">
			<h3><strong>Upcoming</strong> WordCamps</h3>

			<?php // Get the upcoming WordCamps
			if ( function_exists( 'wcpt_has_wordcamps' ) &&
				wcpt_has_wordcamps( array(
					'posts_per_page' => 5,
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
			?>

			<ul>
			<?php while ( wcpt_wordcamps() ) : wcpt_the_wordcamp(); ?>

				<li>
					<a href="<?php wcpt_wordcamp_permalink(); ?>"><strong><?php wcpt_wordcamp_title(); ?></strong>
						<?php WordCamp_Central_Theme::the_wordcamp_date(); ?>
					</a>
				</li>

			<?php endwhile; // wcpt_wordcamps ?>
			</ul>
			<a href="<?php echo home_url( '/schedule/' ); ?>" class="more">More WordCamps &rarr;</a>

			<?php endif; // wcpt_has_wordcamps ?>

		</div><!-- .wc-upcoming -->

		<div class="wc-news">

			<h3><strong>News</strong></h3>

			<?php
				// Removes links and other post formats from the home page
				// props mfields: http://wordpress.mfields.org/2011/post-format-queries/
				$formats = get_post_format_slugs();
				foreach ( (array) $formats as $i => $format )
					$formats[$i] = 'post-format-' . $format;

				$news = new WP_Query( array( 
					'posts_per_page' => 1, 
					'ignore_sticky_posts' => 1,
					'tax_query' => array(
						array(
							'taxonomy' 	=> 'post_format',
							'field' 	=> 'slug',
							'terms' 	=> $formats,
							'operator' 	=> 'NOT IN',
						)
					)
				) );
			?>
			<?php if ( $news->have_posts() ) : $post_counter = 0; ?>
				<?php while ( $news->have_posts() ) : $news->the_post(); ?>
					<?php $news_class_last = 'last'; // (bool) ( ++$post_counter % 2 ) ? '' : 'last'; ?>

			<div class="news-item <?php echo $news_class_last; ?>">
				<h4><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
				<span class="wc-news-meta">by <?php the_author_posts_link(); ?> on <strong><?php the_date(); ?></strong></span>
				<?php the_excerpt('keep reading'); ?>
				<a href="<?php the_permalink(); ?>" class="wc-news-more">Keep Reading &raquo;</a>
			</div>

				<?php endwhile; ?>
			<?php endif; ?>

			<a href="<?php echo home_url( '/news/' ); ?>" class="more">More News &rarr;</a>	

		</div><!-- .wc-news -->

		<div class="wc-sessions last">
			<h3>WordCamp <strong>Sessions</strong></h3>

			<?php $sessions = WordCamp_Central_Theme::get_sessions(); ?>
			<?php if ( ! empty( $sessions ) ) : ?>
			<ul>
				<?php foreach( $sessions as $session ) : ?>
				<li>
					<?php echo $session['wordcamp_thumb']; ?>
					<a href="<?php echo esc_url( $session['permalink'] ); ?>" class="wc-session-name"><?php echo esc_html( $session['name'] ); ?></a>
					<span class="wc-session-speakers">by <?php echo esc_html( $session['speakers'] ); ?></span>
					<a href="<?php echo esc_url( $session['wordcamp_permalink'] ); ?>" class="wc-session-wordcamp"><?php echo esc_html( $session['wordcamp_title'] ); ?></a>
				</li>
				<?php endforeach; ?>
			</ul>
			<!--<a href="#" class="more">More Speakers &rarr;</a>-->
			<?php else : // ! empty( $sessions ) ?>
			<p>We're sorry, the speakers list is temporarily unavailable.</p>
			<?php endif; ?>
		</div>  <!-- .wc-speakers -->

	</div> <!-- #wc-content-blocks -->
	<?php
		/*
		$photos = WordCamp_Central_Theme::get_photos();
		$main_photo = false;
		shuffle( $photos );

		foreach ( $photos as $key => $photo ) {
			if ( $photo['l_width'] > 473 && $photo['width'] > $photo['height'] ) {
				$main_photo = $photo;
				unset( $photos[$key] );
				break;
			}
		}
		$photos = array_slice( $photos, 0, 5 );
	?>
	<div id="wc-media" class="group">

		<div class="wc-media-photos">
			<h3><a href="http://www.flickr.com/photos/tags/wordcampsf/"><strong>WordCamps</strong> in Photos</a></h3>
			<a href="http://www.flickr.com/photos/tags/wordcampsf/" class="wc-media-more">More Photos &rarr;</a>

			<?php if ( $main_photo ) : ?>
			<a href="<?php echo esc_url( $main_photo['url'] ); ?>" class="wc-media-photo-main">
				<img src="<?php echo esc_url( $main_photo['l_url'] ); ?>" alt="<?php echo esc_attr( $main_photo['title'] ); ?>" title="<?php echo esc_attr( $main_photo['title'] ); ?>" width="446" />
			</a>
			<?php endif; ?>

			<?php foreach ( $photos as $photo ) : ?>
			<a href="<?php echo esc_url( $photo['url'] ); ?>" class="wc-media-photo-thumb">
				<img src="<?php echo esc_url( $photo['t_url'] ); ?>" alt="<?php echo esc_attr( $photo['title'] ); ?>" title="<?php echo esc_attr( $photo['title'] ); ?>" width="75" height=75" />
			</a>
			<?php endforeach; ?>

		</div><!-- .wc-media-photos -->


		<?php $videos = WordCamp_Central_Theme::get_videos(); ?>

		<div class="wc-media-videos">
			<h3><a href="http://wordpress.tv/category/wordcamptv/"><strong>WordCamp</strong>TV</a></h3>
			<a href="http://wordpress.tv/category/wordcamptv/" class="wc-media-more">More @ WordCamp.tv &rarr;</a>
			<ul>
				<?php foreach( $videos as $video ) : ?>
				<li>
					<a href="<?php echo esc_url( $video['permalink'] ); ?>">
						<img src="<?php echo esc_url( $video['thumbnail'] ); ?>" class="wc-media-video-thumb" alt="<?php echo esc_attr( $video['title'] ); ?>" width="128" />
						<h4 class="wc-video-title"><?php echo apply_filters( 'the_title', $video['title'] ); ?></h4>
						<!-- <span class="wc-video-wordcamp">WordCamp Los Angeles</span> -->
					</a>
				</li>
				<?php endforeach; ?>
			</ul>
		</div><!-- .wc-media-videos -->

	</div><!-- .wc-media -->

	*/ ?>

<?php get_footer(); ?>
