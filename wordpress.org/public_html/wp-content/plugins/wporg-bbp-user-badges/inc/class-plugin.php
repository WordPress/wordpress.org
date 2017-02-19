<?php

namespace WordPressdotorg\Forums\User_Badges;

defined( 'ABSPATH' ) or die();

class Plugin {

	/**
	 * @access private
	 * @var string The prefix for the plugin directory tables.
	 */
	private static $plugins_table_prefix;

	/**
	 * @access private
	 * @var string The prefix for the theme directory tables.
	 */
	private static $themes_table_prefix;

	/**
	 * @access private
	 * @var Plugin The singleton instance.
	 */
	private static $instance;

	/**
	 * Returns always the same instance of this plugin.
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( ! ( self::$instance instanceof self ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Instantiates a new Plugin object.
	 */
	private function __construct() {
		self::$plugins_table_prefix = 'wporg_' . WPORG_PLUGIN_DIRECTORY_BLOGID . '_';
		self::$themes_table_prefix  = 'wporg_' . WPORG_THEME_DIRECTORY_BLOGID . '_';

		add_action( 'bbp_loaded', array( $this, 'bbp_loaded' ) );
	}

	/**
	 * Initializes the plugin.
	 */
	public function bbp_loaded() {
		// Add class to div containing reply.
		add_filter( 'bbp_get_topic_class', array( $this, 'bbp_get_topic_class' ), 10, 2 );
		add_filter( 'bbp_get_reply_class', array( $this, 'bbp_get_reply_class' ), 10, 2 );

		// Add badge before reply author info.
		add_action( 'bbp_theme_before_topic_author_details', array( $this, 'show_topic_author_badge' ) );
		add_action( 'bbp_theme_before_reply_author_details', array( $this, 'show_reply_author_badge' ) );
	}

	/**
	 * Return information about the resource item author if they merit a badge.
	 *
	 * Author badge is only applicable in support or reviews forums for a plugin
	 * or theme to which the author is listed as a committer or a contributor.
	 *
	 * @access protected
	 *
 	 * @param string $item_type The type of thing whose author is being checked
	 *                          for badge info. One of 'topic' or 'reply'.
	 * @param int    $item_id   The ID of the item getting badge assigned.
	 * @return array|false      Associative array with keys 'type', 'slug', and
	 *                          'user_login' if author merits a badge, else false.
	 */
	protected function get_author_badge_info( $item_type, $item_id ) {
		if ( ! class_exists( '\WordPressdotorg\Forums\Plugin' ) ) {
			return false;
		}

		$badgeable_forums = array(
			\WordPressdotorg\Forums\Plugin::PLUGINS_FORUM_ID,
			\WordPressdotorg\Forums\Plugin::REVIEWS_FORUM_ID,
			\WordPressdotorg\Forums\Plugin::THEMES_FORUM_ID,
		);

		if ( 'topic' === $item_type ) {
			$forum_id = bbp_get_topic_forum_id();
			$topic_id = $item_id;
			$user_id  = bbp_get_topic_author_id();
		} else {
			$forum_id = bbp_get_reply_forum_id();
			$topic_id = bbp_get_reply_topic_id();
			$user_id  = bbp_get_reply_author_id();
		}

		if ( ! in_array( $forum_id, $badgeable_forums ) ) {
			return false;
		}

		if ( ! $user_id ) {
			return false;
		}

		$slugs = $types = array();

		$user_login = get_user_by( 'id', $user_id )->user_login;

		// Check if the thread is associated with a plugin.
		if ( $forum_id === \WordPressdotorg\Forums\Plugin::PLUGINS_FORUM_ID ) {
			$types = array( 'plugin' );
		}
		// Else check if the thread is associated with a theme.
		elseif ( $forum_id === \WordPressdotorg\Forums\Plugin::THEMES_FORUM_ID ) {
			$types = array( 'theme' );
		}
		// Else check if the thread is a review.
		elseif ( $forum_id === \WordPressdotorg\Forums\Plugin::REVIEWS_FORUM_ID ) {
			// Need to check for plugin AND theme association to know which the review is for.
			$types = array( 'plugin', 'theme' );
		}
		// Else not a type of concern.
		else {
			return false;
		}

		foreach ( $types as $type ) {
			$slugs = wp_get_post_terms( $topic_id, 'topic-' . $type, array( 'fields' => 'slugs' ) );
			if ( $slugs ) {
				break;
			}
		}

		if ( ! $slugs ) {
			return false;
		}

		return array(
			'type'       => $type,
			'slug'       => $slugs[0],
			'user_login' => $user_login,
		);
	}

	/**
	 * Amends the provided classes for a given topic with badge-related classes.
	 *
	 * @param array $classes  Array of existing classes.
	 * @param int   $topic_id The ID of the topic.
	 * @return array
	 */
	public function bbp_get_topic_class( $classes, $topic_id ) {
		return $this->get_badge_class( $classes, 'topic', $topic_id );
	}

