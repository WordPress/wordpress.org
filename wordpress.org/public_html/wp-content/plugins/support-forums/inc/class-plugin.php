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
	 * @var int Plugin DB revision, increments when changes are made to rewrite rules.
	 */
	private static $db_version = 5637;

	// Define the properties for all the Support Forum components.
	public $users                = false;
	public $user_notes           = false;
	public $moderators           = false;
	public $hooks                = false;
	public $report_topic         = false;
	public $nsfw_handler         = false;
	public $stats                = false;
	public $emails               = false;
	public $audit_log            = false;
	public $dropin               = false;
	public $support_compat       = false;
	public $performance          = false;
	public $themes               = false;
	public $plugins              = false;
	public $plugin_subscriptions = false; // Defined via Support_Compat
	public $theme_subscriptions  = false; // Defined via Support_Compat
	public $blocks               = false;
	public $rest_api             = false;

	/**
	 * Define whether this is the global forums, or a locale forum.
	 * ie. https://wordpress.org/support/
	 *
	 * @var bool
	 */
	public $is_main_forums = false;

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
		$this->users        = new Users;
		$this->user_notes   = new User_Notes;
		$this->moderators   = new Moderators;
		$this->hooks        = new Hooks;
		$this->report_topic = new Report_Topic;
		$this->nsfw_handler = new NSFW_Handler;
		$this->stats        = new Stats;
		$this->emails       = new Emails;
		$this->audit_log    = new Audit_Log;
		$this->rest_api     = new REST_API;

		// Set a flag to indicate whether this is the global forums, or a locale forum.
		$this->is_main_forums = (
			defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) &&
			WPORG_SUPPORT_FORUMS_BLOGID == get_current_blog_id()
		);

		// These modifications are specific to https://wordpress.org/support/
		if ( $this->is_main_forums ) {
			$this->dropin          = new Dropin;
			$this->support_compat  = new Support_Compat;

			// Only load Performance_Optimizations if necessary.
			$this->performance     = new Performance_Optimizations;

			// Ratings_Compat is loaded by Theme_Directory_Compat or
			// Plugin_Directory_Compat depending on the request.
			$this->themes          = new Theme_Directory_Compat;
			$this->plugins         = new Plugin_Directory_Compat;
		}

		// Only load the Block Support if the Blocks Everywhere plugin is available.
		if ( class_exists( 'Automattic\Blocks_Everywhere\Blocks_Everywhere' ) ) {
			$this->blocks          = new Blocks;
		}

		add_action( 'bbp_add_rewrite_rules', array( $this, 'maybe_flush_rewrite_rules' ) );
	}

	/**
	 * Check the plugin version to see if rewrite rules should be flushed.
	 */
	public function maybe_flush_rewrite_rules() {
		$db_version_option_name = 'wporg_support_forums_plugin_db_version';

		if ( get_option( $db_version_option_name ) != self::$db_version ) {
			flush_rewrite_rules();
			update_option( $db_version_option_name, self::$db_version );
		}
	}

}
