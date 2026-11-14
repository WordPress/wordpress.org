<?php
/**
 * Sends visitors on locale sites to the English article when the translation isn’t published.
 *
 * @package HelpHub
 */

declare( strict_types = 1 );

namespace WordPressdotorg\HelpHub\Locale_Fallback;

/**
 * Redirects a missing article on a locale site to the English article with the same slug.
 *
 * Editors who can see an unpublished translation get the translation, not a 404, so they never reach this.
 *
 * @return void
 */
function redirect_to_english_article(): void {
	if ( ! defined( 'IS_ROSETTA_NETWORK' ) || ! IS_ROSETTA_NETWORK || ! is_404() ) {
		return;
	}

	$slug = get_query_var( 'name' );

	if ( 'helphub_article' !== get_query_var( 'post_type' ) || ! is_string( $slug ) || '' === $slug ) {
		return;
	}

	$url = get_english_article_url( $slug );

	if ( $url ) {
		wp_safe_redirect( $url );
		exit;
	}
}
add_action( 'template_redirect', __NAMESPACE__ . '\redirect_to_english_article', 20 ); // After core’s old-slug and canonical redirects.

/**
 * Returns the URL of the published English article for a slug.
 *
 * @param string $slug Article slug, current or one the English article was renamed from.
 * @return string Article URL, or an empty string when there is no such article.
 */
function get_english_article_url( string $slug ): string {
	$blog_id = get_blog_id_from_url( 'wordpress.org', '/documentation/' );

	if ( ! $blog_id ) {
		return '';
	}

	$args = array(
		'post_type'   => 'helphub_article',
		'post_status' => 'publish',
		'fields'      => 'ids',
		'numberposts' => 1,
	);

	switch_to_blog( $blog_id );

	$post_ids = get_posts( $args + array( 'name' => $slug ) );

	if ( ! $post_ids ) {
		$post_ids = get_posts(
			$args + array(
				'meta_key'   => '_wp_old_slug',
				'meta_value' => $slug,
			)
		);
	}

	$post_name = $post_ids ? get_post_field( 'post_name', $post_ids[0] ) : '';

	restore_current_blog();

	return $post_name ? get_home_url( $blog_id, '/article/' . $post_name . '/' ) : '';
}
