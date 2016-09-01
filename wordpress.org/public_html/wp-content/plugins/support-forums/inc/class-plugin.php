<?php

namespace WordPressdotorg\Forums;

class Plugin {

	/**
	 * Set constants for existing forums.
	 */
	const THEMES_FORUM_ID   = 21261;
	const PLUGINS_FORUM_ID  = 21262;
	const REVIEWS_FORUM_ID  = 21272;

	/**
	 * @var Plugin The singleton instance.
	 */
	private static $instance;

	/**
	 * Always return the same instance of this plugin.
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( ! ( self::$instance instanceof Plugin ) ) {
			self::$instance = new Plugin();
		}
		return self::$instance;
	}

	/**
	 * Instantiate a new Plugin object.
	 */
	private function __construct() {
		$this->performance = new Performance_Optimizations;
		$this->users       = new Users;
		$this->moderators  = new Moderators;
		$this->hooks       = new Hooks;

		// These modifications are specific to https://wordpress.org/support/
		$blog_id = get_current_blog_id();
		if ( $blog_id && defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) && WPORG_SUPPORT_FORUMS_BLOGID == $blog_id ) {
			$this->dropin  = new Dropin;
			$this->themes  = new Theme_Directory_Compat;
			$this->plugins = new Plugin_Directory_Compat;
		}
	}
}
