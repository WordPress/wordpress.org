<script id="tmpl-theme" type="text/template">
	<a class="url" href="{{{ data.permalink }}}" rel="bookmark" tabindex="-1">
		<# if ( data.screenshot_url ) { #>
		<div class="theme-screenshot">
			<img src="{{ data.screenshot_url }}?w=286&strip=all" alt="" />
		</div>
		<# } else { #>
		<div class="theme-screenshot blank"></div>
		<# } #>
		<span class="more-details"><?php _ex( 'More Info', 'theme' ); ?></span>
		<div class="theme-author"><?php printf( __( 'By %s' ), '<span class="author">{{ data.author.display_name }}</span>' ); ?></div>
		<h3 class="theme-name entry-title">{{{ data.name }}}</h3>

		<div class="theme-actions">
			<a class="button button-primary preview install-theme-preview" href="//downloads.wordpress.org/theme/{{ data.slug }}.{{ data.version }}.zip"><?php esc_html_e( 'Download' ); ?></a>
		</div>
	</a>
</script>
