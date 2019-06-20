<?php

/**
 * Theme-agnostic front end HTML templates.
 */

namespace WordCamp\Theme_Templates;
use WP_Post;
use WP_Service_Worker_Scripts;

defined( 'WPINC' ) || die();


/*
 * todo
 *
 * Add a drafted page titled "Day of Event" to new sites, assign to this template. Add note explaining what the template is/how to use/style it.
 * This needs more testing.
 * Probably integrate `bbpress-network-templates` into this, but only after this is network-activated.
 */

add_filter( 'theme_page_templates',            __NAMESPACE__ . '\register_page_templates' );
add_filter( 'template_include',                __NAMESPACE__ . '\set_page_template_locations' );
add_filter( 'template_include',                __NAMESPACE__ . '\inject_offline_template', 20 );  // After others because being offline transcends other templates.
add_action( 'wp_enqueue_scripts',              __NAMESPACe__ . '\enqueue_template_assets' );
add_filter( 'wp_offline_error_precache_entry', __NAMESPACE__ . '\add_offline_template_cachebuster' );
add_action( 'wp_front_service_worker',         __NAMESPACE__ . '\precache_offline_template_assets' );


/**
 * Add new templates to the Page Template dropdown in the editor.
 *
 * @param array $templates
 *
 * @return array
 */
function register_page_templates( $templates ) {
	/*
	 * Remove CampSite 2017's Day of template, since it's redundant, and having multiple templates in the menu
	 * would be confusing.
	 *
	 * @todo
	 * when network-enable, this template should only be removed on WCEU and new sites, so need a feature flag
	 *      there could be sites on the schedule now that have already configured it, cna't switch it up on them last minute
	 *      or maybe it's ok to break back-compat since it's just used on the day of the event? old sites shouldn't be showing it
	 *      also want existing sites that haven't happened yet to be able to to use it
	 * also need to remove the day-of widgets
	 *      same back-comapt considerations
	 * need to provide a way for organizers to add arbitrary content to this new day-of event page?
	 *      otherwise we'll be taking away flexibility when we disable the campsite day of template
	 *      maybe just have the template call `the_content()` above or below the hardcoded stuff?
	 */
	if ( isset( $templates['templates/page-day-of.php'] ) ) {
		unset( $templates['templates/page-day-of.php'] );
	}

	$templates['day-of-event'] = __( 'Day of Event', 'wordcamporg' );

	natsort( $templates );
	return $templates;
}

/**
 * Tell WP where to load the templates from.
 *
 * @param string $template_path
 *
 * @return string
 */
function set_page_template_locations( $template_path ) {
	global $post;

	if ( ! $post instanceof WP_Post ) {
		return $template_path;
	}

	switch( $post->_wp_page_template ) {
		case 'day-of-event':
			$template_path = __DIR__ . '/templates/day-of-event/day-of-event.php';
			break;
	}

	return $template_path;
}

/**
 * Enqueues JavaScript and CSS files for the active page template.
 */
function enqueue_template_assets() {
	global $post;

	if ( ! $post instanceof WP_Post ) {
		return;
	}

	switch( $post->_wp_page_template ) {
		case 'day-of-event':
			// todo this has poor readability, we wouldn't really want to do all this in a `switch` for multiple entries
			// maybe move to separate function, but then it's kind of awkward to have this switch setup just for 1 template
			// maybe remove b/c YAGNI, but other places use `switch` too and it feels cleaner/better there.

			wp_enqueue_script(
				'day-of-event-template',
				plugins_url( '/templates/day-of-event/build/index.js', __FILE__ ),
				json_decode( file_get_contents( __DIR__ . '/templates/day-of-event/build/index.deps.json' ) ),
				filemtime( __DIR__ . '/templates/day-of-event/build/index.js' ),
				true
			);

			$config = array(
				'postsArchiveUrl' => esc_url( get_post_type_archive_link( 'post' ) ),
				'scheduleUrl'     => esc_url( site_url( __( 'schedule', 'wordcamporg' ) ) ), // todo can't hardcode
			);

			$configScript = sprintf(
				'var dayOfEventConfig = JSON.parse( decodeURIComponent( \'%s\' ) );',
				rawurlencode( wp_json_encode( $config ) )
			);

			wp_add_inline_script( 'day-of-event-template', $configScript, 'before' );

			/*
			 * This depends on 'wp-components', but that is intentionally left out because the only part we need
			 * is `.components-spinner`, and it wouldn't be performant to bundle the entire file just for that. So
			 * instead that class is duplicated in `day-of-event.css`.
			 */
			wp_enqueue_style(
				'day-of-event-template',
				plugins_url( '/templates/day-of-event/day-of-event.css', __FILE__ ),
				array(),
				filemtime( __DIR__ . '/templates/day-of-event/day-of-event.css' )
			);

			break;
	}
}


/**
 * Inject the offline template when the service worker pre-caches the response to offline requests.
 *
 * This is a dynamic template like search, 404, etc, rather than a page template.
 *
 * @param string $template_path
 *
 * @return string
 */
function inject_offline_template( $template_path ) {
	if ( is_offline() || is_500() ) {
		$template_path = __DIR__ . '/templates/offline.php';
	}

	return $template_path;
}

/**
 * Add a cache-buster to the offline template's pre-cache entry.
 *
 * @param string
 *
 * @return string
 */
function add_offline_template_cachebuster( $entry ) {
	$entry['revision'] .= ';' . filemtime( __DIR__ . '/templates/offline.php' );    // todo test that this is working. doesn't seem like it is, WB_REVISION is 0.2.0 (pwa plugin version), rather than this.

	return $entry;
}

/**
 * Precache the current theme's stylesheet
 *
 * @param WP_Service_Worker_Scripts $scripts
 */
function precache_offline_template_assets( WP_Service_Worker_Scripts $scripts ) {
	$asset = get_custom_css_precache_details();

	/*
	 * If we don't have a URL, that's probably because the custom CSS is empty or short enough to be printed
	 * inline instead of enqueued. In that case, the offline template will have it printed from the `wp_head()`
	 * call anyway.
	 */
	if ( ! $asset ) {
		return;
		// todo test
	}

	$scripts->precaching_routes()->register(
		$asset['url'],
		array( 'revision' => $asset['revision'] )
	);
}

/**
 * Get the URL and revision for the custom CSS stylesheet.
 *
 * @return array|bool
 */
function get_custom_css_precache_details() {
	$url = wcorg_get_custom_css_url();

	wp_parse_str(
		wp_parse_url( $url, PHP_URL_QUERY ),
		$url_query_params
	);

	// todo precache header image too, but can't for wceu b/c they're specifying in CSS bg image, rather than using Core functions.

	return array(
		'url' => $url,

		/*
		 * This could probably be anything, since `$url` actually contains a cachebuster, but a unique revision
		 * is set just for completeness.
		 *
		 * Jetpack stores the cachebuster in the `custom-css` query parameter.
		 */
		'revision' => $url_query_params['custom-css'],
	);
}
