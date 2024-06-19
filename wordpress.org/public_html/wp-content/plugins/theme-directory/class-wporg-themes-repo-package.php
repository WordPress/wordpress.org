<?php

/**
 * Class WPORG_Themes_Repo_Package
 *
 * The WPORG_Themes_Repo_Package class wraps the WP_Post class with theme-specific info.
 * You can create one with new and pass it either a WP_Post, post ID, or slug.
 */
class WPORG_Themes_Repo_Package {

	/**
	 * Holds a WP_Post object representing this post.
	 *
	 * @var WP_Post
	 */
	public $wp_post;

	/**
	 * Construct a new Package for the given post ID or object.
	 *
	 * @param WP_Post|int|slug $wp_post The Post object, Post ID, or theme slug of the package.
	 */
	public function __construct( $wp_post = 0 ) {
		global $post;
		if ( ! $wp_post ) {
			return;
		}

		if ( $wp_post instanceof WP_Post ) {
			$this->wp_post = $wp_post;

		} elseif ( is_numeric( $wp_post ) ) {
			$this->wp_post = get_post( $wp_post );

		} elseif (
			is_string( $wp_post ) &&
			! empty( $post ) &&
			$post->post_name === $wp_post &&
			$post->post_type === 'repopackage'
		) {
			$this->wp_post = $post;

		} elseif ( is_string( $wp_post ) ) {
			$theme = get_posts( array(
				'name'        => $wp_post,
				'post_type'   => 'repopackage',
				'post_status' => 'any',
				'numberposts' => 1,
			) );
		
			if ( $themes ) {
				$this->wp_post = $theme[0];
			}
		}
	}

	/**
	 * Returns the screen shot URL for a theme.
	 *
	 * @return string
	 */
	public function screenshot_url() {
		$screen  = 'screenshot.png';
		$version = $this->latest_version();

		if ( ! empty( $this->wp_post->_screenshot[ $version ] ) ) {
			$screen = $this->wp_post->_screenshot[ $version ];
		}

		return sprintf( 'https://i0.wp.com/themes.svn.wordpress.org/%1$s/%2$s/%3$s',
			$this->wp_post->post_name,
			$version,
			$screen
		);
	}

	/**
	 * Returns the latest version number for a theme.
	 *
	 * The latest published version, or the latest version for unpublished themes.
	 *
	 * @return int|string
	 */
	public function latest_version() {
		$status = get_post_meta( $this->wp_post->ID, '_status', true );

		if ( empty( $status ) ) {
			return '';
		}

		uksort( $status, 'version_compare' );

		// Find if there is a live version, and use that one.
		$latest = array_search( 'live', $status );

		// If none, just get the latest version.
		if ( ! $latest ) {
			$versions = array_keys( $status );
			$latest   = array_pop( $versions );
		}

		return $latest;
	}

	/**
	 * Returns the download URL for a theme.
	 *
	 * @param string $version Optional.
	 * @return string
	 */
	public function download_url( $version = 'latest-stable' ) {
		if ( 'latest-stable' === $version ) {
			$version = $this->latest_version();
		}

		$url  = 'http://downloads.wordpress.org/theme/';
		$file = $this->wp_post->post_name . '.' . $version . '.zip';

		$file = preg_replace( '/[^a-z0-9_.-]/i', '', $file );
		$file = preg_replace( '/[.]+/', '.', $file );

		return set_url_scheme( $url . $file );
	}

	/**
	 * Returns the preview URL for a theme.
	 */
	public function preview_url() {
		$link = 'https://wp-themes.com/' . $this->wp_post->post_name . '/';

		if ( $this->blueprint || isset( $_GET['playground-preview'] ) ) {
			$link = 'https://playground.wordpress.net/?mode=seamless&blueprint-url=' . urlencode( rest_url( 'themes/v1/preview-blueprint/' . $this->wp_post->post_name ) );
		}

		return $link;
	}

	/**
	 * Magic getter for a few handy variables.
	 *
	 * @param string $name
	 * @return int|string
	 */
	public function __get( $name ) {
		$version = $this->latest_version();
		switch ( $name ) {
			case 'version' :
				return $version;
			case 'theme-url' :
				return $this->wp_post->_theme_url[ $version ] ?? '';
			case 'author-url' :
				return $this->wp_post->_author_url[ $version ] ?? '';
			case 'ticket' :
				return $this->wp_post->_ticket_id[ $version ] ?? '';
			case 'requires':
				return $this->wp_post->_requires[ $version ] ?? '';
			case 'requires-php':
				return $this->wp_post->_requires_php[ $version ] ?? '';
			default:
				return $this->wp_post->$name;
		}
	}
}
