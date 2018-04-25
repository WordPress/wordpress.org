<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package CampSite_2017
 */

namespace WordCamp\CampSite_2017;

?>

		</div> <!-- #content -->

		<footer id="colophon" class="site-footer" role="contentinfo">
			<?php get_sidebar( 'footer' ); ?>

			<div class="site-info">
				<?php /* translators: %s: WordPress */ ?>
				<a class="site-info-generator" href="https://wordpress.org/" title="<?php esc_attr_e( 'A Semantic Personal Publishing Platform', 'wordcamporg' ); ?>" rel="generator">
					<?php echo esc_html( sprintf( __( 'Proudly powered by %s', 'wordcamporg' ), 'WordPress' ) ); ?>
				</a>

				<span role="separator" aria-hidden="true"></span>

				<a class="site-info-network" href="https://central.wordcamp.org/">
					<?php esc_html_e( 'Go to WordCamp Central', 'wordcamporg' ); ?>
				</a>

				<span role="separator" aria-hidden="true"></span>

				<?php function_exists( 'the_privacy_policy_link' ) && the_privacy_policy_link(); ?>
			</div>
		</footer>

	</div> <!-- #page -->

	<?php wp_footer(); ?>

</body>
</html>
