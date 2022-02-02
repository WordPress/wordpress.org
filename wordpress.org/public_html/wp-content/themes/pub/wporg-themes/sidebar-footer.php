<aside class="theme-widget-area" role="complementary">
	<div class="widget-wrap wrap">
		<div class="widget widget_text">
			<h2 class="widget-title"><?php esc_html_e( 'Add Your Theme', 'wporg-themes' ); ?></h2>
			<div class="textwidget"><?php
				printf(
					__( 'The WordPress theme directory is used by millions of WordPress users all over the world. <a href="%s">Submit your theme</a> to the official WordPress.org theme repository.', 'wporg-themes' ),
					esc_url( site_url( 'getting-started/' ) )
				);
			?>
			</div>
		</div>
		<div class="widget widget_text">
			<h2 class="widget-title"><?php esc_html_e( 'Create a Theme', 'wporg-themes' ); ?></h2>
			<div class="textwidget"><?php
				printf(
					__( 'Want to learn how to build a great theme? Read the <a href="%s">Theme Developer Handbook</a> to learn everything about WordPress theme development.', 'wporg-themes' ),
					esc_url( 'https://developer.wordpress.org/themes/' )
				);
			?>
			</div>
		</div>
		<div class="widget widget_text">
			<h2 class="widget-title"><?php esc_html_e( 'Stay Up-to-Date', 'wporg-themes' ); ?></h2>
			<div class="textwidget"><?php
				printf(
					__( "Trying to ensure a great experience for the theme authors and users, means that theme requirements change from time to time. Keep up with the latest changes by following the <a href='%s'>Themes Team blog</a>.", 'wporg-themes' ),
					esc_url( 'https://make.wordpress.org/themes/' )
				);
			?>
			</div>
		</div>
	</div>
</aside>
