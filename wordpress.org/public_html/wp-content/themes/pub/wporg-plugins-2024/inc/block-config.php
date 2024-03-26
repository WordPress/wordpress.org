<?php
/**
 * Set up configuration for dynamic blocks.
 */

namespace WordPressdotorg\Theme\Plugins_2024\Block_Config;

add_filter( 'wporg_block_navigation_menus', __NAMESPACE__ . '\add_site_navigation_menus' );
add_filter( 'wporg_query_filter_options_sort', __NAMESPACE__ . '\wporg_query_filter_options_sort' );
add_filter( 'wporg_query_filter_options_business_model', __NAMESPACE__ . '\wporg_query_filter_options_business_model' );
add_filter( 'wporg_query_filter_in_form', __NAMESPACE__ . '\wporg_query_filter_in_form' );
add_filter( 'wporg_query_total_label', __NAMESPACE__ . '\wporg_query_total_label', 10, 2 );
add_filter( 'render_block_core/search', __NAMESPACE__ . '\filter_search_block' );
add_filter( 'render_block_core/site-title', __NAMESPACE__ . '\filter_site_title_block' );

/**
 * Provide a list of local navigation menus.
 */
function add_site_navigation_menus( $menus ) {
	$items = array(
		'plugins' => array(
			array(
				'label' => __( 'My Favorites', 'wporg-plugins' ),
				'url' => '/browse/favorites/',
			),
			array(
				'label' => __( 'Submit a plugin', 'wporg-plugins' ),
				'url' => '/developers/',
			),
		),
		'section-bar' => array(
			array(
				'label' => __( 'All', 'wporg-plugins' ),
				'url' => home_url( '/' ),
			),
			array(
				'label' => __( 'Community', 'wporg-plugins' ),
				'url'   => home_url( '?plugin_business_model=community' ),
				'term'  => get_term_by( 'slug', 'community', 'plugin_business_model' ),
			),
			array(
				'label' => __( 'Commercial', 'wporg-plugins' ),
				'url'   => home_url( '?plugin_business_model=commercial' ),
				'term'  => get_term_by( 'slug', 'commercial', 'plugin_business_model' ),
			),
			/*
			array(
				'label' => __( 'Block-Enabled', 'wporg-plugins' ),
				'term'  => get_term_by( 'slug', 'blocks', 'plugin_section' ),
			),
			array(
				'label' => __( 'Featured', 'wporg-plugins' ),
				'term'  => get_term_by( 'slug', 'featured', 'plugin_section' ),
			),
			array(
				'label' => __( 'Beta', 'wporg-plugins' ),
				'term'  => get_term_by( 'slug', 'beta', 'plugin_section' ),
			),
			array(
				'label' => __( 'Popular', 'wporg-plugins' ),
				'term'  => get_term_by( 'slug', 'popular', 'plugin_section' ),
			),*/
		)
	);

	/*
	// Not usually in the menu, but we need to show these somehow.
	if ( is_tax( 'plugin_section', 'adopt-me' ) ) {
		$items['section-bar'][] = array(
			'label' => _x( 'Adopt Me', 'Plugin Section Name', 'wporg-plugins' ),
			'term'  => get_term_by( 'slug', 'adopt-me', 'plugin_section' )
		);
	} elseif ( is_tax( 'plugin_tags' ) ) {
		$items['section-bar'][] = array(
			'label' => sprintf( __( 'Tag: %s', 'wporg-plugins' ), single_term_title( '', false ) ),
			'term'  => get_queried_object()
		);
	}
	*/

	return $items;
}

