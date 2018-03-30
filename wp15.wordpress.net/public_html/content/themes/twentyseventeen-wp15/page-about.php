<?php get_header(); ?>

<div class="wrap">
	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

				<header class="entry-header">
					<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
				</header>

				<div class="entry-content">
					<h1>
						<?php _e( 'Celebrate WordPress with parties all over the globe on May 27, 2018 in honor of our 15th anniversary.', 'wp15' ); ?>
					</h1>

					<img src="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/images/confetti-divider.svg" alt="Confetti with the WordPress logo and WP15 color scheme" />

					<?php echo do_shortcode( '[wp15_meetup_events]' ); ?>

					<img src="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/images/confetti-divider.svg" alt="Confetti with the WordPress logo and WP15 color scheme" />

					<p>
						<?php printf(
							__( 'Check out <a href="%s">this post about our 10th anniversary</a>.', 'wp15' ),
							'https://wordpress.org/news/2013/05/ten-good-years/'
						); ?>
					</p>
				</div>

			</article>
		</main>
	</div>
</div>

<?php get_footer();
