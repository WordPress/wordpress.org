<script id="tmpl-theme" type="text/template">
	<# if ( data.screenshot_url ) { #>
	<div class="theme-screenshot">
		<img src="{{ data.screenshot_url }}" alt="" />
	</div>
	<# } else { #>
	<div class="theme-screenshot blank"></div>
	<# } #>
	<span class="more-details"><?php _ex( 'More Info', 'theme' ); ?></span>
	<div class="theme-author"><?php printf( __( 'By %s' ), '{{ data.author }}' ); ?></div>
	<h3 class="theme-name">{{ data.name }}</h3>

	<div class="theme-actions">
		<a class="button button-primary preview install-theme-preview" href="#"><?php esc_html_e( 'Download' ); ?></a>
	</div>
</script>
