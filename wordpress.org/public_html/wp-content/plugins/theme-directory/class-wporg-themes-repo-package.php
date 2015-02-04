<?php

/**
 * Class WPORG_Themes_Repo_Package
 *
 * The WPORG_Themes_Repo_Package class extends the base package class for theme-specific info.
 * You can create one with new and pass it either a post or post id.
 */
class WPORG_Themes_Repo_Package extends Repo_Package {

	/**
	 * Returns the screenshot URL for a theme.
	 *
	 * @return string
	 */
	public function screenshot_url() {
		$screen = $this->wp_post->_screenshot;
		if ( ! $screen ) {
			$screen = sprintf( '//ts.w.org/wp-content/themes/%1$s/screenshot.png?ver=%2$s', $this->wp_post->post_name, $this->latest_version() );
		}

		return $screen;
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
				return $this->wp_post->_theme_url[ $version ];
			case 'author-url' :
				return $this->wp_post->_author_url[ $version ];
			case 'ticket' :
				return $this->wp_post->_ticket_id[ $version ];
			default:
				return $this->wp_post->$name;
		}
	}
}
