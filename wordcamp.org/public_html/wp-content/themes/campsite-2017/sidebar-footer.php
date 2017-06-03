<?php
/**
 * The sidebar widget areas in the footer
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package CampSite_2017
 */

namespace WordCamp\CampSite_2017;

$has_active_sidebar = is_active_sidebar( 'footer-1' ) || is_active_sidebar( 'footer-2' ) || is_active_sidebar( 'footer-3' ) || is_active_sidebar( 'footer-4' ) || is_active_sidebar( 'footer-5' );

if ( $has_active_sidebar ) : ?>
	<div id="footer-widgets">

		<?php foreach ( range( 1, 5 ) as $index ) {
			if ( is_active_sidebar( 'footer-' . $index ) ) : ?>

				<div id="footer-widget-<?php echo absint( $index ); ?>" class="footer-widgets-block">
					<?php dynamic_sidebar( 'footer-' . $index ); ?>
				</div>

			<?php endif;
		} ?>

	</div>
<?php endif;
