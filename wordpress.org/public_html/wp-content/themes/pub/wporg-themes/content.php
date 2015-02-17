<?php
$post  = get_post();
$theme = new WPORG_Themes_Repo_Package( $post );
?>
<article id="post-<?php echo $post->post_name; ?>" class="theme hentry">
	<a class="url" href="<?php the_permalink(); ?>" rel="bookmark">
		<div class="theme-screenshot">
			<?php the_post_thumbnail( '572' ); ?>
		</div>
		<span class="more-details"><?php _ex( 'More Info', 'theme' ); ?></span>
		<div class="theme-author">
			<?php printf( _x( 'By %s', 'post author', 'wporg-themes' ), '<span class="author vcard">' . esc_html( get_the_author() ) . '</span>' ); ?>
		</div>
		<h3 class="theme-name entry-title"><?php the_title(); ?></h3>

		<div class="theme-actions">
			<a class="button button-primary preview install-theme-preview" href="<?php echo esc_url( '//downloads.wordpress.org/theme/' . $post->post_name . '.' . $theme->latest_version() . '.zip' ); ?>"><?php esc_html_e( 'Download' ); ?></a>
		</div>
	</a>
</article>
