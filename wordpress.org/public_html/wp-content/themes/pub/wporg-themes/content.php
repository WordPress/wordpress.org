<?php global $theme; ?>
<article class="theme">
	<div class="theme-screenshot">
		<img src="<?php echo esc_url( $theme->screenshot_url ); ?>" alt="">
	</div>
	<a href="/<?php echo $theme->slug; ?>" class="more-details"><?php _ex( 'More Info', 'theme' ); ?></a>
	<div class="theme-author"><?php printf( __( 'By %s' ), $theme->author ); ?></div>
	<h3 class="theme-name"><?php echo $theme->name; ?></h3>

	<div class="theme-actions">
		<a class="button button-primary preview install-theme-preview" href="<?php echo esc_url( '//downloads.wordpress.org/theme/' . $theme->slug . '.' . $theme->version . '.zip' ); ?>"><?php esc_html_e( 'Download' ); ?></a>
	</div>
</article>
