<div class="site-branding">
	<div class="wrap">

		<?php the_custom_logo(); ?>

		<div class="site-branding-text">
			<?php if ( is_front_page() ) : ?>
				<h1 class="site-title">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
						<?php bloginfo( 'name' ); ?>
					</a>
				</h1>

			<?php else : ?>

				<p class="site-title">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
						<?php bloginfo( 'name' ); ?>
					</a>
				</p>
			<?php endif; ?>

			<p class="site-description">
				<?php
				// translators: Date format, see https://php.net/date.
				$date_format    = _x( 'F dS, Y', 'Site description date format' );
				$formatted_date = date_i18n( $date_format, strtotime( '2018-05-27' ) );

				printf(
					// translators: 1: the formatted date; e.g., "May 27th, 2018".
					__( 'WordPress turns 15 years old on %1$s.', 'wp15' ),
					$formatted_date
				);

				?>
			</p>
		</div>

		<?php if ( ( twentyseventeen_is_frontpage() || ( is_home() && is_front_page() ) ) && ! has_nav_menu( 'top' ) ) : ?>
			<a href="#content" class="menu-scroll-down">
				<?php echo twentyseventeen_get_svg( array( 'icon' => 'arrow-right' ) ); ?>
				<span class="screen-reader-text">
					<?php _e( 'Scroll down to content', 'twentyseventeen' ); ?>
				</span>
			</a>
		<?php endif; ?>

	</div>
</div>
