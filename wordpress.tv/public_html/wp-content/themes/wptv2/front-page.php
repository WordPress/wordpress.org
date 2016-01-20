<?php
/**
 * WordPress.tv Front Page Template
 */

$featured_params = array(
	'posts_per_page' => 1,
	'tag'            => 'featured',
);
if ( $sticky_posts = get_option( 'sticky_posts' ) ) {
	$featured_params['post__in'] = $sticky_posts;
}
$featured = new WP_Query( $featured_params );

global $wptv;
get_header(); ?>

<div class="wptv-hero group">
	<div class="container">

		<?php while ( $featured->have_posts() ) : $featured->the_post(); ?>
		<div class="main-video">
			<?php $wptv->the_video(); ?>
			<h3>
				<a href="<?php the_permalink() ?>" rel="bookmark" class="video-title"><?php the_title(); ?></a>
				<?php $wptv->the_event( '<strong class="video-event">', '</strong>' ); ?>
			</h3>
		</div><!-- .main-video -->
		<?php endwhile; // $featured->have_posts ?>

		<div class="secondary-videos">
			<h3>
				<?php esc_html_e( 'WordCampTV', 'wptv' ); ?>
				<a href="<?php echo home_url( '/category/wordcamptv/' ); ?>" class="view-more"><?php _e( 'More &rarr;', 'wptv' ); ?></a>
			</h3>
			<ul>
				<?php
					$featured = new WP_Query( array( // WordCampTV Featured
						'posts_per_page' => 4,
						'post__not_in'   => array( get_the_id() ), // In case the above video is the same
						'category_name'  => 'wordcamptv',
						'tag'            => 'featured',
					) );

					while ( $featured->have_posts() ) :
						$featured->the_post();
				?>

				<li class="group">
					<a href="<?php the_permalink(); ?>" rel="bookmark">
						<span class="video-thumbnail">
							<img src="<?php $wptv->the_video( true, true ); ?>" />
						</span>
						<span class="video-title"><?php the_title(); ?></span>
						<?php $wptv->the_event( '<strong class="video-event">', '</strong>' ); ?>
					</a>
				</li>

				<?php
					endwhile; // $featured->have_posts
					unset( $featured );
				?>
			</ul>
		</div><!-- .secondary-videos -->

	</div><!-- .container -->
</div><!-- .wptv-hero -->

<div class="container">
	<div class="primary-content">

		<!-- Latest Videos -->
		<?php
			if ( have_posts() ) :
		?>
		<h3><?php esc_html_e( 'Latest Videos', 'wptv' ); ?></h3>
		<ul class="video-list four-col">

			<?php while ( have_posts() ) : the_post(); ?>
			<li>
				<a href="<?php the_permalink(); ?>">
					<span class="video-thumbnail"><?php $wptv->the_video_image( 50, null, false ); ?></span>
					<span class="video-title"><?php the_title(); ?></span>
				</a>
			</li>
			<?php endwhile; ?>

		</ul>
		<?php
			endif; // $latest->have_posts

			// Popular Videos
			$popular = new WP_Query( array(
				'posts_per_page' => 8,
				'meta_key'       => 'wptv_post_views',
				'orderby'        => 'meta_value_num',
				'order'          => 'DESC',
				'date_query'     => array(
					'after' => '-180 days',
				),
			) );

			if ( $popular->have_posts() ) :
		?>
		<h3><?php esc_html_e( 'Popular Videos', 'wptv' ); ?></h3>
		<ul class="video-list four-col">

			<?php while ( $popular->have_posts() ) : $popular->the_post(); ?>
			<li>
				<a href="<?php the_permalink(); ?>">
					<span class="video-thumbnail"><?php $wptv->the_video_image( 50, null, false ); ?></span>
					<span class="video-title"><?php the_title(); ?></span>
				</a>
			</li>
			<?php endwhile; ?>

		</ul>
		<?php
			endif; // $popular->have_posts
			unset( $popular );
		?>

	</div><!-- .primary-content -->
	<?php get_sidebar(); ?>
</div><!-- .container-->

<?php
get_footer();
