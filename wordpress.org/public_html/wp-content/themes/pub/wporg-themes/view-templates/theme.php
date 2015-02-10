<script id="tmpl-theme" type="text/template">
	<# if ( data.screenshot_url ) { #>
	<div class="theme-screenshot">
		<img src="{{ data.screenshot_url }}?w=572&strip=all" alt="" />
	</div>
	<# } else { #>
	<div class="theme-screenshot blank"></div>
	<# } #>
	<a class="more-details url" href="{{{ data.permalink }}}" rel="bookmark"><?php _ex( 'More Info', 'theme' ); ?></a>
	<div class="theme-author"><?php printf( __( 'By %s' ), '<span class="author">{{ data.author }}</span>' ); ?></div>
	<h3 class="theme-name entry-title">{{{ data.name }}}</h3>

	<div class="theme-actions">
		<a class="button button-primary preview install-theme-preview" href="//downloads.wordpress.org/theme/{{ data.slug }}.{{ data.version }}.zip"><?php esc_html_e( 'Download' ); ?></a>
	</div>
</script>
