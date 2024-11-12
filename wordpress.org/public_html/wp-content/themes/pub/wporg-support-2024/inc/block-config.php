<?php
/**
 * Set up configuration for dynamic blocks.
 */

namespace WordPressdotorg\Forums\Block_Config;

/**
 * Actions and filters.
 */
add_filter( 'render_block_context', __NAMESPACE__ . '\wporg_render_block_context', 10, 3 );
add_filter( 'wporg_ratings_data', __NAMESPACE__ . '\wporg_set_rating_data', 10, 2 );

/**
 * Update ratings blocks with real rating data.
 *
 * @param array $data    Rating data.
 * @param int   $post_id Current post.
 *
 * @return array
 */
function wporg_set_rating_data( $data, $post_id ) {
	$post = wporg_support_get_compat_object();

	if ( ! class_exists( '\WPORG_Ratings' ) ) {
		return $data;
	}

	$rating  = \WPORG_Ratings::get_avg_rating( $post->type, $post->post_name ) ?: 0;
	$ratings = \WPORG_Ratings::get_rating_counts( $post->type, $post->post_name ) ?: array();

	/**
	 * Why do we multiply the average rating by 20?
	 * The themes API does it this way, and the rating plugin was built to fit that. 
	 * Instead of redoing everything, multiplying here keeps things simple and works well.
	 *
	 * @see theme-directory/class-themes-api.php for more info.
	 */
	$adjusted_rating = $rating * 20;

	return array(
		'rating' => $adjusted_rating,
		'ratingsCount' => array_sum( $ratings ),
		'ratings' => $ratings,
		'supportUrl' => esc_url( sprintf( home_url( '/%s/%s/reviews/' ), $post->type, $post->post_name ) )
	);
}

/**
 * Modifies the block context to include a `postId` for specific blocks.
 * 
 * The `wporg/ratings-stars` and `wporg/ratings-bars` require a postId. Due to context, it's unavailable.
 * 
 * @param array      $context      The current block context.
 * @param array      $parsed_block The block being rendered.
 * @param WP_Block|null $parent_block Optional. The parent block, if any.
 *
 * @return array The modified block context.
 */
function wporg_render_block_context( $context, $parsed_block, $parent_block ) {
	if ( isset( $parsed_block['blockName'] ) && 
		in_array( $parsed_block['blockName'], [ 'wporg/ratings-stars', 'wporg/ratings-bars' ], true ) ) {

		$compat_object = wporg_support_get_compat_object();

		if ( $compat_object ) {
			$context = array_merge( $context, [ 'postId' => $compat_object->ID ] );
		}
	}

	return $context;
}
