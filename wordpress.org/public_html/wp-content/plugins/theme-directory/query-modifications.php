<?php

/**
 * Correct the post type for theme queries to be "repopackage".
 */
function wporg_themes_pre_get_posts( $query ) {
	if ( is_admin() ) {
		return;
	}

	// Don't apply this to non-theme queries
	if ( !empty( $query->query_vars['post_type'] ) && 'repopackage' != $query->query_vars['post_type'] ) {
		return;
	}

	// Themes are never via pagename
	if ( !empty( $query->query_vars['pagename'] ) ) {
		return;
	}

	// Default to the ~featured~ popular view
	if ( empty( $query->query ) ) {
		$query->query_vars['browse'] = 'popular';
	}

	// From now on, always query published themes.
	$query->query_vars['post_type']   = 'repopackage';
	if ( empty( $query->query_vars['post_status'] ) ) {
		$query->query_vars['post_status'] = 'publish';
	}
	if ( ! isset( $query->query_vars['browse'] ) ) {
		$query->query_vars['browse'] = '';
	}

	// Force the browse query_var when querying for a users favorites
	if ( !empty( $query->query_vars['favorites_user'] ) ) {
		$query->query_vars['browse'] = 'favorites';
	}

	// Delisted items should be available on singular / author archives.
	if (
		! empty( $query->query_vars['name'] ) ||
		! empty( $query->query_vars['author_name'] )
	) {
		if ( ! is_array( $query->query_vars['post_status'] ) ) {
			$query->query_vars['post_status'] = explode( ',', $query->query_vars['post_status'] );
		}

		$query->query_vars['post_status'][] = 'delist';
	}

	switch ( $query->query_vars['browse'] ) {
		case 'new':
			$query->query_vars['orderby'] = 'post_date';
			$query->query_vars['order'] = 'DESC';
			break;

		case 'updated':
			$query->query_vars['orderby'] = 'modified';
			$query->query_vars['order'] = 'DESC';
			break;

		case 'featured':
			// Pages > 1 don't exist.
			if ( isset( $query->query_vars['paged'] ) && $query->query_vars['paged'] > 1 ) {
				// Force a 404
				$query->query_vars['post__in'] = array( 0 );
			}

			$query->query_vars['posts_per_page'] = $query->found_posts = 15;
			// Featured themes require it to have been updated within the last year, not the default 2.
			$query->query_vars['date_query']['recent_themes_only'] = array(
				'column' => 'post_modified',
				'after'  => date( 'Y-m-d', strtotime( '-1 year' ) )
			);

			// Some themes are always featured by ways of the menu_order
			$query->query_vars['orderby'] = 'menu_order DESC, RAND(' . date( 'Ymd' ) . ')';
			$query->query_vars['no_found_rows'] = true;
			break;

		case 'favorites':
			$favorites = array();

			$user_id = 0;
			if ( ! empty( $query->query_vars['favorites_user'] ) ) {
				$user = get_user_by( 'login', $query->query_vars['favorites_user'] );
				if ( $user ) {
					$user_id = $user->ID;
				}
			} elseif ( is_user_logged_in() ) {
				$user_id = get_current_user_id();
			}

			if ( $user_id ) {
				$favorites = array_filter( (array) get_user_meta( $user_id, 'theme_favorites', true ) );
			}

			if ( $favorites ) {
				$query->query_vars['post_name__in'] = $favorites;
			} else {
				// Force a 404
				$query->query_vars['post__in'] = array( 0 );
			}

			$query->query_vars['orderby'] = 'post_title';
			$query->query_vars['order'] = 'ASC';
			break;

		case 'popular':
			// Only include themes that have existed for at least 2 weeks into the popular listing
			// This avoids cases where a new theme skews our popularity algorithms.
			$query->query_vars['date_query']['existing_themes_only'] = array(
				'column' => 'post_date',
				'before'  => date( 'Y-m-d', strtotime( '-2 weeks' ) )
			);

			// Sort by the popularity meta key
			$query->query_vars['meta_key'] = '_popularity';
			$query->query_vars['meta_type'] = 'DECIMAL(20,10)';
			$query->query_vars['orderby'] = 'meta_value DESC';
			break;

		case 'commercial':
			if ( ! isset( $query->query_vars['tax_query'] ) ) {
				$query->query_vars['tax_query'] = array();
			}
			$query->query_vars['tax_query']['model'] = array(
				'taxonomy' => 'theme_business_model',
				'field'    => 'slug',
				'terms'    => 'commercial',
				'operator' => 'IN',
			);
			break;

		case 'community':
			if ( ! isset( $query->query_vars['tax_query'] ) ) {
				$query->query_vars['tax_query'] = array();
			}
			$query->query_vars['tax_query']['model'] = array(
				'taxonomy' => 'theme_business_model',
				'field'    => 'slug',
				'terms'    => 'community',
				'operator' => 'IN',
			);
			break;

		default:
			// Force a 404 for anything else.
			if ( $query->query_vars['browse'] ) {
				$query->query_vars['error'] = 404;
				$query->query_vars['name'] = 'please-trigger-a-404';
				$query->query_vars['p'] = -404;
				$query->set_404();
			}
			break;
	}

	// Unless a specific theme/author is being requested, or it's an internal query, limit results to the last 2 years.
	if (
		empty( $query->query_vars['name'] ) &&
		empty( $query->query_vars['author_name'] ) &&
		empty( $query->query_vars['author'] ) &&
		! in_array( $query->query_vars['browse'], array( 'favorites', 'new', 'updated' ) ) &&
		empty( $query->query_vars['meta_query']['trac_sync_ticket_id'] ) && // jobs/class-trac-sync.php - Always needs to find the post, and looks up via a meta search.
		empty( $query->query_vars['meta_query']['theme_uri_search'] ) // class-wporg-themes-upload.php - Searching all known themes by meta value.
	) {
		$query->query_vars['date_query']['recent_themes_only'] = array(
			'column' => 'post_modified',
			'after'  => date( 'Y-m-d', strtotime( '-2 years' ) ),
		);
	}

	// Prioritize translated themes for localized requests, except when viewing a specific ordered themes.
	if (
		'en_US' !== get_locale() &&
		! in_array( $query->query_vars['browse'], array( 'favorites', 'new', 'updated' ) )
	) {
		add_filter( 'posts_clauses', 'wporg_themes_prioritize_translated_posts_clauses', 11 );
	}

	if ( $query->is_search() ) {
		add_filter( 'posts_clauses', 'wporg_themes_prioritize_exact_matches_clauses', 10, 2 );
	}
}
add_action( 'pre_get_posts', 'wporg_themes_pre_get_posts' );

