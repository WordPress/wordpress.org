<?php

namespace WordPressdotorg\Forums;

class Dropin {

	function __construct() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 11 );
	}

	public function plugins_loaded() {
		remove_action( 'bbp_trashed_topic',    'bbp_update_topic_walker' );
		remove_action( 'bbp_untrashed_topic',  'bbp_update_topic_walker' );
		remove_action( 'bbp_deleted_topic',    'bbp_update_topic_walker' );
		remove_action( 'bbp_spammed_topic',    'bbp_update_topic_walker' );
		remove_action( 'bbp_unspammed_topic',  'bbp_update_topic_walker' );
		remove_action( 'bbp_approved_topic',   'bbp_update_topic_walker' );
		remove_action( 'bbp_unapproved_topic', 'bbp_update_topic_walker' );

		remove_action( 'bbp_trashed_reply',    'bbp_update_reply_walker' );
		remove_action( 'bbp_untrashed_reply',  'bbp_update_reply_walker' );
		remove_action( 'bbp_deleted_reply',    'bbp_update_reply_walker' );
		remove_action( 'bbp_spammed_reply',    'bbp_update_reply_walker' );
		remove_action( 'bbp_unspammed_reply',  'bbp_update_reply_walker' );
		remove_action( 'bbp_approved_reply',   'bbp_update_reply_walker' );
		remove_action( 'bbp_unapproved_reply', 'bbp_update_reply_walker' );

		// Not needed, causing slowdown when approving topics (due to expensive count queries)
		remove_action( 'bbp_approved_topic',   'bbp_approved_unapproved_topic_update_forum_reply_count' );
		remove_action( 'bbp_unapproved_topic', 'bbp_approved_unapproved_topic_update_forum_reply_count' );

		add_action( 'bbp_trashed_reply',       array( $this, 'update_reply_topic_meta' ) );
		add_action( 'bbp_untrashed_reply',     array( $this, 'update_reply_topic_meta' ) );
		add_action( 'bbp_deleted_reply',       array( $this, 'update_reply_topic_meta' ) );
		add_action( 'bbp_spammed_reply',       array( $this, 'update_reply_topic_meta' ) );
		add_action( 'bbp_unspammed_reply',     array( $this, 'update_reply_topic_meta' ) );
		add_action( 'bbp_approved_reply',      array( $this, 'update_reply_topic_meta' ) );
		add_action( 'bbp_unapproved_reply',    array( $this, 'update_reply_topic_meta' ) );

		add_action( 'bbp_edit_topic',          array( $this, 'update_old_topic_meta' ) );
		add_action( 'bbp_edit_reply',          array( $this, 'update_old_reply_meta' ) );

		// Avoid bbp_update_topic_walker().
		remove_action( 'bbp_new_topic',  'bbp_update_topic' );
		remove_action( 'bbp_edit_topic', 'bbp_update_topic' );
		add_action( 'bbp_new_topic',     array( $this, 'bbp_update_topic' ), 10, 5 );
		add_action( 'bbp_edit_topic',    array( $this, 'bbp_update_topic' ), 10, 5 );

		// Avoid bbp_update_reply_walker().
		remove_action( 'bbp_new_reply',  'bbp_update_reply' );
		remove_action( 'bbp_edit_reply', 'bbp_update_reply' );
		add_action( 'bbp_new_reply',     array( $this, 'bbp_update_reply' ), 10, 7 );
		add_action( 'bbp_edit_reply',    array( $this, 'bbp_update_reply' ), 10, 7 );

		if ( is_admin() ) {
			remove_filter( 'the_title', 'bbp_get_reply_title_fallback', 2 );
		}
	}

	/**
	 * Handle only the necessary meta stuff from trashing/untrashing, deleting,
	 * spamming/unspamming, and approving/unapproving a reply.
	 *
	 * @param int $reply_id Reply ID.
	 */
	function update_reply_topic_meta( $reply_id ) {
		$topic_id = bbp_get_reply_topic_id( $reply_id );

		// Make every effort to get topic ID.
		// https://bbpress.trac.wordpress.org/ticket/2529
		if ( empty( $topic_id ) && ( current_filter() === 'bbp_deleted_reply' ) ) {
			$topic_id = get_post_field( 'post_parent', $reply_id );
		}

		bbp_update_topic_last_reply_id( $topic_id );
		bbp_update_topic_last_active_id( $topic_id );
		bbp_update_topic_last_active_time( $topic_id );
		bbp_update_topic_voice_count( $topic_id );
	}

	/**
	 * Adjust the total hidden reply count of a topic (hidden includes trashed,
	 * spammed, pending, and archived replies).
	 *
	 * Extends the native bbPress bbp_update_topic_reply_count_hidden() function
	 * to include 'archived' status.
	 *
	 * @see https://bbpress.trac.wordpress.org/ticket/3128
	 *
	 * @param int $topic_id    Optional. Topic ID to update.
	 * @param int $reply_count Optional. Set the reply count manually.
	 * @return int Topic hidden reply count.
	 */
	function bbp_update_topic_reply_count_hidden( $topic_id = 0, $reply_count = 0 ) {

		// If it's a reply, then get the parent (topic id)
		$topic_id = bbp_is_reply( $topic_id )
			? bbp_get_reply_topic_id( $topic_id )
			: bbp_get_topic_id( $topic_id );

		// Get replies of topic
		if ( empty( $reply_count ) ) {
			$statuses    = array( bbp_get_trash_status_id(), bbp_get_spam_status_id(), bbp_get_pending_status_id(), Moderators::ARCHIVED );
			$post_status = "'" . implode( "','", $statuses ) . "'";
			$bbp_db      = bbp_db();
			$query       = $bbp_db->prepare( "SELECT COUNT(ID) FROM {$bbp_db->posts} WHERE post_parent = %d AND post_status IN ( {$post_status} ) AND post_type = %s", $topic_id, bbp_get_reply_post_type() );
			$reply_count = $bbp_db->get_var( $query );
		}

		$reply_count = (int) $reply_count;

		update_post_meta( $topic_id, '_bbp_reply_count_hidden', $reply_count );

		// Filter & return
		return (int) apply_filters( 'bbp_update_topic_reply_count_hidden', $reply_count, $topic_id );
	}

	/**
	 * Update the necessary meta data when editing a topic created before
	 * 2017-07-21, as those topics can have potentially inaccurate data.
	 *
	 * @see https://meta.trac.wordpress.org/ticket/1971
	 * @see https://meta.trac.wordpress.org/ticket/2043
	 *
	 * @param int $topic_id Topic ID.
	 */
	function update_old_topic_meta( $topic_id ) {
		// Only run on topics older than 2017-07-21.
		if ( get_post_field( 'post_date', $topic_id ) >= '2017-07-21' ) {
			return;
		}

		bbp_update_topic_last_reply_id( $topic_id );
		bbp_update_topic_last_active_id( $topic_id );
		bbp_update_topic_last_active_time( $topic_id );
		bbp_update_topic_voice_count( $topic_id );

		bbp_update_topic_reply_count( $topic_id );
		$this->bbp_update_topic_reply_count_hidden( $topic_id );
	}

	/**
	 * Update the necessary meta data when editing a reply created before
	 * 2017-07-17, as those replies can have potentially inaccurate data.
	 *
	 * @see https://meta.trac.wordpress.org/ticket/2110
	 * @see https://meta.trac.wordpress.org/ticket/2481
	 *
	 * @param int $reply_id Reply ID.
	 */
	function update_old_reply_meta( $reply_id ) {
		// Only run on replies older than 2017-07-17.
		if ( get_post_field( 'post_date', $reply_id ) >= '2017-07-17' ) {
			return;
		}

		// Don't recaculate position for archived replies.
		if ( 'archived' === get_post_status( $reply_id ) ) {
			return;
		}

		bbp_update_reply_position( $reply_id );
	}

	/**
	 * Handle only the necessary meta stuff from posting a new topic or editing a topic
	 *
	 * @param int $topic_id Optional. Topic id
	 * @param int $forum_id Optional. Forum id
	 * @param bool|array $anonymous_data Optional logged-out user data.
	 * @param int $author_id Author id
	 * @param bool $is_edit Optional. Is the post being edited? Defaults to false.
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses bbp_get_current_user_id() To get the current user id
	 * @yses bbp_get_topic_forum_id() To get the topic forum id
	 * @uses update_post_meta() To update the topic metas
	 * @uses set_transient() To update the flood check transient for the ip
	 * @uses bbp_update_user_last_posted() To update the users last posted time
	 * @uses bbp_is_subscriptions_active() To check if the subscriptions feature is
	 *                                      activated or not
	 * @uses bbp_is_user_subscribed() To check if the user is subscribed
	 * @uses bbp_remove_user_subscription() To remove the user's subscription
	 * @uses bbp_add_user_subscription() To add the user's subscription
	 * @uses bbp_update_topic_last_reply_id() To update the last reply id topic meta
	 * @uses bbp_update_topic_last_active_id() To update the topic last active id
	 * @uses bbp_update_topic_last_active_time() To update the last active topic meta
	 * @uses bbp_update_topic_reply_count() To update the topic reply count
	 * @uses bbp_update_topic_reply_count_hidden() To udpate the topic hidden reply count
	 * @uses bbp_update_topic_voice_count() To update the topic voice count
	 * @uses bbp_update_topic_walker() To udpate the topic's ancestors
	 */
	function bbp_update_topic( $topic_id = 0, $forum_id = 0, $anonymous_data = false, $author_id = 0, $is_edit = false ) {

		// Validate the ID's passed from 'bbp_new_topic' action
		$topic_id = bbp_get_topic_id( $topic_id );
		$forum_id = bbp_get_forum_id( $forum_id );

		// Bail if there is no topic
		if ( empty( $topic_id ) ) {
			return;
		}

		// Check author_id
		if ( empty( $author_id ) ) {
				$author_id = bbp_get_current_user_id();
		}

		// Check forum_id
		if ( empty( $forum_id ) ) {
			$forum_id = bbp_get_topic_forum_id( $topic_id );
		}

		// Get the topic types
		$topic_types = bbp_get_topic_types( $topic_id );

		// Sticky check after 'bbp_new_topic' action so forum ID meta is set
		if ( ! empty( $_POST['bbp_stick_topic'] ) && in_array( $_POST['bbp_stick_topic'], array_keys( $topic_types ) ) ) {

			// What's the caps?
			if ( current_user_can( 'moderate', $topic_id ) ) {

				// What's the haps?
				switch ( $_POST['bbp_stick_topic'] ) {

					// Sticky in this forum
					case 'stick'   :
						bbp_stick_topic( $topic_id );
						break;

					// Super sticky in all forums
					case 'super'   :
						bbp_stick_topic( $topic_id, true );
						break;

					// We can avoid this as it is a new topic
					case 'unstick' :
					default        :
						break;
				}
			}
		}

		// If anonymous post, store name, email, website and ip in post_meta.
		// It expects anonymous_data to be sanitized.
		// Check bbp_filter_anonymous_post_data() for sanitization.
		if ( ! empty( $anonymous_data ) && is_array( $anonymous_data ) ) {

			// Parse arguments against default values
			$r = bbp_parse_args( $anonymous_data, array(
				'bbp_anonymous_name'    => '',
				'bbp_anonymous_email'   => '',
				'bbp_anonymous_website' => '',
			), 'update_topic' );

			// Update all anonymous metas
			foreach ( $r as $anon_key => $anon_value ) {
				update_post_meta( $topic_id, '_' . $anon_key, (string) $anon_value, false );
			}

			// Set transient for throttle check (only on new, not edit)
			if ( empty( $is_edit ) ) {
				set_transient( '_bbp_' . bbp_current_author_ip() . '_last_posted', time() );
			}

		} else {
			if ( empty( $is_edit ) && ! current_user_can( 'throttle' ) ) {
				bbp_update_user_last_posted( $author_id );
			}
		}

		// Handle Subscription Checkbox
		if ( bbp_is_subscriptions_active() && ! empty( $author_id ) ) {
			$subscribed = bbp_is_user_subscribed( $author_id, $topic_id );
			$subscheck  = ( ! empty( $_POST['bbp_topic_subscription'] ) && ( 'bbp_subscribe' === $_POST['bbp_topic_subscription'] ) ) ? true : false;

			// Subscribed and unsubscribing
			if ( true === $subscribed && false === $subscheck ) {
				bbp_remove_user_subscription( $author_id, $topic_id );

			// Subscribing
			} elseif ( false === $subscribed && true === $subscheck ) {
				bbp_add_user_subscription( $author_id, $topic_id );
			}
		}

		// Forum topic meta
		bbp_update_topic_forum_id( $topic_id, $forum_id );

		// Update associated topic values if this is a new topic
		if ( empty( $is_edit ) ) {

			// Update poster IP if not editing
			update_post_meta( $topic_id, '_bbp_author_ip', bbp_current_author_ip(), false );

			// Last active time
			$last_active = get_post_field( 'post_date', $topic_id );

			// Reply topic meta
			bbp_update_topic_last_reply_id      ( $topic_id, 0            );
			bbp_update_topic_last_active_id     ( $topic_id, $topic_id    );
			bbp_update_topic_last_active_time   ( $topic_id, $last_active );
			bbp_update_topic_reply_count        ( $topic_id, 0            );
			bbp_update_topic_reply_count_hidden ( $topic_id, 0            );
			bbp_update_topic_voice_count        ( $topic_id               );

		// Walk up ancestors and do the dirty work
		// bbp_update_topic_walker( $topic_id, $last_active, $forum_id, 0, false );
		}
	}

	/**
	 * Handle only the necessary meta stuff from posting a new reply or editing a reply
	 *
	 * @param int $reply_id Optional. Reply id
	 * @param int $topic_id Optional. Topic id
	 * @param int $forum_id Optional. Forum id
	 * @param bool|array $anonymous_data Optional logged-out user data.
	 * @param int $author_id Author id
	 * @param bool $is_edit Optional. Is the post being edited? Defaults to false.
	 * @param int $reply_to Optional. Reply to id
	 * @uses bbp_get_reply_id() To get the reply id
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses bbp_get_current_user_id() To get the current user id
	 * @uses bbp_get_reply_topic_id() To get the reply topic id
	 * @uses bbp_get_topic_forum_id() To get the topic forum id
	 * @uses update_post_meta() To update the reply metas
	 * @uses set_transient() To update the flood check transient for the ip
	 * @uses bbp_update_user_last_posted() To update the users last posted time
	 * @uses bbp_is_subscriptions_active() To check if the subscriptions feature is
	 *                                      activated or not
	 * @uses bbp_is_user_subscribed() To check if the user is subscribed
	 * @uses bbp_remove_user_subscription() To remove the user's subscription
	 * @uses bbp_add_user_subscription() To add the user's subscription
	 * @uses bbp_update_reply_forum_id() To update the reply forum id
	 * @uses bbp_update_reply_to() To update the reply to id
	 * @uses bbp_update_reply_walker() To update the reply's ancestors' counts
	 */
	function bbp_update_reply( $reply_id = 0, $topic_id = 0, $forum_id = 0, $anonymous_data = false, $author_id = 0, $is_edit = false, $reply_to = 0 ) {

		// Validate the ID's passed from 'bbp_new_reply' action
		$reply_id = bbp_get_reply_id( $reply_id );
		$topic_id = bbp_get_topic_id( $topic_id );
		$forum_id = bbp_get_forum_id( $forum_id );
		$reply_to = bbp_validate_reply_to( $reply_to, $reply_id );

		// Bail if there is no reply
		if ( empty( $reply_id ) ) {
			return;
		}

		// Check author_id
		if ( empty( $author_id ) ) {
			$author_id = bbp_get_current_user_id();
		}

		// Check topic_id
		if ( empty( $topic_id ) ) {
			$topic_id = bbp_get_reply_topic_id( $reply_id );
		}

		// Check forum_id
		if ( ! empty( $topic_id ) && empty( $forum_id ) ) {
			$forum_id = bbp_get_topic_forum_id( $topic_id );
		}

		// If anonymous post, store name, email, website and ip in post_meta.
		// It expects anonymous_data to be sanitized.
		// Check bbp_filter_anonymous_post_data() for sanitization.
		if ( ! empty( $anonymous_data ) && is_array( $anonymous_data ) ) {

			// Parse arguments against default values
			$r = bbp_parse_args( $anonymous_data, array(
				'bbp_anonymous_name'    => '',
				'bbp_anonymous_email'   => '',
				'bbp_anonymous_website' => '',
			), 'update_reply' );

			// Update all anonymous metas
			foreach ( $r as $anon_key => $anon_value ) {
				update_post_meta( $reply_id, '_' . $anon_key, (string) $anon_value, false );
			}

			// Set transient for throttle check (only on new, not edit)
			if ( empty( $is_edit ) ) {
				set_transient( '_bbp_' . bbp_current_author_ip() . '_last_posted', time() );
			}

		} else {
			if ( empty( $is_edit ) && !current_user_can( 'throttle' ) ) {
				bbp_update_user_last_posted( $author_id );
			}
		}

		// Handle Subscription Checkbox
		if ( bbp_is_subscriptions_active() && ! empty( $author_id ) && ! empty( $topic_id ) ) {
			$subscribed = bbp_is_user_subscribed( $author_id, $topic_id );
			$subscheck  = ( ! empty( $_POST['bbp_topic_subscription'] ) && ( 'bbp_subscribe' === $_POST['bbp_topic_subscription'] ) ) ? true : false;

			// Subscribed and unsubscribing
			if ( true === $subscribed && false === $subscheck ) {
				bbp_remove_user_subscription( $author_id, $topic_id );

			// Subscribing
			} elseif ( false === $subscribed && true === $subscheck ) {
				bbp_add_user_subscription( $author_id, $topic_id );
			}
		}

		// Reply meta relating to reply position in tree
		bbp_update_reply_forum_id( $reply_id, $forum_id );
		bbp_update_reply_topic_id( $reply_id, $topic_id );
		bbp_update_reply_to      ( $reply_id, $reply_to );

		// Update associated topic values if this is a new reply
		if ( empty( $is_edit ) ) {

			// Update poster IP if not editing
			update_post_meta( $reply_id, '_bbp_author_ip', bbp_current_author_ip(), false );

			// Last active time
			$last_active_time = get_post_field( 'post_date', $reply_id );

			// Walk up ancestors and do the dirty work
			// bbp_update_reply_walker( $reply_id, $last_active_time, $forum_id, $topic_id, false );

			bbp_update_topic_last_reply_id( $topic_id, $reply_id );
			bbp_update_topic_last_active_id( $topic_id, $reply_id );

			// Get the last active time if none was passed
			$topic_last_active_time = $last_active_time;
			if ( empty( $last_active_time) ) {
				$topic_last_active_time = get_post_field( 'post_date', bbp_get_topic_last_active_id( $topic_id ) );
			}

			// Update the topic last active time regardless of reply status.
			// See https://bbpress.trac.wordpress.org/ticket/2838
			bbp_update_topic_last_active_time( $topic_id, $topic_last_active_time );

			// Counts
			bbp_update_topic_voice_count( $topic_id );

			// Only update reply count if we're deleting a reply, or in the dashboard.
			if ( in_array( current_filter(), array( 'bbp_deleted_reply', 'save_post' ), true ) ) {
				bbp_update_topic_reply_count( $topic_id );
				bbp_update_topic_reply_count_hidden( $topic_id );
			}
		}
	}
}
