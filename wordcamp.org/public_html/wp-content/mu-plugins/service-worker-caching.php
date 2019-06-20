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


// todo is prompt to save offline automatically showing on mobile?
	// if so, not sure we want it to
	// not really related to this file, but nothing closer at the moment
	// maybe want it to be eventually, but not until we do more work to really make the site use pwa features well?
	// otherwise risk giving users bad impression of pwas

/**
 * Register caching routes with both service workers.
 */
function register_caching_routes() {
	/*
	 * todo
	 *
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
	// todo test url being false/null

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
