<?php
/**
 * Custom taxonomies.
 *
 * Route contract (docs/routes.md): the registration arguments are copied verbatim
 * from wptv2, because they decide the rewrite rules, query vars and REST routes.
 * Do not change them in Phase 1. `producer` and `flavor` will be retired later
 * with 301 redirects (docs/backlog.md, B3).
 *
 * `template`, `helps`, `sort` and `args` are legacy arguments kept as is:
 * `sort` and `args` keep terms in the order they were entered (term_order).
 *
 * @package WordPressTV
 */

namespace WordPressTV\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the WordPress.tv taxonomies. Runs on init, priority 0, as in wptv2.
 */
function register() {
	register_taxonomy(
		'producer',
		array( 'post' ),
		array(
			'label'    => __( 'Producer', 'wptv' ),
			/* translators: %l: List of producers. */
			'template' => __( 'Producer: %l.', 'wptv' ),
			'helps'    => __( 'Separate producers with commas.', 'wptv' ),
			'sort'     => true,
			'args'     => array( 'orderby' => 'term_order' ),
			'rewrite'  => array( 'slug' => 'producer' ),
		)
	);

	register_taxonomy(
		'producer-username',
		array( 'post' ),
		array(
			'label'    => __( 'Producer Username', 'wptv' ),
			/* translators: %l: List of producers. */
			'template' => __( 'Producer: %l.', 'wptv' ),
			'helps'    => __( 'Separate producer usernames with commas.', 'wptv' ),
			'sort'     => true,
			'args'     => array( 'orderby' => 'term_order' ),
			'rewrite'  => array( 'slug' => 'producer-username' ),
		)
	);

	register_taxonomy(
		'speakers',
		array( 'post' ),
		array(
			'label'        => __( 'Speakers', 'wptv' ),
			/* translators: %l: List of speakers. */
			'template'     => __( 'Speakers: %l.', 'wptv' ),
			'helps'        => __( 'Separate speakers with commas.', 'wptv' ),
			'sort'         => true,
			'args'         => array( 'orderby' => 'term_order' ),
			'rewrite'      => array( 'slug' => 'speakers' ),
			'show_in_rest' => true,
			'rest_base'    => 'speakers',
		)
	);

	register_taxonomy(
		'flavor',
		array( 'post' ),
		array(
			'label'    => __( 'Flavor', 'wptv' ),
			/* translators: %l: List of flavors. */
			'template' => __( 'Flavor: %l.', 'wptv' ),
			'helps'    => __( 'Separate flavors with commas.', 'wptv' ),
			'sort'     => true,
			'args'     => array( 'orderby' => 'term_order' ),
			'rewrite'  => array( 'slug' => 'flavor' ),
		)
	);

	register_taxonomy(
		'language',
		array( 'post' ),
		array(
			'label'        => __( 'Language', 'wptv' ),
			/* translators: %l: List of languages. */
			'template'     => __( 'Language: %l.', 'wptv' ),
			'helps'        => __( 'Separate languages with commas.', 'wptv' ),
			'sort'         => true,
			'args'         => array( 'orderby' => 'term_order' ),
			'rewrite'      => array( 'slug' => 'language' ),
			'show_in_rest' => true,
			'rest_base'    => 'language',
		)
	);

	register_taxonomy(
		'event',
		array( 'post' ),
		array(
			'label'        => __( 'Event', 'wptv' ),
			/* translators: %l: List of events. */
			'template'     => __( 'Event: %l.', 'wptv' ),
			'helps'        => __( 'Enter event', 'wptv' ),
			'sort'         => true,
			'args'         => array( 'orderby' => 'term_order' ),
			'rewrite'      => array( 'slug' => 'event' ),
			'show_in_rest' => true,
			'rest_base'    => 'event',
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\register', 0 );
