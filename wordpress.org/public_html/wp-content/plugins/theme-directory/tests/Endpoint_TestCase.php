<?php
/**
 * Base test case for the themes REST API endpoint tests.
 *
 * @package theme-directory
 */

use PHPUnit\Framework\TestCase;

/**
 * Shared helpers for dispatching requests against the themes endpoints.
 *
 * The theme-directory REST endpoints are include()d on rest_api_init, so
 * the action must only ever fire once per PHP process. That happens when
 * rest_get_server() first creates the server; afterwards the same
 * instance is reused.
 */
abstract class Theme_Directory_Endpoint_TestCase extends TestCase {

	/**
	 * Returns the REST server with all routes registered.
	 *
	 * @return \WP_REST_Server
	 */
	protected static function server() {
		return rest_get_server();
	}

	/**
	 * Creates a published theme post.
	 *
	 * @param string $slug  The theme slug.
	 * @param string $title The theme title.
	 * @param array  $args  Optional. Overrides for the post array.
	 * @return int The post ID.
	 */
	protected static function create_theme( $slug, $title, $args = array() ) {
		$defaults = array(
			'post_type'   => 'repopackage',
			'post_status' => 'publish',
			'post_name'   => $slug,
			'post_title'  => $title,
			'post_author' => self::theme_author(),
			// Version and screenshot meta, which fill_theme() requires.
			'meta_input'  => array(
				'_status'     => array( '1.0' => 'live' ),
				'_screenshot' => array( '1.0' => 'screenshot.png' ),
			),
		);

		return wp_insert_post( wp_parse_args( $args, $defaults ) );
	}

	/**
	 * Returns the shared theme author fixture, creating it if necessary.
	 *
	 * @return int The user ID.
	 */
	protected static function theme_author() {
		$author = get_user_by( 'login', 'theme-endpoint-test-author' );
		if ( $author ) {
			return $author->ID;
		}

		return wp_insert_user(
			array(
				'user_login' => 'theme-endpoint-test-author',
				'user_pass'  => wp_generate_password(),
				'user_email' => 'theme-endpoint-test-author@example.org',
			)
		);
	}

	/**
	 * Deletes a theme post.
	 *
	 * The plugin prevents repopackages from being deleted; detaches that
	 * specific guard while cleaning up the fixture post.
	 *
	 * @param int $post_id The post ID.
	 */
	protected static function delete_theme( $post_id ) {
		remove_filter( 'before_delete_post', 'wporg_theme_no_delete_repopackage' );
		wp_delete_post( $post_id, true );
		add_filter( 'before_delete_post', 'wporg_theme_no_delete_repopackage' );
	}

	/**
	 * Creates post_tag terms.
	 *
	 * The plugin restricts term creation to super admins; detaches that
	 * specific guard while creating the fixture terms.
	 *
	 * @param string[] $tags The tag names to create.
	 * @return int[] The created term IDs.
	 */
	protected static function create_tags( $tags ) {
		$term_ids = array();

		remove_filter( 'pre_insert_term', 'wporg_themes_pre_insert_term' );
		foreach ( $tags as $tag ) {
			$term = wp_insert_term( $tag, 'post_tag' );
			if ( ! is_wp_error( $term ) ) {
				$term_ids[] = $term['term_id'];
			}
		}
		add_filter( 'pre_insert_term', 'wporg_themes_pre_insert_term' );

		return $term_ids;
	}
}
