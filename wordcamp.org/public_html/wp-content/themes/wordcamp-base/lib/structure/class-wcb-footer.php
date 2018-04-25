<?php

class WCB_Footer extends WCB_Element {
	/**
	 * Get the ID of the element.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'footer';
	}

	/**
	 * Render the content of the element.
	 */
	public function content() {
		?>

		<div class="grid_12">
			<div id="<?php echo esc_attr( $this->get_id() ); ?>" role="contentinfo">
				<div id="colophon">
					<div id="site-info">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home">
							<?php bloginfo( 'name' ); ?>
						</a>
					</div>

					<div id="site-generator">
						<?php do_action( 'twentyten_credits' ); ?>

						<a href="<?php echo esc_url( __( 'https://wordpress.org/', 'wordcamporg' ) ); ?>" title="<?php esc_attr_e( 'Semantic Personal Publishing Platform', 'wordcamporg' ); ?>" rel="generator">
							<?php
							// translators: %s is "WordPress".
							printf( esc_html__( 'Proudly powered by %s.', 'wordcamporg' ), 'WordPress' );
							?>
						</a>
					</div>
				</div>
			</div>
		</div>

		<?php wp_footer();
	}
}
