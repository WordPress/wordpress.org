<?php

/**
 * Template Name: Mobile
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

/* See inc/page-meta-descriptions.php for the meta description for this page. */

get_header( 'top-level-page' );
the_post();

?>

	<main id="main" class="site-main col-12" role="main">
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<div class="entry-content col-12">

				<img
					class="feature"
					alt="Devices showing the WordPress mobile app"
					src="//s.w.org/images/mobile/devices.png?2"
					width="600"
					height="281"
				/>

				<p class="intro">
					Inspiration strikes any time, anywhere. WordPress mobile apps put the power of publishing in your hands, making it easy to create and consume content. Write, edit, and publish posts to your site, check stats, and get inspired with great posts in the Reader. And of course, they&rsquo;re open source, just like WordPress.
				</p>

				<div class="web-stores">
					<a href="http://play.google.com/store/apps/details?id=org.wordpress.android" class="button-android">
						<img
							src="https://s.w.org/wp-content/themes/pub/wporg-main/images/badge-google-play.png"
							alt="Available in the Google Play Store"
						/>
					</a>

					<a href="https://itunes.apple.com/app/apple-store/id335703880?pt=299112&ct=wordpress.org&mt=8" class="button-ios">
						<img
							src="https://s.w.org/wp-content/themes/pub/wporg-main/images/badge-apple.png"
							alt="Available in the Apple App Store"
						/>
					</a>

					<a href="https://apps.wordpress.com/desktop/" class="button-desktop">
						<button>Windows, Mac, Linux</button>
					</a>
				</div>

				<p class="requirements">
					WordPress mobile apps support WordPress.com and self-hosted WordPress.org sites running WordPress 4.0 or higher.
					<a href="https://apps.wordpress.com/get/?campaign=wporg">Learn more</a>
				</p>

			</div><!-- .entry-content -->
		</article><!-- #post-## -->
	</main><!-- #main -->

<?php

get_footer();
