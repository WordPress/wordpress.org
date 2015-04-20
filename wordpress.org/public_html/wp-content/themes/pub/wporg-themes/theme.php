<article id="post-<?php echo esc_attr( $theme->slug ); ?>" class="theme hentry">
	<a class="url" href="<?php echo home_url( $theme->slug . '/' ); ?>" rel="bookmark" tabindex="-1">
		<?php if ( $theme->screenshot_url ) { ?>
		<div class="theme-screenshot">
			<img src="<?php echo esc_url( $theme->screenshot_url ); ?>?w=572&amp;strip=all" alt="" />
		</div>
		<?php } else { ?>
		<div class="theme-screenshot blank"></div>
		<?php } ?>
		<span class="more-details"><?php _ex( 'More Info', 'theme', 'wporg-themes' ); ?></span>
		<div class="theme-author"><?php printf( _x( 'By %s', 'theme author', 'wporg-themes' ), '<span class="author">' . esc_html( $theme->author->display_name ) . '</span>' ); ?></div>
		<h3 class="theme-name entry-title"><?php echo esc_html( $theme->name ); ?></h3>
	</a>
	<div class="theme-actions">
		<a class="button button-primary preview install-theme-preview" href="<?php echo esc_url( $theme->download_link ); ?>"><?php esc_html_e( 'Download', 'wporg-themes' ); ?></a>
	</div>
</article>