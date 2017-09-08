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
					'post_status' => WordCamp_Loader::get_public_post_statuses(),
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

		<div class="wc-tweets last">
			<h3><strong>Latest Tweets</strong></h3>

			<div id="wc-tweets-spinner" class="spinner spinner-visible"></div>
			<ul id="wc-tweets-container" class="transparent"></ul>

			<p id="wc-tweets-error" class="hidden" hidden>
				Tweets from <a href="https://twitter.com/wordcamp">@WordCamp</a> are currently unavailable.
			</p>

			<a href="https://twitter.com/wordcamp" class="more">Follow @WordCamp on Twitter &rarr;</a>

			<script id="tmpl-wc-tweet" type="text/html">
				<li>
					<div class="wc-tweet-content">{{{tweet.text}}}</div>

					<p class="wc-tweet-timestamp">
						<a href="https://twitter.com/wordcamp/status/{{tweet.id_str}}">{{tweet.time_ago}}</a>
					</p>

					<ul class="wc-tweet-actions clearfix">
						<li class="wc-tweet-action-reply">
							<a href="https://twitter.com/intent/tweet?in_reply_to={{tweet.id_str}}">
								<span class="wc-tweet-action-icon"></span>
								Reply
							</a>
						</li>

						<li class="wc-tweet-action-retweet">
							<a href="https://twitter.com/intent/retweet?tweet_id={{tweet.id_str}}">
								<span class="wc-tweet-action-icon"></span>
								Retweet
							</a>
						</li>

						<li class="wc-tweet-action-favorite">
							<a href="https://twitter.com/intent/favorite?tweet_id={{tweet.id_str}}">
								<span class="wc-tweet-action-icon"></span>
								Favorite
							</a>
						</li>
					</ul>
				</li>
			</script>
		</div>  <!-- .wc-tweets -->

	</div> <!-- #wc-content-blocks -->

<?php get_footer(); ?>
