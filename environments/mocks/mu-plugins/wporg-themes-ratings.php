<?php
/**
 * Plugin Name: WordPress.org Ratings (Theme Directory local stub)
 * Description: Local stand-in for the wordpress.org ratings system the Theme
 *              Directory depends on. The production ratings table exists locally
 *              (created empty by the theme-directory env), but seeding real
 *              per-user rows is impractical, so this serves the rating summary
 *              from meta the importer stores on each repopackage post.
 *
 * @package theme-directory-env
 */

if ( ! class_exists( 'WPORG_Ratings' ) ) {
	/**
	 * Minimal stand-in for the wordpress.org ratings system.
	 *
	 * Production reads ratings from a dedicated ratings table; locally we serve
	 * the values seeded onto each repopackage post by the theme importer.
	 */
	class WPORG_Ratings {

		/**
		 * Return the rating counts (1-5) for a theme from its seeded post meta.
		 *
		 * @param string $type  Object type (unused, always 'theme' here).
		 * @param string $theme Theme slug.
		 * @return array
		 */
		public static function get_rating_counts( $type, $theme ) {
			$post = self::get_theme_post( $theme );

			return $post->ratings ?? array();
		}

		/**
		 * Return the average rating (0-5) for a theme from its seeded post meta.
		 *
		 * @param string $type  Object type (unused).
		 * @param string $theme Theme slug.
		 * @return float
		 */
		public static function get_avg_rating( $type, $theme ) {
			$post = self::get_theme_post( $theme );

			return $post->rating ?? 0;
		}

		/**
		 * Return the number of ratings for a theme from its seeded post meta.
		 *
		 * @param string $type  Object type (unused).
		 * @param string $theme Theme slug.
		 * @return int
		 */
		public static function get_rating_count( $type, $theme ) {
			$post = self::get_theme_post( $theme );

			return $post->num_ratings ?? 0;
		}

		/**
		 * Look up a repopackage post by slug.
		 *
		 * @param string $theme Theme slug.
		 * @return WP_Post|null
		 */
		private static function get_theme_post( $theme ) {
			$posts = get_posts( array(
				'post_type'   => 'repopackage',
				'post_status' => 'any',
				'name'        => $theme,
				'numberposts' => 1,
			) );

			return $posts ? $posts[0] : null;
		}
	}
}
