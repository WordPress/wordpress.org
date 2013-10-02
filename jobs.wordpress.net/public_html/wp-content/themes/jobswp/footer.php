<?php
/**
 * The template for displaying the footer.
 *
 * Contains the closing of the #content div and all content after
 *
 * @package jobswp
 */
?>

	</div><!-- #content -->
</div><!-- #page -->

	<footer id="colophon" class="site-footer" role="contentinfo">
		<div class="site-info container">
			<?php do_action( 'jobswp_credits' ); ?>

			<?php locate_template( 'wporg-footer.php', true ); ?>
		</div><!-- .site-info -->
	</footer><!-- #colophon -->

<?php wp_footer(); ?>

</body>
</html>