<?php get_header(); ?>

<div class="wrap">
	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

				<header class="entry-header">
					<h1 class="entry-title">
						<?php the_title(); ?>
					</h1>
				</header>

				<div class="entry-content">
					<?php esc_html_e( 'Join the conversation by using #WP15 on your favorite social networks.', 'wp15' ); ?>

					<?php echo do_shortcode( '[tagregator hashtag="#WP15"]' ); ?>
				</div>

			</article>
		</main>
	</div>
</div>

<?php get_footer();
