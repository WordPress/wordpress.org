<?php

namespace WordPressdotorg\Forums;

class Stickies_Compat {

	const META = '_bbp_sticky_topics';

	var $compat   = null;
	var $slug     = null;
	var $taxonomy = null;
	var $object   = null;
	var $term     = null;

	public function __construct( $args ) {
		$args = wp_parse_args( $args, array(
			'compat'       => '',
			'slug'         => '',
			'taxonomy'     => '',
			'object'       => '',
			'term'         => '',
			'authors'      => array(),
			'contributors' => array(),
			'support_reps' => array(),
		) );

		if ( ! $args['compat'] || ! $args['slug'] || ! $args['taxonomy'] || ! $args['object'] || ! $args['term'] ) {
			return;
		}

		$this->compat       = $args['compat'];
		$this->slug         = $args['slug'];
		$this->taxonomy     = $args['taxonomy'];
		$this->object       = $args['object'];
		$this->term         = $args['term'];
		$this->authors      = $args['authors'];
		$this->contributors = $args['contributors'];
		$this->support_reps = $args['support_reps'];

		// Remove global stickies from sticky array.
		add_filter( 'bbp_get_super_stickies', array( $this, 'get_super_stickies' ) );

		// Add compat stickies to sticky array.
		add_filter( 'bbp_get_stickies', array( $this, 'get_compat_stickies' ), 10, 2 );

		// Make bbp_is_topic_sticky() recognize compat stickies.
		add_filter( 'bbp_is_topic_sticky', array( $this, 'is_topic_sticky' ), 10, 2 );

		// Add topic toggle for compat authors.
		add_action( 'bbp_get_request', array( $this, 'sticky_handler' ) );

		// Add link to topic admin.
		add_filter( 'bbp_topic_admin_links', array( $this, 'admin_links' ), 10, 2 );
	}

	/**
	 * Return empty array for super sticky topics in compat mode.
	 *
	 * @param array $stickies The super sticky topic IDs.
	 * @return array An empty array.
	 */
	public function get_super_stickies( $stickies ) {
		// If we're archiving a topic, make sure bbp_unstick_topic() gets the correct list.
		if ( isset( $_GET['action'] ) && 'wporg_bbp_archive_post' === $_GET['action'] ) {
			return $stickies;
		}

		return array();
	}

	/**
	 * Return compat stickies for a given term.
	 *
	 * @param array $stickies The sticky topic IDs.
	 * @param int   $forum_id The forum ID.
	 * @return array The sticky topic IDs.
	 */
	public function get_compat_stickies( $stickies, $forum_id ) {
		if ( $this->term && bbp_is_single_view() && $this->compat == bbp_get_view_id() ) {
			$stickies = self::get_stickies( $this->term->term_id );
		}
		return $stickies;
	}

	/**
	 * Check whether a compat topic is sticky.
	 *
	 * @param bool $is_sticky Whether the topic is sticky.
	 * @param int  $topic     The topic ID.
	 * @return bool Whether the topic is sticky.
	 */
	public function is_topic_sticky( $is_sticky, $topic_id ) {
		if ( $this->term && bbp_is_single_topic() ) {
			$is_sticky = self::is_sticky( $this->term->term_id, $topic_id );
		}
		return $is_sticky;
	}

