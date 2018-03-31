<?php get_header(); ?>

<div class="wrap">
	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

				<header class="entry-header">
					<h1 class="entry-title">
						<?php

						// translators: The name of the page that describes the WP15 celebrations.
						esc_html_e( 'About', 'wp15' );

						?>
					</h1>
				</header>

				<div class="entry-content">
					<h1>
						<?php esc_html_e( 'People all over the world are celebrating the WordPress 15th Anniversary on May 27, 2018. Join us!', 'wp15' ); ?>
					</h1>

					<img src="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/images/confetti-divider.svg" alt="<?php esc_attr_e( 'Confetti with the WordPress logo and WP15 color scheme', 'wp15' ); ?>" />

					<?php echo do_shortcode( '[wp15_meetup_events]' ); ?>

					<img src="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/images/confetti-divider.svg" alt="<?php esc_attr_e( 'Confetti with the WordPress logo and WP15 color scheme', 'wp15' ); ?>" />

					<p class="wp10-nostalgia">
						<?php printf(
							wp_kses_data( __( 'Check out <a href="%s">this post about the WordPress 10th anniversary</a>.', 'wp15' ) ),
							'https://wordpress.org/news/2013/05/ten-good-years/'
						); ?>
					</p>
				</div>

			</article>
		</main>
	</div>
</div>

<?php get_footer();
