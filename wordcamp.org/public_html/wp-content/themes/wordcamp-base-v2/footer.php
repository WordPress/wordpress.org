<?php
/**
 * The template for displaying the footer.
 *
 * Contains the closing of the id=main div and all content after
 *
 * @package WCBS
 * @since WCBS 1.0
 */

?>

	</div> <!-- #main -->

	<footer id="colophon" class="site-footer" role="contentinfo">

		<div id="footer-widgets">
			<?php foreach ( range( 1, 5 ) as $index ) : ?>
				<?php

				if ( ! is_active_sidebar( 'footer-' . $index ) ) {
					continue;
				}

				?>

				<div id="footer-widget-<?php echo esc_attr( $index ); ?>" class="footer-widgets-block">
					<?php dynamic_sidebar( 'footer-' . $index ); ?>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="site-info">
			<?php do_action( 'wcbs_credits' ); ?>

			<a class="site-info-generator" href="https://wordpress.org/" title="<?php esc_attr_e( 'A Semantic Personal Publishing Platform', 'wordcamporg' ); ?>" rel="generator">
				<?php
				// translators: %s is "WordPress".
				printf( esc_html__( 'Proudly powered by %s', 'wordcamporg' ), 'WordPress' );
				?>
			</a>

			<a class="site-info-network" href="https://central.wordcamp.org/">
				<?php esc_html_e( 'Go to WordCamp Central', 'wordcamporg' ); ?>
			</a>

			<?php function_exists( 'the_privacy_policy_link' ) && the_privacy_policy_link( '<span class="privacy-policy-link-wrapper">', '</span>' ); ?>
		</div>
	</footer>
</div> <!-- #page -->

<?php wp_footer(); ?>

</body>
</html>
