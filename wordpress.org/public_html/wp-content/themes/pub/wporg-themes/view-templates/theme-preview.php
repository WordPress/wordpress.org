<script id="tmpl-theme-preview" type="text/template">
	<div class="wp-full-overlay-sidebar">
		<div class="wp-full-overlay-header">
			<a href="#" class="close-full-overlay"><span class="screen-reader-text"><?php _e( 'Close' ); ?></span></a>
			<a href="#" class="previous-theme"><span class="screen-reader-text"><?php _ex( 'Previous', 'Button label for a theme' ); ?></span></a>
			<a href="#" class="next-theme"><span class="screen-reader-text"><?php _ex( 'Next', 'Button label for a theme' ); ?></span></a>
			<a href="//downloads.wordpress.org/theme/{{ data.slug }}.{{ data.version }}.zip" class="button button-primary theme-install"><?php _e( 'Download' ); ?></a>
		</div>
		<div class="wp-full-overlay-sidebar-content">
			<div class="install-theme-info">
				<h3 class="theme-name">{{ data.name }}</h3>
				<span class="theme-by"><?php printf( __( 'By %s' ), '{{ data.author.display_name }}' ); ?></span>

				<img class="theme-screenshot" src="{{ data.screenshot_url }}" alt="" />

				<div class="theme-details">
					<div class="rating rating-{{ Math.round( data.rating / 10 ) * 10 }}">
						<span class="one"></span>
						<span class="two"></span>
						<span class="three"></span>
						<span class="four"></span>
						<span class="five"></span>

						<# if ( data.num_ratings ) { #>
						<small class="ratings"><?php printf( __( '(based on %s ratings).', 'wporg-themes' ), '{{ data.num_ratings }}' ); ?></small>
						<# } else { #>
						<small class="ratings"><?php _e( 'No ratings.' ); ?></small>
						<# } #>
					</div>
					<div class="theme-version"><?php printf( __( 'Version: %s' ), '{{ data.version }}' ); ?></div>
					<div class="theme-description">{{{ data.description }}}</div>
				</div>
			</div>
		</div>
		<div class="wp-full-overlay-footer">
			<a href="#" class="collapse-sidebar" title="<?php esc_attr_e( 'Collapse Sidebar' ); ?>">
				<span class="collapse-sidebar-label"><?php _e( 'Collapse' ); ?></span>
				<span class="collapse-sidebar-arrow"></span>
			</a>
		</div>
	</div>
	<div class="wp-full-overlay-main">
		<iframe src="{{ data.preview_url }}" />
	</div>
</script>
