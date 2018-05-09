<?php
use WP15\Theme;
?>

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
						<h2>
							<?php esc_html_e( 'Print your own', 'wp15' ); ?>
						</h2>

						<p><?php esc_html_e( 'These 15th Anniversary logos and files are available for download for folks who want to print their own swag:', 'wp15' ); ?></p>

						<ul class="downloads-wrapper">
							<?php foreach ( Theme\get_swag_download_items() as $item ) : ?>
								<li class="downloads-item">
									<div class="downloads-item-preview">
										<img src="<?php echo esc_attr( $item['preview_image_url'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" />
									</div>
									<div class="downloads-item-header">
										<strong><?php echo esc_html( $item['title'] ); ?></strong>
									</div>
									<?php if ( ! empty( $item['files'] ) ) : ?>
										<ul class="downloads-item-files">
											<?php foreach ( $item['files'] as $file ) : ?>
												<li>
													<a href="<?php echo esc_attr( $file['url'] ); ?>"><?php echo esc_html( $file['name'] ); ?></a>
												</li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>

						<img class="wp15-confetti-divider" src="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/images/confetti-divider.svg" alt="" />

						<h2>
							<?php esc_html_e( 'Purchase', 'wp15' ); ?>
						</h2>

						<p>
							<?php
							printf(
								wp_kses_post( __( 'Check out the <a href="%s">WordPress Swag Store</a> if you\'d like to purchase WordPress 15th anniversary swag.', 'wp15' ) ),
								'https://mercantile.wordpress.org/product-category/wordpress-15/'
							);
							?>
						</p>

						<?php echo wp_oembed_get( 'https://mercantile.wordpress.org/product/wordpress-15th-anniversary-mug/' ); ?>
						<?php echo wp_oembed_get( 'https://mercantile.wordpress.org/product/wordpress-15th-anniversary-tshirt/' ); ?>

					</div>

				</article>
			</main>
		</div>
	</div>

<?php get_footer();