	/**
	 * Amends the provided classes for a given reply with badge-related classes.
	 *
	 * @param array $classes  Array of existing classes.
	 * @param int   $reply_id The ID of the reply.
	 * @return array
	 */
	public function bbp_get_reply_class( $classes, $reply_id ) {
		return $this->get_badge_class( $classes, 'reply', $reply_id );
	}

	/**
	 * Amends the provided classes with badge-related classes.
	 *
	 * Possible badge classes:
	 * - by-moderator (Note: will always be added if author is a moderator)
	 * - by-plugin-author
	 * - by-plugin-contributor
	 * - by-theme-author
	 * - by-theme-contributor
	 *
	 * @access protected
	 *
	 * @param array  $classes   Array of existing classes.
 	 * @param string $item_type The type of thing getting badge assigned. One of 'topic' or 'reply'.
	 * @param int    $item_id   The ID of the item getting badge assigned.
	 * @return array
	 */
	protected function get_badge_class( $classes, $item_type, $item_id ) {
		$has_badge = false;

		// Class related to moderators.
		if ( $this->is_user_moderator() ) {
			$classes[] = 'by-moderator';
			$has_badge = true;
		}

		// Class related to plugin and theme authors/contributors.
		if ( $info = $this->get_author_badge_info( $item_type, $item_id ) ) {
			if ( $this->is_user_author( $info['user_login'], $info['type'], $info['slug'] ) ) {
				$contrib_type = 'author';
			} elseif ( $this->is_user_contributor( $info['user_login'], $info['type'], $info['slug'] ) ) {
				$contrib_type = 'contributor';
			} else {
				$contrib_type = '';
			}

			if ( $contrib_type ) {
				$classes[] = 'by-' . $info['type'] . '-' . $contrib_type;
				$has_badge = true;
			}
		}

		if ( $has_badge ) {
			$classes[] = 'author-has-badge';
		}

		return $classes;
	}

	/**
	 * Display badge for topic author if they merit a badge.
	 */
	public function show_topic_author_badge() {
		$this->show_user_badge( 'topic', bbp_get_topic_id() );
	}

	/**
	 * Display badge for reply author if they merit a badge.
	 */
	public function show_reply_author_badge() {
		$this->show_user_badge( 'reply', bbp_get_reply_id() );
	}

	/**
	 * Display badge if the author merits a badge.
	 *
	 * @access protected
	 *
 	 * @param string $item_type The type of thing getting badge assigned. One of 'topic' or 'reply'.
	 * @param int    $item_id   The ID of the item getting badge assigned.
	 */
	protected function show_user_badge( $item_type, $item_id ) {
		$output = $this->get_author_badge( $item_type, $item_id );

		// Don't assign moderator badge if already assigning author badge.
		if ( ! $output ) {
			$output = $this->get_moderator_badge();
		}

		if ( $output ) {
			echo $this->format_badge( $output['type'], $output['label'], $output['help'] );
		}
	}

	/**
	 * Returns the HTML formatted badge.
	 *
	 * @param $type  string The type of badge.
	 * @param $label string The label for the badge.
	 * @param $help  string Optional. Help/descriptive text for the badge.
	 * @return string
	 */
	protected function format_badge( $type, $label, $help = '' ) {
		$output = '';

		if ( $label ) {
			$output .= sprintf(
				'<span class="author-badge author-badge-%s" title="%s">%s</span>',
				esc_attr( $type ),
				esc_attr( $help ),
				$label
			);
		}

		// Return the markup.
		return $output;
	}

	/**
	 * Get badge if the author merits a badge for being a plugin/theme author or
	 * contributor.
	 *
	 * @access protected
	 *
 	 * @param string $item_type The type of thing getting badge assigned. One of
	 *                          'topic' or 'reply'.
	 * @param int    $item_id   The ID of the item getting badge assigned.
	 * @return array|false      Associative array with keys 'type', 'slug', and
	 *                          'user_login' if author merits a badge, else null.
	 */
	protected function get_author_badge( $item_type, $item_id ) {
		if ( ! $info = $this->get_author_badge_info( $item_type, $item_id ) ) {
			return false;
		}

		$label = $help = null;

		// Determine strings to use based on user being an author or contributor.
		if ( $this->is_user_author( $info['user_login'], $info['type'], $info['slug'] ) ) {
			if ( 'plugin' == $info['type'] ) {
				$label = __( 'Plugin Author', 'wporg-forums' );
				$help  = __( 'This person is the author of this plugin', 'wporg-forums' );
			} else {
				$label = __( 'Theme Author', 'wporg-forums' );
				$help  = __( 'This person is the author of this theme', 'wporg-forums' );
			}
		}
		elseif ( $this->is_user_contributor( $info['user_login'], $info['type'], $info['slug'] ) ) {
			if ( 'plugin' == $info['type'] ) {
				$label = __( 'Plugin Contributor', 'wporg-forums' );
				$help  = __( 'This person is a contributor to this plugin', 'wporg-forums' );
			} else {
				$label = __( 'Theme Contributor', 'wporg-forums' );
				$help  = __( 'This person is a contributor to this theme', 'wporg-forums' );
			}
		}

		return $label ? array( 'type' => $info['type'], 'label' => $label, 'help' => $help ) : false;
	}

