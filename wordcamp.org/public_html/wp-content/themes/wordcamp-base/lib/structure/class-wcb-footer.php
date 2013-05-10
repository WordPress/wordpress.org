<?php

class WCB_Footer extends WCB_Element {
	function get_id() {
		return 'footer';
	}

	function content() { ?>
		<div class="grid_12">
		<div id="<?php echo $this->get_id(); ?>" role="contentinfo">
			<div id="colophon">
				<div id="site-info">
					<a href="<?php echo home_url( '/' ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home">
						<?php bloginfo( 'name' ); ?>
					</a>
				</div><!-- #site-info -->

				<div id="site-generator">
					<?php do_action( 'twentyten_credits' ); ?>
					<a href="<?php echo esc_url( __( 'http://wordpress.org/', 'wordcampbase' ) ); ?>" title="<?php esc_attr_e( 'Semantic Personal Publishing Platform', 'wordcampbase' ); ?>" rel="generator"><?php printf( __( 'Proudly powered by %s.', 'wordcampbase' ), 'WordPress' ); ?></a>
				</div><!-- #site-generator -->

			</div><!-- #colophon -->
		</div><!-- #footer -->
		</div>
		<?php
			/* Always have wp_footer() just before the closing </body>
			 * tag of your theme, or you will break many plugins, which
			 * generally use this hook to reference JavaScript files.
			 */

			wp_footer();
	}
}

?>