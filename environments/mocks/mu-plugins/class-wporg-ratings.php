<?php
/**
 * Plugin Name: WordPress.org Ratings (local stub)
 * Description: Local stand-in for the wordpress.org ratings system that the
 *              plugin directory, theme directory and support forums all depend
 *              on. Reads and writes the `ratings` table when an environment
 *              provides one, and falls back to the rating summary the theme
 *              importer seeds onto each repopackage post.
 *
 * Ratings_Compat guards on class_exists( 'WPORG_Ratings' ) only, so a partial
 * stub is worse than none: the guard passes and the first missing method is a
 * fatal. Every method the directories and the forums call is implemented here.
 *
 * The public methods leave their parameters untyped, as production does. Callers
 * pass post meta and compat objects straight through, so a declared scalar type
 * would turn what production shrugs off into a TypeError only seen locally.
 *
 * @package wporg-env
 */

declare( strict_types = 1 );

if ( class_exists( 'WPORG_Ratings' ) ) {
	return;
}

/**
 * Stand-in for the wordpress.org ratings system.
 */
class WPORG_Ratings {

	/**
	 * Whether the `ratings` table is present, memoized for the request.
	 *
	 * @var bool|null
	 */
	private static $has_table = null;

	/**
	 * Whether this environment provides the production `ratings` table.
	 *
	 * @global \wpdb $wpdb
	 *
	 * @return bool
	 */
	private static function has_table(): bool {
		global $wpdb;

		if ( null === self::$has_table ) {
			self::$has_table = (bool) $wpdb->get_var( "SHOW TABLES LIKE 'ratings'" );
		}

		return self::$has_table;
	}

	/**
	 * The posts table the review topics live in.
	 *
	 * Reviews are topics on the support forums site, which is a different blog
	 * from the directory the rating is about.
	 *
	 * @global \wpdb $wpdb
	 *
	 * @return string
	 */
	private static function reviews_table(): string {
		global $wpdb;

		if ( defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) && is_multisite() ) {
			return $wpdb->get_blog_prefix( (int) WPORG_SUPPORT_FORUMS_BLOGID ) . 'posts';
		}