	/**
	 */
	public function sticky_handler( $action = '' ) {
		// Bail if the action isn't meant for this function.
		if ( ! in_array( $action, array(
			'wporg_bbp_stick_compat_topic',
			'wporg_bbp_unstick_compat_topic',
		) ) ) {
			return;
		}

		// Bail if no topic id or term id are passed.
		if ( empty( $_GET['topic_id'] ) || empty( $_GET['term_id'] ) ) {
			return;
		}

		// Get required data.
		$topic   = get_post( absint( $_GET['topic_id'] ) );
		$term    = get_term( absint( $_GET['term_id'] ) );
		$user_id = get_current_user_id();

		// Check for empty topic or term id.
		if ( ! $topic || ! $term ) {
			bbp_add_error( 'wporg_bbp_sticky_topic_id', __( '<strong>Error:</strong> No topic was found! Which topic are you sticking?', 'wporg-forums' ) );

		// Check user.
		} elseif ( ! $this->user_can_stick( $user_id, $term->term_id, $topic->ID ) ) {
			bbp_add_error( 'wporg_bbp_sticky_logged_in', __( '<strong>Error:</strong> You do not have permission to do this!', 'wporg-forums' ) );

		// Check nonce.
		} elseif( ! bbp_verify_nonce_request( 'toggle-topic-sticky_' . $topic->ID . '_' . $term->term_id ) ) {
			bbp_add_error( 'wporg_bbp_sticky_nonce', __( '<strong>Error:</strong> Are you sure you wanted to do that?', 'wporg-forums' ) );
		}

		if ( bbp_has_errors() ) {
			return;
		}

		$is_sticky = self::is_sticky( $term->term_id, $topic->ID );
		$success = false;

		// Stick/unstick the topic.
		if ( 'wporg_bbp_stick_compat_topic' == $action ) {
			$success = self::add_sticky( $term->term_id, $topic->ID );
		} elseif ( 'wporg_bbp_unstick_compat_topic' == $action ) {
			$success = self::remove_sticky( $term->term_id, $topic->ID );
		}

		$permalink = get_permalink( $topic->ID );

		if ( $success && ! is_wp_error( $success ) ) {
			bbp_redirect( $permalink );
		} elseif ( true === $is_sticky && 'wporg_bbp_stick_compat_topic' == $action ) {
			bbp_add_error( 'wporg_bbp_stick_compat_topic', __( '<strong>Error:</strong> There was a problem sticking that topic!', 'wporg-forums' ) );
		} elseif ( false === $is_sticky && 'wporg_bbp_unstick_compat_topic' == $action ) {
			bbp_add_error( 'wporg_bbp_unstick_compat_topic', __( '<strong>Error:</strong> There was a problem unsticking that topic!', 'wporg-forums' ) );
		}
	}

	/**
	 * Replace the bbPress stick link with the custom stick link in compat topics.
	 *
	 * @param array $r The array of admin links
	 * @param int $topic_id The topic id
	 * @return array The filtered array of admin links
	 */
	public function admin_links( $r, $topic_id ) {
		if ( ! bbp_is_single_topic() ) {
			return $r;
		}

		$user_id = get_current_user_id();

		if (
			Plugin::REVIEWS_FORUM_ID != bbp_get_topic_forum_id()
		&&
			in_array( get_post_status( $topic_id ), array( 'publish', 'closed' ) )
		&&
			$this->user_can_stick( $user_id, $this->term->term_id, $topic_id )
		) {
			$r['stick'] = self::get_stick_link( array( 'topic_id' => $topic_id, 'term_id' => $this->term->term_id ) );
		} else {
			unset( $r['stick'] );
		}
		return $r;
	}

	/**
	 * Get the link to stick/unstick a compat topic.
	 *
	 * @param array $args The link arguments
	 * @return string The linked URL
	 */
	public static function get_stick_link( $args = array() ) {
		$user_id = get_current_user_id();

		$r = bbp_parse_args( $args, array(
			'topic_id' => get_the_ID(),
			'term_id'  => 0,
			'stick'    => esc_html__( 'Stick', 'wporg-forums' ),
			'unstick'  => esc_html__( 'Unstick', 'wporg-forums' ),
		), 'get_topic_stick_link' );
		if ( empty( $r['topic_id'] ) || empty( $r['term_id'] ) ) {
			return false;
		}

		$topic = get_post( $r['topic_id'] );
		$term  = get_term( $r['term_id'] );
		if ( ! $topic || ! $term ) {
			return false;
		}

		if ( self::is_sticky( $term->term_id, $topic->ID ) ) {
			$text = $r['unstick'];
			$query_args = array( 'action' => 'wporg_bbp_unstick_compat_topic', 'topic_id' => $topic->ID, 'term_id' => $term->term_id );
		} else {
			$text = $r['stick'];
			$query_args = array( 'action' => 'wporg_bbp_stick_compat_topic', 'topic_id' => $topic->ID, 'term_id' => $term->term_id );
		}

		$permalink = get_permalink( $topic->ID );
		$url = esc_url( wp_nonce_url( add_query_arg( $query_args, $permalink ), 'toggle-topic-sticky_' . $topic->ID . '_' . $term->term_id ) );
		return sprintf( "<a href='%s'>%s</a>", $url, esc_html( $text ) );
	}

