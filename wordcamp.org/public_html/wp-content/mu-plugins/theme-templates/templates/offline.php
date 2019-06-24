<?php

/**
 * Template Name: Offline Notice
 *
 * The service worker will show this template to visitors who are browsing offline.
 *
 * To test changes to it, you'll need to
 * temporarily enable the `Bypass for network` setting in dev tools.
 * 		^ doesn't work when using `offline` in chrome dev tools
 * 		maybe when using urls directly?
 *
 * See https://github.com/xwp/pwa-wp/issues/167#issuecomment-501004695
 */

namespace WordCamp\Theme_Templates;

get_header();

/*
 * todo
 *
 * test this across themes
 * test with child themes to make sure parent theme stylesheet is cached too
 * 		should theoretically pre-cache all stylesheets, but don't need to in practice b/c caching route takes care of?
 * 		can't hurt. maybe needed if regular cached assets are evicted to free up space, whereas precached ones wouldn't be?
 * test with themes replacing and extending the theme stylesheet (via jetpack/remote-css)
 * add date/time, location, schedule, etc -- https://github.com/wceu/wordcamp-pwa-page/issues/9
 * Should we add `{{{error_details_iframe}}}` ?
 * Should we add `wp_service_worker_error_details_template()` ?
 * Maybe list all of the pages that are available offline, ala https://chrisruppel.com/travel/
 * 		Maybe do that instead of the regular navigation menu?
 * include sidebar?
 */

?>

	<main>
		<h1>
			<?php echo esc_html( _x( 'Offline', 'Page Title', 'wordcamporg' ) ); ?>
		</h1>

		<p>
			<?php esc_html_e( "This page couldn't be loaded because you appear to be offline. Please try again once you have a network connection.", 'wordcamporg' ); ?>
		</p>

		<div>
			<?php
			// todo repeats "please try again..." from above hardcoded string in some cases, but not all.
			// need to detect when and avoid saying it twice?
			// or just don't call this b/c it doesn't provide detailed error message that'd be useful to user?
			// -- wp_service_worker_error_message_placeholder();
			// ?>
		</div>

		<p>
			<?php _e( "In the mean time, hopefully this information is useful:", 'wordcamporg' );
			// todo that string needs a lot of work
			?>
		</p>

		<?php
			require_once( dirname( __DIR__ ) . '/parts/dates.php' );
			require_once( dirname( __DIR__ ) . '/parts/location.php' );
		?>

		<h3>
			<?php esc_html_e( 'Schedule' ); ?>
		</h3>

		<?php
		/*
		 * todo
		 *
		 * this doesn't have headlines for separate days. why not? will probably be fixed by replacing block with shortcode though
		 * maybe need to specify params to show all days/all tracks/etc
		 * disable "favorites" b/c it won't work? well, some parts will and some parts won't. probably better to leave it, but think about in more detail
		 * replace w/ Schedule block once that's available, but use feature flag for back-compat
		 */
		echo do_shortcode( '[schedule]' );

		?>
	</main>

<?php

get_footer();
