<?php
/**
 * The sidebar widget areas before the content
 *
 * @link    https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package CampSite_2017
 */

namespace WordCamp\CampSite_2017;

if ( is_front_page() && ! is_page_template( 'templates/page-day-of.php' ) ) {
	$has_active_homepage_sidebar = is_active_sidebar( 'before-content-homepage-1' ) || is_active_sidebar( 'before-content-homepage-2' ) || is_active_sidebar( 'before-content-homepage-3' ) || is_active_sidebar( 'before-content-homepage-4' ) || is_active_sidebar( 'before-content-homepage-5' );

	if ( $has_active_homepage_sidebar ) {
		?>

		<div id="content-widgets">
			<?php foreach ( range( 1, 5 ) as $index ) {
				if ( is_active_sidebar( 'before-content-homepage-' . $index ) ) : ?>

					<div id="content-widget-<?php echo absint( $index ); ?>" class="content-widgets-block">
						<?php dynamic_sidebar( 'before-content-homepage-' . $index ); ?>
					</div>

				<?php endif;
			} ?>
		</div>

		<?php
	}

} elseif ( is_page_template( 'templates/page-day-of.php' ) ) {
	$has_active_day_of_sidebar = is_active_sidebar( 'before-content-day-of-1' ) || is_active_sidebar( 'before-content-day-of-2' ) || is_active_sidebar( 'before-content-day-of-3' ) || is_active_sidebar( 'before-content-day-of-4' ) || is_active_sidebar( 'before-content-day-of-5' );

	if ( $has_active_day_of_sidebar ) {
		?>

		<div id="content-widgets">
			<?php foreach ( range( 1, 5 ) as $index ) {
				if ( is_active_sidebar( 'before-content-day-of-' . $index ) ) : ?>

					<div id="content-widget-<?php echo absint( $index ); ?>" class="content-widgets-block">
						<?php dynamic_sidebar( 'before-content-day-of-' . $index ); ?>
					</div>

				<?php endif;
			} ?>
		</div>

		<?php
	}

} elseif ( is_active_sidebar( 'before-content-1' ) ) { ?>

	<div id="content-widgets">
		<div id="content-widget-1" class="content-widgets-block">
			<?php dynamic_sidebar( 'before-content-1' ); ?>
		</div>
	</div>

<?php }
