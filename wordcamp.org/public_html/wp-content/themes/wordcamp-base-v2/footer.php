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

	</div><!-- #main -->

	<footer id="colophon" class="site-footer" role="contentinfo">
	
		<?php // Footer Widget Areas ?>
		<div id="footer-widgets">
			<?php foreach ( range( 1, 5 ) as $index ) : ?>
				<?php if ( ! is_active_sidebar( 'footer-' . $index ) ) continue; ?>
				<div id="footer-widget-<?php echo $index; ?>" class="footer-widgets-block">
					<?php dynamic_sidebar( 'footer-' . $index ) ; ?>
				</div>
			<?php endforeach; ?>
		</div><!-- #footer-widgets -->

		<div class="site-info">

			<?php do_action( 'wcbs_credits' ); ?>

			<a class="site-info-generator" href="http://wordpress.org/" title="<?php esc_attr_e( 'A Semantic Personal Publishing Platform', 'wordcamporg' ); ?>" rel="generator"><?php printf( __( 'Proudly powered by %s', 'wordcamporg' ), 'WordPress' ); ?></a>

			<a class="site-info-network" href="http://central.wordcamp.org/" title="<?php esc_attr_e( 'Return to WordCamp Central', 'wordcamporg' ); ?>"><?php _e('Go to WordCamp Central', 'wordcamporg'); ?></a>
			
		</div><!-- .site-info -->
	</footer><!-- .site-footer .site-footer -->
</div><!-- #page .hfeed .site -->

<?php wp_footer(); ?>

</body>
</html>