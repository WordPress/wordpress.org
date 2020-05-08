<?php
namespace WordPressdotorg\SEO\Canonical;
/**
 * Adds canonical-related functionality.
 * @see https://core.trac.wordpress.org/ticket/18660
 */

/**
 * Outputs a <link rel="canonical"> on most pages.
 */
function rel_canonical_link() {
	if ( $url = get_canonical_url() ) {
		printf(
			'<link rel="canonical" href="%s">' . "\n",
			esc_url( $url )
		);
	}
}
add_action( 'wp_head', __NAMESPACE__ . '\rel_canonical_link' );
add_action( 'login_head',  __NAMESPACE__ . '\rel_canonical_link' );

/**
 * Outputs a Canonical Link header on needed pages.
 */
function rel_canonical_header() {
	$url = get_canonical_url();
	if ( ! $url || headers_sent() ) {
		return;
	}

	$canonical = false;

	if ( is_feed() && ! is_404() && is_archive() && ! is_comment_feed() ) {
		// $url will NOT contain /feed/ so we can't output this on is_comment_feed()
		$canonical = true;
	}

	if ( $canonical ) {
		header( sprintf(
			'Link: <%s>; rel="canonical"',
			esc_url( $url )
		) );
	}
}
add_action( 'template_redirect', __NAMESPACE__ . '\rel_canonical_header', 1000 );

remove_action( 'wp_head', 'rel_canonical' );

/**
 * Get the current Canonical URL.
 */
function get_canonical_url() {
	global $wp, $wp_query, $wp_rewrite;

	$queried_object = get_queried_object();
	$url = false;

	if ( is_tax() || is_tag() || is_category() ) {
		$url = get_term_link( $queried_object );

		// Detect multi-term queries.
		// Create a copy and remove the 'relation' param if present.
		$tax_queries = $wp_query->tax_query->queries;
		unset( $tax_queries['relation'] );

		$term_queries    = count( $tax_queries );
		$term_query_zero = count( $tax_queries[0]['terms'] );
		if ( $term_queries > 1 || $term_query_zero > 1 ) {
			// Multiple terms are being queried for.
			$terms = wp_list_pluck( $tax_queries, 'terms' );
			$terms = call_user_func_array( 'array_merge', $terms );

			// Determine how many taxonomies are involved in this query.
			$taxonomies = array_unique( wp_list_pluck( $tax_queries, 'taxonomy' ) );

			if ( count( $taxonomies ) > 1 ) {
				// Multiple-taxonomy query. No canonical produced.
				// TODO: Edgecase: on a site where a taxonomy query is added via pre_get_posts this will result in no canonical produced.
				$url = false;
			} elseif ( $term_queries > 1 && 1 === $term_query_zero ) {
				// AND +
				$glue = '+';
			} elseif ( $term_query_zero > 1 && 1 === $term_queries && 'AND' === $wp_query->tax_query->relation ) {
				if ( 'AND' === $tax_queries[0]['operator'] ) {
					// AND +
					$glue = '+';
				} else {
					// Union ,
					$glue = ',';
				}
			} else {
				$url = false;
			}

			if ( $url ) {
				$url = str_replace(
					'/' . $queried_object->slug . '/',
					'/' . implode( $glue, $terms ) . '/',
					$url
				);
			}
		}
	} elseif ( is_singular() ) {
		$url = get_permalink( $queried_object );
	} elseif ( is_search() ) {
		$url = home_url( 'search/' . urlencode( get_query_var( 's' ) ) . '/' );
	} elseif ( is_author() ) {
		// On WordPress.org get_author_posts_url() returns profile.wordpress.org links. Build it manually.
		$url = home_url( 'author/' . $queried_object->user_nicename . '/' );
	} elseif ( is_post_type_archive() ) {
		$url = get_post_type_archive_link( $queried_object->name );
	} elseif ( is_home() ) {
		$url = get_post_type_archive_link( 'post' );
	} elseif ( is_front_page() ) {
		$url = home_url( '/' );
	} elseif ( is_date() ) {
		if ( is_day() ) {
			$url = get_day_link( get_query_var('year'), get_query_var('monthnum'), get_query_var('day') );
		} elseif ( is_month() ) {
			$url = get_month_link( get_query_var('year'), get_query_var('monthnum') );
		} elseif ( is_year() ) {
			$url = get_year_link( get_query_var('year') );
		}
	}

	// Filter to override the above logics.
	$url = apply_filters( 'wporg_canonical_base_url', $url );

	// Certain routes, such as `get_term_link()` can return WP_Error objects.
	if ( is_wp_error( $url ) ) {
		$url = false;
	}

	// Ensure trailing slashed paths.
	if ( $url ) {
		// Slash before ?.. and/or #...
		$url = preg_replace( '!^([^?#]*?)([^/])((\?.*)?(#.*)?)$!', '$1$2/$3', $url );
	}

	if ( $url && is_paged() && (int) get_query_var( 'paged' ) > 1 ) {
		if ( false !== stripos( $url, '?' ) ) {
			// We're not actually sure 100% here if the current url supports rewrite rules.
			$url = add_query_arg( 'paged', (int) get_query_var( 'paged' ), $url );
		} else {
			$url = rtrim( $url, '/' ) . '/' . $wp_rewrite->pagination_base . '/' . (int) get_query_var( 'paged' ) . '/';
		}
	}

	// Add order/orderby to Archives.
	if ( is_archive() || is_search() || is_home() ) {
		// Check $wp since `get_query_var()` will return default values too.
		if ( !empty( $wp->query_vars[ 'order'] ) ) {
			$url = add_query_arg( 'order', get_query_var( 'order' ), $url );
		}
		if ( !empty( $wp->query_vars[ 'orderby'] ) ) {
			$url = add_query_arg( 'orderby', strtolower( get_query_var( 'orderby' ) ), $url );
		}
	}

	$url = apply_filters( 'wporg_canonical_url', $url );

	// Force canonical links to be lowercase.
	// See https://meta.trac.wordpress.org/ticket/4414
	$url = mb_strtolower( $url, 'UTF-8' );

	return $url;
}