		return $wpdb->posts;
	}

	/**
	 * Published rating rows for one directory object.
	 *
	 * @global \wpdb $wpdb
	 *
	 * @param string $type Object type, plugin or theme.
	 * @param string $slug Object slug.
	 * @return array List of objects with user_id and rating.
	 */
	private static function get_ratings( string $type, string $slug ): array {
		global $wpdb;

		if ( ! self::has_table() ) {
			return array();
		}

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT r.user_id, r.rating FROM ratings AS r
				 INNER JOIN %i AS p ON p.ID = r.post_id
				 WHERE r.object_type = %s AND r.object_slug = %s AND p.post_status = %s',
				self::reviews_table(),
				$type,
				$slug,
				'publish'
			)
		);
	}

	/**
	 * The summary the theme importer seeds onto a repopackage post.
	 *
	 * Deliberately a direct query rather than get_posts(). Ratings_Compat is
	 * constructed from Directory_Compat::maybe_load() on pre_get_posts, so a
	 * WP_Query here re-enters that hook and recurses until memory runs out.
	 *
	 * @global \wpdb $wpdb
	 *
	 * @param string $slug Theme slug.
	 * @param string $key  Meta key: rating, num_ratings or ratings.
	 * @return mixed Meta value, or null when there is no such theme.
	 */
	private static function get_seeded_theme_meta( string $slug, string $key ) {
		global $wpdb;

		// repopackage posts live on the theme directory blog, not the one the request is rendering.
		$switched = defined( 'WPORG_THEME_DIRECTORY_BLOGID' )
			&& is_multisite()
			&& get_current_blog_id() !== (int) WPORG_THEME_DIRECTORY_BLOGID;

		if ( $switched ) {
			switch_to_blog( (int) WPORG_THEME_DIRECTORY_BLOGID );
		}

		$post_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s LIMIT 1",
				$slug,
				'repopackage'
			)
		);

		$meta = $post_id ? get_post_meta( $post_id, $key, true ) : null;

		if ( $switched ) {
			restore_current_blog();
		}

		return $meta;
	}

	/**
	 * Count ratings at each star level.
	 *
	 * @param string $type Object type.
	 * @param string $slug Object slug.
	 * @return array Counts keyed 1 through 5.
	 */
	public static function get_rating_counts( $type, $slug ): array {
		$counts  = array_fill( 1, 5, 0 );
		$ratings = self::get_ratings( (string) $type, (string) $slug );

		if ( ! $ratings && 'theme' === $type ) {
			$seeded = self::get_seeded_theme_meta( (string) $slug, 'ratings' );
			return $seeded ? array_replace( $counts, (array) $seeded ) : $counts;
		}

		foreach ( $ratings as $row ) {
			$star = (int) $row->rating;
			if ( isset( $counts[ $star ] ) ) {
				++$counts[ $star ];
			}
		}

		return $counts;
	}

	/**
	 * Count ratings, optionally at one star level.
	 *
	 * @param string $type   Object type.
	 * @param string $slug   Object slug.
	 * @param int    $rating Star level, or 0 for all of them.
	 * @return int
	 */
	public static function get_rating_count( $type, $slug, $rating = 0 ): int {
		$rating = (int) $rating;
		$counts = self::get_rating_counts( $type, $slug );

		if ( $rating ) {
			return (int) ( $counts[ $rating ] ?? 0 );
		}

		// The API's distribution need not add up to num_ratings, so prefer the stored total.
		if ( 'theme' === $type && ! self::get_ratings( (string) $type, (string) $slug ) ) {
			return (int) self::get_seeded_theme_meta( (string) $slug, 'num_ratings' );
		}

		return (int) array_sum( $counts );
	}

	/**
	 * Average rating on a 0 to 5 scale.
	 *
	 * @param string $type Object type.
	 * @param string $slug Object slug.
	 * @return float
	 */
	public static function get_avg_rating( $type, $slug ): float {
		$ratings = array_column( self::get_ratings( (string) $type, (string) $slug ), 'rating' );

		if ( ! $ratings ) {
			return 'theme' === $type ? (float) self::get_seeded_theme_meta( (string) $slug, 'rating' ) : 0.0;
		}

		return array_sum( $ratings ) / count( $ratings );
	}

	/**
	 * One user's rating of a directory object.
	 *
	 * @param string $type    Object type.
	 * @param string $slug    Object slug.
	 * @param int    $user_id User ID.
	 * @return int The rating, or 0 when the user has not rated it.
	 */
	public static function get_user_rating( $type, $slug, $user_id ): int {
		foreach ( self::get_ratings( (string) $type, (string) $slug ) as $row ) {
			if ( (int) $user_id === (int) $row->user_id ) {
				return (int) $row->rating;
			}
		}

		return 0;
	}

	/**
	 * Store one rating per user and directory object.
	 *
	 * @global \wpdb $wpdb
	 *
	 * @param int    $post_id Review topic ID.
	 * @param string $type    Object type.
	 * @param string $slug    Object slug.
	 * @param int    $user_id User ID.
	 * @param int    $rating  Star level, 1 through 5.
	 * @return void
	 */
	public static function set_rating( $post_id, $type, $slug, $user_id, $rating ): void {
		global $wpdb;

		$rating = (int) $rating;

		if ( ! self::has_table() || $rating < 1 || $rating > 5 ) {
			return;
		}

		// Relies on the table's UNIQUE KEY over object_type, object_slug and user_id.
		$wpdb->replace(
			'ratings',
			array(
				'post_id'     => (int) $post_id,
				'object_type' => (string) $type,
				'object_slug' => (string) $slug,
				'user_id'     => (int) $user_id,
				'rating'      => $rating,
			),
			array( '%d', '%s', '%s', '%d', '%d' )
		);

		self::clear_cache( $post_id );
	}

	/**
	 * Refresh a review. Reads are uncached locally, so only the post matters.
	 *
	 * @param int    $post_id Review topic ID.
	 * @param string $type    Object type. Unused; part of the production signature.
	 * @param string $slug    Object slug. Unused; part of the production signature.
	 * @param int    $user_id User ID. Unused; part of the production signature.
	 * @return void
	 */
	public static function clear_cache( $post_id, $type = '', $slug = '', $user_id = 0 ): void {
		unset( $type, $slug, $user_id );

		clean_post_cache( (int) $post_id );
	}

	/**
	 * Drop the stored ratings for a permanently deleted review.
	 *
	 * @global \wpdb $wpdb
	 *
	 * @param int $post_id Deleted post ID.
	 * @return void
	 */
	public static function delete_rating( $post_id ): void {
		global $wpdb;

		if ( self::has_table() ) {
			$wpdb->delete( 'ratings', array( 'post_id' => (int) $post_id ), array( '%d' ) );
		}
	}

	/**
	 * An accessible star summary.
	 *
	 * @param float $rating Star rating.
	 * @return string Escaped HTML.
	 */
	public static function get_dashicons_stars( $rating ): string {
		$rating = (float) $rating;

		// Callers pass stored meta straight through, so an out-of-range value must not reach str_repeat().
		$stars = min( 5, max( 0, (int) round( $rating ) ) );

		return sprintf(
			'<span class="wporg-ratings" aria-label="%s">%s</span>',
			esc_attr( sprintf( '%.1f out of 5 stars', $rating ) ),
			esc_html( str_repeat( '★', $stars ) . str_repeat( '☆', 5 - $stars ) )
		);
	}

	/**
	 * The rating input the real review submission handler reads.
	 *
	 * @param string $type     Object type.
	 * @param string $slug     Object slug.
	 * @param bool   $required Whether a rating is required.
	 * @return void
	 */
	public static function get_dashicons_form( $type, $slug, $required = false ): void {
		$current = self::get_user_rating( $type, $slug, get_current_user_id() ) ?: 5;

		printf(
			'<select name="rating" id="rating" aria-label="Rating"%s>',
			$required ? ' required' : ''
		);

		for ( $stars = 5; $stars >= 1; $stars-- ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $stars is a loop integer; selected() returns a fixed attribute.
			printf(
				'<option value="%1$d"%2$s>%1$d</option>',
				(int) $stars,
				selected( $stars, $current, false )
			);
		}

		echo '</select>';
	}
}

add_action( 'deleted_post', array( 'WPORG_Ratings', 'delete_rating' ) );
