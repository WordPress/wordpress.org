<?php

namespace WordCamp\PWA\Caching;
use WP_Service_Worker_Caching_Routes;

add_action( 'wp_front_service_worker', __NAMESPACE__ . '\register_caching_routes' );
	// todo is this the most appropriate hook to register these?
		// seemed to have problems when using `default_service_workers`, like infinite loop.
	// But do caching routes always affect both workers regardless? Or should they?
		// Even if the caching is restricted to front end worker, that would still affect Tagregator and others,
		// so still need to worry about side-effects.
add_action( 'wp_front_service_worker',    __NAMESPACE__ . '\set_navigation_caching_strategy' );


// todo prompt to install app to home screen is automatically showing on mobile
	// not sure we want it to
	// not really related to this file, but nothing closer at the moment
	// maybe want it to be eventually, but not until we do more work to really make the site use pwa features well?
	// otherwise risk giving users bad impression of pwas

// also, says "install wordcamp" instead of "install wordcamp europe" - maybe manifest issue?

// after adding to home screen, there's a delay and a "wordcamp" interstitial screen before the site appears.
	// why? can remove that? it should load instantly, right? that's the whole idea

// experience on mid-level phone (moto e4) and fast bandwidth is pretty poor
	// what's the cause? big images? too much markup to parse? server slow ttfb?
	// maybe just needs to have better perceived speed rather than better actual speed? like transitions between screens
		// maybe new default theme that's a SPA and has a subtle transition animation when fetching new page content from API

// what's the point of adding to home screen? doesn't seem like it has anything extra cached for offline use
	// maybe if can detect when it's installed, we should pre-cache more things, for faster loading and more offline accessibility?

// maybe avoid loading images on slow connections
	// related https://github.com/xwp/pwa-wp/issues/110, probably better to contribute to that (or new issue in that repo), than build custom. would be good feature for core
	// https://github.com/wceu/wordcamp-pwa-page/issues/5
	// https://deanhume.com/dynamic-resources-using-the-network-information-api-and-service-workers/
	// this isn't really caching, so maybe create a separate file for it, or refactor this to be everything related to service workers.
		// probably the former, `service-worker-misc.php`

// having multiple tabs open, when 1 has a youtube embed playing, then refresh other tab, the video in first tab stops playing
	// doesn't happen every time though






// open issue w/ pwa lpugin - this may be plugin territory, but save offline button to add to precache route?
	// similar to that one site
//	or does it do that automatically already?
//
//maybe button to download all pages (not all posts or other cotent types, just pages, so that whole site heirarchy is accessible offline)


/**
 * Register caching routes with both service workers.
 */
function register_caching_routes() {
	/*
	 * todo
	 *
	 * pre-cache important pages like Location. what others? how to detect programatically?
	 *      could match `location` slug, and also add a `service-worker-precache` postmeta field to post stubs that we create on new sites
	 *      maybe pwa feature plugin already supports something like that? if not, maybe propose it
	 *      offline and day-of-event templates could show warnings to logged-in admins if the key is missing b/c they didn't use the default page
	 * Is the wp-content/includes caching route even working? Didn't cache assets for offline template.
	 *      How can you tell if it's working, compared to regular browser caching?
	 *      devtools Network panel should say "From service worker" for size?
	 * Is this the most appropriate way to make the day-of template performant?
	 *      Should an eTag be used in addition to -- or instead of -- this?
	 *      See https://github.com/wceu/wordcamp-pwa-page/issues/6#issuecomment-499366120
	 * Are the expiration periods here appropriate for all consumers of these resources?
	 * What side effects does this introduce, if any?
	 * Will cachebuster params in asset URLs still work?
	 * Will Gutenberg, Tagregator, etc receive outdated responses to their GET requests?
	 *      If so that would fundamentally break them, and this needs to be fixed.
	 *      If not, then the reason should be documented here, because it's not obvious.
	 *      Gutenberg probably ok as long as only registering caching route w/ front-end service worker.
	 *      For tagregator, maybe should only cache the specific endpoints that the day-of-event template calls?
	 *          Or maybe cache all routes, but then add an extra route that caches Tagregator less often?
	 * All of this needs to be tested to verify that it's working as intended.
	 *      What's the best way to do that? Document it here if it's not obvious.
	 * need to explicitly remove older revisions of custom-css (and other assets?) from the cache when they change?
	 */

	$custom_css_url_parts = wp_parse_url( wcorg_get_custom_css_url() );

	$static_asset_route_params = array(
		'strategy'  => WP_Service_Worker_Caching_Routes::STRATEGY_CACHE_FIRST,
		'cacheName' => 'assets',
		'plugins'   => [
			'expiration' => [
				'maxEntries'    => 60,
				'maxAgeSeconds' => DAY_IN_SECONDS,
			],
		],
	);

	wp_register_service_worker_caching_route(
		'/wp-(content|includes)/.*\.(?:png|gif|jpg|jpeg|svg|webp|css|js)(\?.*)?$',
		$static_asset_route_params
	);

	if ( isset( $custom_css_url_parts['path'], $custom_css_url_parts['query'] ) ) {
		wp_register_service_worker_caching_route(
			$custom_css_url_parts['path'] . $custom_css_url_parts['query'] . '$',
			$static_asset_route_params
		);
	}

	wp_register_service_worker_caching_route(
		'/wp-(content|includes)/.*\.(?:png|gif|jpg|jpeg|svg|webp|css|js)(\?.*)?$',
		[
			'strategy'  => WP_Service_Worker_Caching_Routes::STRATEGY_CACHE_FIRST,
			'cacheName' => 'assets',
			'plugins'   => [
				'expiration' => [
					'maxEntries'    => 60,
					'maxAgeSeconds' => DAY_IN_SECONDS,
				],
			],
		]
	);

	wp_register_service_worker_caching_route(
		'/wp-json/.*',
		[
			'strategy'  => WP_Service_Worker_Caching_Routes::STRATEGY_CACHE_FIRST,
			'cacheName' => 'rest-api',
			'plugins'   => [
				'expiration' => [
					'maxEntries'    => 60,
					'maxAgeSeconds' => 15 * MINUTE_IN_SECONDS,
				],
			],
		]
	);
}

/**
 * Set the navigation preload strategy for the front end service worker.
 * todo is ^ the best explanation of what's going on here?
 */
function set_navigation_caching_strategy() {
	/*
	 * todo
	 *
	 * All of this needs to be understood deeper in order to know if it's the right way to achieve the goals
	 * of this project, and what the unintended side-effects may be.
	 *
	 * See https://developers.google.com/web/updates/2017/02/navigation-preload
	 *
	 * Should navigation preloading really be disabled? Maybe it's good to disable it since we're not using it?
	 * But then why is it enabled by default? Maybe the `pwa` plugin is using it?
	 *
	 * We need to clearly document what's going on here, and _why_.
	 *
	 * Are the chosen caching strategies and parameters appropriate in this context?
	 *
	 * Are there side-effects beyond the day-of template? If so, how should they be addressed?
	 *
	 * All of this needs to be tested to verify that it's working as intended.
	 *      What's the best way to do that? Document it here if it's not obvious.
	 */

	add_filter( 'wp_service_worker_navigation_preload', '__return_false' );

	add_filter(
		'wp_service_worker_navigation_caching_strategy',
		function() {
			return WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_FIRST;
		}
	);

	add_filter(
		'wp_service_worker_navigation_caching_strategy_args',
		function( $args ) {
			$args['cacheName']                           = 'pages';
			$args['plugins']['expiration']['maxEntries'] = 50;

			return $args;
		}
	);
};
