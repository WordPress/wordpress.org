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
add_action( 'wp_enqueue_scripts',              __NAMESPACe__ . '\enqueue_template_assets' );


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

