<?php
	global $theme;
	$theme = wporg_themes_photon_screen_shot( $theme );
?>
<article id="post-<?php echo $theme->slug; ?>" class="theme hentry">
	<div class="theme-screenshot">
		<img src="<?php echo esc_url( $theme->screenshot_url . '?w=286&strip=all' ); ?>" alt="">
	</div>
	<a class="more-details url" href="<?php echo esc_url( home_url( $theme->slug . '/' ) ); ?>" rel="bookmark"><?php _ex( 'More Info', 'theme' ); ?></a>
	<div class="theme-author"><?php printf( __( 'By %s' ), '<span class="author">' . $theme->author . '</span>' ); ?></div>
	<h3 class="theme-name entry-title"><?php echo $theme->name; ?></h3>

	<div class="theme-actions">
		<a class="button button-primary preview install-theme-preview" href="<?php echo esc_url( '//downloads.wordpress.org/theme/' . $theme->slug . '.' . $theme->version . '.zip' ); ?>"><?php esc_html_e( 'Download' ); ?></a>
	</div>
</article>
