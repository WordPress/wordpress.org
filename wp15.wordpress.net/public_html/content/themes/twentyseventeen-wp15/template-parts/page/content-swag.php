<?php
use WP15\Theme;
?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<h1>
			<?php
			/* translators: "Swag" is a term for promotional items. */
			esc_html_e( 'Swag', 'wp15' );
			?>
		</h1>
	</header><!-- .entry-header -->
	<div class="entry-content">

		<h2>
			<?php esc_html_e( 'Print your own', 'wp15' ); ?>
		</h2>

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



	</div><!-- .entry-content -->
</article><!-- #post-## -->