function wporg_query_filter_options_sort() {
	global $wp_query;
	$orderby = strtolower( $wp_query->query['orderby'] ?? '' );
	$order   = strtolower( $wp_query->query['order'] ?? '' );
	$sort     = $orderby . ( $order ? '_' . $order : '' );

	$options = array(
		'relevance'       => __( 'Relevance', 'wporg-plugins' ),
		'active_installs' => __( 'Most Used', 'wporg-plugins' ),
		'rating'          => __( 'Rating', 'wporg-plugins' ),
		'ratings'         => __( 'Reviews', 'wporg-plugins' ),
		'last_updated'    => __( 'Recently Updated', 'wporg-plugins' ),
		'date_desc'       => __( 'Newest', 'wporg-plugins' ),
		'tested'          => __( 'Tested Up to', 'wporg-plugins' ),
	);

	// Remove relevance for non-search.
	if ( ! is_search() ) {
		unset( $options['relevance'] );
	} else {
		// Temporarily disable on search, until the ES integration supports it.
		return false;
	}

	$label = __( 'Sort', 'wporg-plugins' );
	if ( $sort && isset( $options[ $sort ] ) ) {
		/* translators: 'Sort: Rating' or 'Sort: Most Used', etc. */
		$label = sprintf( __( 'Sort: %s', 'wporg-plugins' ), $options[ $sort ] );
	}

	return array(
		'label'    => $label,
		'title'    => __( 'Sort', 'wporg-plugins' ),
		'key'      => 'orderby',
		'action'   => '',
		'options'  => $options,
		'selected' => [ $sort ],
	);
}

function wporg_query_filter_options_business_model() {
	$options = array(
		'commercial' => __( 'Commercial', 'wporg-plugins' ),
		'community' => __( 'Community', 'wporg-plugins' ),
	);
	$label = __( 'Type', 'wporg-plugins' );
	if ( get_query_var( 'plugin_business_model' ) && isset( $options[ get_query_var( 'plugin_business_model' ) ] ) ) {
		$label = sprintf( __( 'Type: %s', 'wporg-plugins' ), $options[ get_query_var( 'plugin_business_model' ) ] );
	}

	return array(
		'label'    => $label,
		'title'    => __( 'Type', 'wporg-plugins' ),
		'key'      => 'plugin_business_model',
		'action'   => '',
		'options'  => $options ,
		'selected' => [ get_query_var( 'plugin_business_model' ) ],
	);
}

function wporg_query_filter_in_form( $key ) {
	global $wp_query;

	foreach ( $wp_query->query as $query_var => $values ) {
		if ( $key === $query_var ) {
			continue;
		}

		$array  = is_array( $values );
		$values = (array) $values;
		foreach ( $values as $value ) {
			if ( is_search() && 's' === $query_var ) {
				continue;
			} elseif ( 'plugin_tags' === $query_var ) {
				// Don't include it if it's the current term.
				if ( is_tax( 'plugin_tags', $value ) ) {
					continue;
				}
			} elseif ( 'browse' === $query_var ) {
				// Don't retain if there's no actual items in the section (ie. it's dynamic).
				$term = get_term_by( 'slug', $value, 'plugin_section' );
				if ( ! $term || ! $term->count ) {
					continue;
				}
			}

			printf(
				'<input type="hidden" name="%s" value="%s" />',
				esc_attr( $query_var ) . ( $array ? '[]' : '' ),
				esc_attr( $value )
			);
		}
	}

	// If this is a block directory search, that needs to be retained too.
	if ( is_search() && get_query_var( 'block_search' ) ) {
		echo '<input type="hidden" name="block_search" value="1" />';
	}

}

function wporg_query_total_label( $label, $count ) {
	return _n( '%s plugin', '%s plugins', $count, 'wporg-plugins' );
}

/**
 * Filters the search block to remove the required attribute, and add the query fields.
 *
 * @param string $block_content
 * @return string
 */
function filter_search_block( $block_content ) {
	// Remove the required attribute
	$block_content = preg_replace( '/(<input[^>]*)\s+required\s*([^>]*)>/', '$1$2>', $block_content );

	/* Temporarily disable this until filters are enabled.
	// Insert the current query filters into the search form.
	ob_start();
	wporg_query_filter_in_form( 's' );
	$block_content = str_replace( '</form>', ob_get_clean() . '</form>', $block_content );
	*/

	return $block_content;
}

/**
 * Filters the site title block to use the "proper" slashed home url.
 *
 * @see https://github.com/WordPress/wordpress-develop/blob/6a0e4aa570b6b9e7ce6d630b8d92e2f5091aac6b/src/wp-includes/blocks/site-title.php#L37
 *
 * @param string $block_content
 * @return string
 */
function filter_site_title_block( $block_content ) {
	$block_content = str_replace(
		'href="' . home_url() . '"',
		'href="' . home_url( '/' ) . '"',
		$block_content
	);

	return $block_content;
}