	/**
	 * Is a given user allowed to stick/unstick a topic?
	 *
	 * @param int $user_id The user id
	 * @param int $term_id The term id
	 * @param int $topic_id The topic id
	 * @return bool True if allowed, false if not
	 */
	public function user_can_stick( $user_id = 0, $term_id = 0, $topic_id = 0 ) {
		$retval = $topic = $term = false;

		if ( empty( $user_id ) ) {
			$user_id = get_current_user_id();
		}
		$user = get_userdata( $user_id );

		if ( $topic_id ) {
			$topic = get_post( $topic_id );
		}
		if ( $term_id ) {
			$term = get_term( $term_id );
		}

		if ( $user && $topic && $term ) {
			// Moderators.
			if ( user_can( $user->ID, 'moderate', $topic->ID ) ) {
				$retval = true;
			}

			// Compat authors.
			if ( $this->authors && in_array( $user->user_nicename, $this->authors ) ) {
				$retval = true;
			}

			// Compat contributors.
			if ( $this->contributors && in_array( $user->user_nicename, $this->contributors ) ) {
				$retval = true;
			}

			// Compat support reps.
			if ( $this->support_reps && in_array( $user->user_nicename, $this->support_reps ) ) {
				$retval = true;
			}
		}
		return $retval;
	}

	/**
	 * Is a topic sticky for this term?
	 *
	 * @param int $term_id The term id
	 * @param int $topic_id The topic id
	 * @return bool True if sticky, false if not
	 */
	public static function is_sticky( $term_id, $topic_id ) {
		$stickies = self::get_stickies( $term_id );
		return in_array( $topic_id, $stickies );
	}

	/**
	 * Add a topic id to a term's list of sticky topic ids.
	 *
	 * @param int $term_id The term id
	 * @param int $topic_id The topic id
	 */
	public static function add_sticky( $term_id, $topic_id ) {
		$stickies = self::get_stickies( $term_id );
		if ( ! in_array( $topic_id, $stickies ) ) {
			$stickies[] = $topic_id;
		}
		return self::set_stickies( $term_id, $stickies );
	}

	/**
	 * Remove a topic id from a term's list of sticky topic ids.
	 *
	 * @param int $topic_id The topic id
	 * @param int $term_id The term id
	 */
	public static function remove_sticky( $term_id, $topic_id ) {
		$stickies = self::get_stickies( $term_id );
		if ( ( $key = array_search( $topic_id, $stickies ) ) !== false ) {
			unset( $stickies[ $key ] );
		}
		return self::set_stickies( $term_id, $stickies );
	}

	/**
	 * Return an array of topic sticky ids for a given term.
	 *
	 * @param int $term_id The term id
	 * @return array The sticky topic ids
	 */
	public static function get_stickies( $term_id ) {
		$retval = array();

		$stickies = get_term_meta( $term_id, self::META, true );
		if ( $stickies ) {
			$retval = array_map( 'intval', explode( ',', $stickies ) );
		}
		return $retval;
	}

	/**
	 * Set the topic sticky ids for a given term.
	 *
	 * @param int $term_id The term id
	 * @param array $stickies The sticky topic ids
	 */
	public static function set_stickies( $term_id, $stickies = array() ) {
		$retval = update_term_meta( $term_id, self::META, implode( ',', $stickies ) );
		wp_cache_delete( $term_id, 'term_meta' );
		return $retval;
	}
}
