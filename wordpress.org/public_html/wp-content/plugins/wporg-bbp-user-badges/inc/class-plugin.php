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
		global $wpdb;

		self::$plugins_table_prefix = $wpdb->base_prefix . WPORG_PLUGIN_DIRECTORY_BLOGID . '_';
		self::$themes_table_prefix  = $wpdb->base_prefix . WPORG_THEME_DIRECTORY_BLOGID . '_';

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
	 *                          'user_nicename' if author merits a badge, else false.
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

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		$slugs = $types = array();

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
			$terms = get_the_terms( $topic_id, 'topic-' . $type );
			if ( $terms ) {
				break;
			}
		}

		if ( ! $terms || is_wp_error( $terms ) ) {
			return false;
		}

		return array(
			'type'          => $type,
			'slug'          => $terms[0]->slug,
			'user_nicename' => $user->user_nicename,
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
			if ( $this->is_user_author( $info['user_nicename'], $info['type'], $info['slug'] ) ) {
				$contrib_type = 'author';
			} elseif ( $this->is_user_contributor( $info['user_nicename'], $info['type'], $info['slug'] ) ) {
				$contrib_type = 'contributor';
			} elseif ( $this->is_user_support_rep( $info['user_nicename'], $info['type'], $info['slug'] ) ) {
				$contrib_type = 'support-rep';
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
		// Don't assign thread starter badge if already assigning a badge.
		if ( ! $output ) {
			$output = $this->get_thread_starter_badge( $item_type, $item_id );
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
	 * @return array|false      Associative array with keys 'type', 'label', and
	 *                          'help' if author merits a badge, else false.
	 */
	protected function get_author_badge( $item_type, $item_id ) {
		if ( ! $info = $this->get_author_badge_info( $item_type, $item_id ) ) {
			return false;
		}

		$label = $help = null;

		// Determine strings to use based on user being an author or contributor.
		if ( $this->is_user_author( $info['user_nicename'], $info['type'], $info['slug'] ) ) {
			if ( 'plugin' == $info['type'] ) {
				$label = __( 'Plugin Author', 'wporg-forums' );
				$help  = __( 'This person is the author of this plugin', 'wporg-forums' );
			} else {
				$label = __( 'Theme Author', 'wporg-forums' );
				$help  = __( 'This person is the author of this theme', 'wporg-forums' );
			}
		}
		elseif ( $this->is_user_contributor( $info['user_nicename'], $info['type'], $info['slug'] ) ) {
			if ( 'plugin' == $info['type'] ) {
				$label = __( 'Plugin Contributor', 'wporg-forums' );
				$help  = __( 'This person is a contributor to this plugin', 'wporg-forums' );
			} else {
				$label = __( 'Theme Contributor', 'wporg-forums' );
				$help  = __( 'This person is a contributor to this theme', 'wporg-forums' );
			}
		}
		elseif ( $this->is_user_support_rep( $info['user_nicename'], $info['type'], $info['slug'] ) ) {
			if ( 'plugin' == $info['type'] ) {
				$label = __( 'Plugin Support', 'wporg-forums' );
				$help  = __( 'This person is a support representative for this plugin', 'wporg-forums' );
			} else {
				$label = __( 'Theme Support', 'wporg-forums' );
				$help  = __( 'This person is a support representative for this theme', 'wporg-forums' );
			}
		}

		return $label ? array( 'type' => $info['type'], 'label' => $label, 'help' => $help ) : false;
	}

	/**
	 * Get badge if the author merits a badge for being a moderator.
	 *
	 * @access protected
	 *
	 * @return array|false Associative array with keys 'type', 'label', and
	 *                     'help' if author merits a badge, else false.
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
	 * Get badge if the author started the thread.
	 *
	 * @access protected
	 *
	 * @return array|false Associative array with keys 'type', 'label', and
	 *                     'help' if author merits a badge, else false.
	 */
	protected function get_thread_starter_badge( $item_type, $item_id ) {
		$label = $help = null;

		if (
			'reply' === $item_type &&
			bbp_get_reply_author_id( $item_id ) === bbp_get_topic_author_id( bbp_get_reply_topic_id( $item_id ) )
		) {
			$label = __( 'Thread Starter', 'wporg-forums' );
			$help  = __( 'This person created the thread', 'wporg-forums' );
		}

		return $label ? array( 'type' => 'thread-starter', 'label' => $label, 'help' => $help ) : false;
	}

	/**
	 * Checks if the specified user is an author to the specified plugin/theme.
	 *
	 * An author is defined as someone who has commit access to a plugin, or is
	 * the designated author for a theme.
	 *
	 * @param string $user_nicename User slug.
	 * @param string $type          Either 'plugin' or 'theme'.
	 * @param string $slug          Slug for the plugin or theme.
	 * @return bool                 True if user is an author, false otherwise.
	 */
	public function is_user_author( $user_nicename, $type, $slug ) {
		if ( class_exists( '\WordPressdotorg\Forums\Plugin' ) ) {
			if ( 'plugin' === $type ) {
				$compat = \WordPressdotorg\Forums\Plugin::get_instance()->plugins;
			} else {
				$compat = \WordPressdotorg\Forums\Plugin::get_instance()->themes;
			}
		} else {
			$compat = null;
		}

		$authors = $compat ? $compat->get_authors( $slug ) : array();

		return $authors && in_array( $user_nicename, $authors );
	}

	/**
	 * Checks if the specified user is a contributor to the specified plugin/theme.
	 *
	 * A plugin contributor is someone listed as a contributor in the plugin's readme.txt.
	 * Currently, themes do not support having contirbutors.
	 *
	 * @param string $user_nicename User slug.
	 * @param string $type          Either 'plugin' or 'theme'.
	 * @param string $slug          Slug for the plugin or theme.
	 * @return bool                 True if user is a contributor, false otherwise.
	 */
	public function is_user_contributor( $user_nicename, $type, $slug ) {
		if ( class_exists( '\WordPressdotorg\Forums\Plugin' ) ) {
			if ( 'plugin' === $type ) {
				$compat = \WordPressdotorg\Forums\Plugin::get_instance()->plugins;
			} else {
				$compat = \WordPressdotorg\Forums\Plugin::get_instance()->themes;
			}
		} else {
			$compat = null;
		}

		$contributors = $compat ? $compat->get_contributors( $slug ) : array();

		return $contributors && in_array( $user_nicename, $contributors );
	}

	/**
	 * Checks if the specified user is a support rep for the specified plugin/theme.
	 *
	 * A support representative is someone assigned as such by the plugin or theme author.
	 *
	 * @param string $user_nicename User slug.
	 * @param string $type          Either 'plugin' or 'theme'.
	 * @param string $slug          Slug for the plugin or theme.
	 * @return bool                 True if user is a support rep, false otherwise.
	 */
	public function is_user_support_rep( $user_nicename, $type, $slug ) {
		if ( class_exists( '\WordPressdotorg\Forums\Plugin' ) ) {
			if ( 'plugin' === $type ) {
				$compat = \WordPressdotorg\Forums\Plugin::get_instance()->plugins;
			} else {
				$compat = \WordPressdotorg\Forums\Plugin::get_instance()->themes;
			}
		} else {
			$compat = null;
		}

		$support_reps = $compat ? $compat->get_support_reps( $slug ) : array();

		return $support_reps && in_array( $user_nicename, $support_reps );
	}

	/**
	 * Checks if the specified user is a forum moderator or keymaster.
	 *
	 * By default, this considers a keymaster as being a moderator for the purpose
	 * of badging them. Use the $strict argument to check that the user is a
	 * moderator without considering if they are a keymaster.
	 *
	 * @param string $user_id Optional. User ID. Assumes current post author ID
	 *                        if not provided.
	 * @param bool   $strict  Optional. True if user should strictly be checked
	 *                        for being a moderator, false will also check if they
	 *                        are a keymaster. Default false.
	 * @return bool           True if user is a moderator, false otherwise.
	 */
	public function is_user_moderator( $user_id = '', $strict = false ) {
		if ( ! $user_id ) {
			$user_id = get_post_field( 'post_author' );
		}

		return ( user_can( $user_id, 'moderate', get_the_ID() ) || ( ! $strict && bbp_is_user_keymaster( $user_id ) ) );
	}
}