/**
 * Filters SQL clauses, to prioritize translated themes.
 *
 * @param array $clauses
 *
 * @return array
 */
function wporg_themes_prioritize_translated_posts_clauses( $clauses ) {
	global $wpdb;

	$clauses['groupby']  = "{$wpdb->posts}.ID";
	$clauses['join']    .= $wpdb->prepare( " LEFT JOIN language_packs AS l ON ( {$wpdb->posts}.post_name = l.domain AND l.active=1 AND l.type='theme' AND l.language=%s )", get_locale() );
	$clauses['orderby']  = 'l.domain IS NOT NULL DESC, ' . $clauses['orderby'];

	return $clauses;
}

/**
 * Filters SQL clauses, to prioritise exact theme slug matches.
 */
function wporg_themes_prioritize_exact_matches_clauses( $clauses, $query ) {
	global $wpdb;

	// Override the post_modified check to allow matching when an exact title/slug match is found.
	$clauses['where'] = preg_replace_callback(
		"!{$wpdb->posts}.post_modified > '(.+?)'!is",
		function( $m ) use ( $query ) {
			global $wpdb;

			return $wpdb->prepare(
				"( {$wpdb->posts}.post_modified > %s OR {$wpdb->posts}.post_name = %s OR {$wpdb->posts}.post_title = %s )",
				$m[1],
				$query->get('s'),
				$query->get('s')
			);
		},
		$clauses['where']
	);

	// Prioritize exact-match slug/titles in search results
	$clauses['orderby'] = $wpdb->prepare(
		"( {$wpdb->posts}.post_name = %s OR {$wpdb->posts}.post_title = %s ) DESC, ",
		$query->get( 's' ),
		$query->get( 's' )
	) . $clauses['orderby'];

	return $clauses;
}

/**
 * Handle proper 404 errors for requests.
 */
function wporg_themes_parse_request( $wp ) {
	$sections = array(
		'new', 'updated', /*'featured',*/ 'favorites', 'popular'
	);

	if ( !empty( $wp->query_vars['browse'] ) && ! in_array( $wp->query_vars['browse'], $sections ) ) {
		$wp->handle_404();
	}
}
add_action( 'parse_request', 'wporg_themes_parse_request' );

/**
 * Remove support for any query vars the Theme Directory doesn't support/need.
 *
 * This should only apply to Rewrite rules, so WP_Query can use anything it needs.
 */
function wporg_themes_remove_query_vars( $qv ) {
	$not_needed = [
		'm', 'w', 'year', 'monthnum', 'day', 'hour', 'minute', 'second',
		'posts', 'withcomments', 'withoutcomments', 'favicon', 'cpage',
		'search', 'exact', 'sentence', 'calendar', 'more', 'tb', 'pb',
		'attachment_id', 'subpost', 'subpost_id', 'preview',
		'post_format', 'cat', 'category_name',
	];

	return array_diff( $qv, $not_needed );
}
add_filter( 'query_vars', 'wporg_themes_remove_query_vars', 0 );
