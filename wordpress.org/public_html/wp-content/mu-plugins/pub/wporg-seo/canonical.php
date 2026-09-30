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
		// Bail early for taxonomy queries that have no queried objects.
		// This is most likely a 404 request for a term that doesn't exist.
		if ( ! $queried_object ) {
			return false;
		}

		$url = get_term_link( $queried_object );

		// TODO: This isn't strictly correct for nested taxonomies.
		$glue = ( 'AND' === $wp_query->tax_query->relation ) ? '+' : ',';
		foreach ( $wp_query->tax_query->queried_terms as $taxonomy => $term_data ) {
			if ( $taxonomy === $queried_object->taxonomy ) {
				// TODO: If multiple tax_queries for the same taxonomy are included, this will miss those.
				if ( count( $term_data['terms'] ) > 1 ) {
					$url = str_replace(
						'/' . $queried_object->slug . '/',
						'/' . implode( $glue, $term_data['terms'] ) . '/',
						$url
					);
				}

				continue;
			}

			// If we have other taxonomies, append as query vars.
			$tax_obj = get_taxonomy( $taxonomy );
			if ( ! empty( $tax_obj->query_var ) ) {
				$url = add_query_arg(
					$tax_obj->query_var,
					urlencode( implode( $glue, $term_data['terms'] ) ),
					$url
				);
			}
		}
	} elseif ( is_singular() ) {
		$url = get_permalink( $queried_object );
	} elseif ( is_search() ) {
		$url = home_url( 'search/' . urlencode( get_query_var( 's' ) ) . '/' );
	} elseif ( is_author() && $queried_object instanceOf \WP_User ) {
		// On WordPress.org get_author_posts_url() returns profile.wordpress.org links. Build it manually
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

	$url = apply_filters( 'wporg_canonical_url', $url );

	// Force canonical links to be lowercase.
	// See https://meta.trac.wordpress.org/ticket/4414
	$url = mb_strtolower( $url, 'UTF-8' );

	return $url;
}
