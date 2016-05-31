<?php

class WCB_Header extends WCB_Element {
	function get_id() {
		return 'header';
	}

	function content() { ?>
		<div id="<?php echo $this->get_id(); ?>" class="grid_12">
			<div id="return-to-central">
<?php if ( false !== strpos($_SERVER['HTTP_HOST'], 'wordpress.org')) { ?>
				<a href="http://wordpress.org/" title="<?php esc_attr_e( 'Return to WordPress.org', 'wordcamporg' ); ?>"><?php _e('&larr; WordPress.org', 'wordcamporg'); ?></a>
<?php } else { ?>
				<a href="http://central.wordcamp.org/" title="<?php esc_attr_e( 'Return to WordCamp Central', 'wordcamporg' ); ?>"><?php _e('&larr; WordCamp Central', 'wordcamporg'); ?></a>
<?php } ?>

			</div>
			<div id="masthead">
				<div id="branding" role="banner">
					<div id="branding-overlay"></div>
					<div id="branding-logo"></div>
					<?php wcb_site_title(); ?>
					<div id="site-description"><?php bloginfo( 'description' ); ?></div>
					<?php wcb_header_image(); ?>
				</div><!-- #branding -->
			</div><!-- #masthead -->
		</div><!-- #header -->
	<?php
	}
}

?>