	/**
	 * Get badge if the author merits a badge for being a moderator.
	 *
	 * @access protected
	 *
	 * @return array|false Associative array with keys 'type', 'slug', and
	 *                     'user_login' if author merits a badge, else false.
	 */
	protected function get_moderator_badge() {
		$label = $help = null;

		if ( $this->is_user_moderator() ) {
			$label = __( 'Moderator', 'wporg-forums' );
			$help  = __( 'This person is a moderator on this forum', 'wporg-forums' );
		}

		return $label ? array( 'type' => 'moderator', 'label' => $label, 'help' => $help ) : false;
	}

	/**
	 * Checks if the specified user is an author to the specified plugin/theme.
	 *
	 * An author is defined as someone who has commit access to a plugin, or is
	 * the designated author for a theme.
	 *
	 * @param string $user_login User login.
	 * @param string $type       Either 'plugin' or 'theme'.
	 * @param string $slug       Slug for the plugin or theme.
	 * @return bool              True if user is an author, false otherwise.
	 */
	public function is_user_author( $user_login, $type, $slug ) {
		global $wpdb;

		$authors = wp_cache_get( $slug, $type . '_authors' );

		if ( false === $authors ) {
			if ( 'plugin' === $type ) {
				// Get users who have commit access.
				$authors = $wpdb->get_col( $wpdb->prepare(
					"SELECT user FROM " . PLUGINS_TABLE_PREFIX . "svn_access WHERE `path` = %s",
					'/' . $slug
				) );
			}
			else {
				// TODO: Change this if themes support having more than one author.
				$author_id = $wpdb->get_var( $wpdb->prepare(
					"SELECT post_author FROM " . self::$themes_table_prefix . "posts WHERE post_name = %s LIMIT 1",
					$slug
				) );
				if ( $author_id ) {
					$author = get_user_by( 'id', $author_id );
					$authors = array( $author->user_login );
				}
			}

			wp_cache_add( $slug, $authors, $type . '_authors', HOUR_IN_SECONDS );
		}

		return $authors && in_array( $user_login, $authors );
	}

	/**
	 * Checks if the specified user is a contributor to the specified plugin/theme.
	 *
	 * A plugin contributor is someone listed as a contributor in the plugin's readme.txt.
	 * Currently, themes do not support having contirbutors.
	 *
	 * @param string $user_login User login.
	 * @param string $type       Either 'plugin' or 'theme'.
	 * @param string $slug       Slug for the plugin or theme.
	 * @return bool              True if user is a contributor, false otherwise.
	 */
	public function is_user_contributor( $user_login, $type, $slug ) {
		global $wpdb;

		$contributors = wp_cache_get( $slug, $type . '_contributors' );

		if ( false === $contributors ) {
			if ( 'plugin' === $type ) {
				// TODO: Change this when the Plugin Directory switches over to WordPress.
				$contributors = $wpdb->get_var( $wpdb->prepare(
					'SELECT meta_value FROM ' . PLUGINS_TABLE_PREFIX . 'meta m LEFT JOIN ' . PLUGINS_TABLE_PREFIX . 'topics t ON m.object_id = t.topic_id WHERE t.topic_slug = %s AND m.object_type = %s AND m.meta_key = %s',
					$slug,
					'bb_topic',
					'contributors'
				) );

				if ( $contributors ) {
					$contributors = unserialize( $contributors );
				}
			}
			else {
				// Themes have no additional contributors at the moment.
				// TODO: Change this if themes support specifying contributors.
				$contributors = array();
			}

			wp_cache_add( $slug, $contributors, $type . '_contributors', HOUR_IN_SECONDS );
		}

		return $contributors && in_array( $user_login, $contributors );
	}

	/**
	 * Checks if the specified user is a forum moderator or keymaster.
	 *
	 * By default, this considers a keymaster as being a moderator for the purpose
	 * of badging them. Use the $strict argument to check that the user is a
	 * moderator without considering if they are a keymaster.
	 *
	 * @param string $user_id Optional. User ID. Assumes current reply author ID
	 *                        if not provided.
	 * @param bool   $strict  Optional. True if user should strictly be checked
	 *                        for being a moderator, false will also check if they
	 *                        are a keymaster. Default false.
	 * @return bool           True if user is a moderator, false otherwise.
	 */
	public function is_user_moderator( $user_id = '', $strict = false ) {
		if ( ! $user_id ) {
			$user_id = bbp_get_reply_author_id();
		}

		return ( user_can( $user_id, 'moderate' ) || ( ! $strict && bbp_is_user_keymaster( $user_id ) ) );
	}
}
