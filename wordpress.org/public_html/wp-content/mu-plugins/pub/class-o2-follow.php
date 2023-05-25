<?php
/**
 * Plugin Name: Jetpack Follow Link for o2
 * Description: Easily subscribe to an o2 comment thread without commenting using a "Follow" action link like WordPress.com has.
 * Author:      WordPress.org
 * Author URI:  https://wordpress.org/
 * Version:     1.0
 * License:     GPLv2 or later
 *
 * @package o2
 */

/**
 * Class o2_follow.
 */
class o2_follow {

	// Use the old p2 meta key, for backwards compatibility.
	// Warning: Be careful using this in other contexts. See `subscribe_to_comments()` for details.
	const USER_META_KEY = 'jpflfp2_posts_following';

	/**
	 * Construct the plugin
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'action_init' ) );
	}

	/**
	 * Initialize plugin.
	 */
	public function action_init() {
		if ( ! current_theme_supports( 'o2' ) || ! class_exists( 'Jetpack_Subscriptions' ) ) {
			return;
		}

		// Only logged in users should be able to subscribe for now because we use the registered email address.
		if ( ! is_user_logged_in() ) {
			return;
		}

		add_action( 'o2_post_form_extras', array( $this, 'subscription_o2_post_form' ) );

		add_action( 'init', array( $this, 'subscribe_o2_register_post_action_states' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		add_action( 'o2_writeapi_post_updated', array( $this, 'check_post_for_subscription' ), 10, 2 );
		add_action( 'o2_writeapi_post_created', array( $this, 'check_post_for_subscription' ), 10, 2 );

		add_action( 'template_redirect', array( $this, 'handle_following_action' ) );

		// Filters.
		add_filter( 'o2_options', array( $this, 'get_options' ) );
		add_filter( 'o2_filter_post_actions', array( $this, 'subscription_o2_add_comment_subscriber_link' ), 10, 2 );

		add_filter( 'o2_comment_form_extras', array( $this, 'subscribe_o2_comment_form' ), 10, 2 );
		add_filter( 'o2_options', array( $this, 'subscribe_add_o2_options' ) );
		add_filter( 'o2_post_fragment', array( $this, 'subscribe_add_o2_post_fragment' ), 10, 2 );

		$this->subscribe_o2_register_post_action_states();
	}

	/**
	 * Enqueue scripts.
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( 'o2-follow', plugins_url( 'modules/follow/css/style.css', O2__FILE__ ) );

		wp_enqueue_script( 'o2-extend-follow-models-post', plugins_url( 'modules/follow/js/models/post.js', O2__FILE__ ), array( 'o2-cocktail', 'o2-models-post', 'o2-notifications' ) );
		wp_enqueue_script( 'o2-extend-follow-views-comment', plugins_url( 'modules/follow/js/views/comment.js', O2__FILE__ ), array( 'o2-cocktail', 'o2-views-comment' ) );
		wp_enqueue_script( 'o2-extend-follow-views-post', plugins_url( 'modules/follow/js/views/post.js', O2__FILE__ ), array( 'o2-cocktail', 'o2-extend-follow-models-post' ) );
	}

	/**
	 * Add follow strings and options to the o2 options array.
	 *
	 * @param array $options O2 options.
	 * @return array
	 */
	public function get_options( $options ) {
		$localizations      = array(
			'follow'               => __( 'Follow', 'wporg' ),
			'followComments'       => __( 'Follow comments', 'wporg' ),
			'unfollow'             => __( 'Unfollow', 'wporg' ),
			'unfollowComments'     => __( 'Unfollow comments', 'wporg' ),
			'followError'          => __( 'There was a problem updating your following preferences.', 'wporg' ),
			'followingAll'         => __( 'Following all', 'wporg' ),
			'followingAllComments' => __( 'You are already following all comments on this site.', 'wporg' ),
		);
		$localizations      = array_merge( $options['strings'], $localizations );
		$options['strings'] = $localizations;

		if ( ! isset( $options['options']['followingBlog'] ) ) {
			$options['options']['followingBlog'] = false;
		}

		if ( ! isset( $options['options']['followingAllComments'] ) ) {
			$options['options']['followingAllComments'] = false;
		}

		return $options;
	}

	/**
	 * Returns a subscription form.
	 *
	 * @param string $comment_form_extras Comment form extras. Default: Empty string.
	 * @param bool   $post_id Post ID. Default: null.
	 * @return string
	 */
	public function subscribe_o2_comment_form( $comment_form_extras = '', $post_id = null ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return $comment_form_extras;
		}

		$post_id        = $post->ID;
		$subscribed_ids = (array) get_user_meta( wp_get_current_user()->ID, self::USER_META_KEY, true );
		$checked        = in_array( $post_id, $subscribed_ids, true );

		$comment_form_extras .= '<p class="comment-subscription-form">';
		$comment_form_extras .= '<input type="checkbox" name="subscribe" id="subscribe" value="subscribe" style="width: auto;"' . checked( $checked, true, false ) . '/> ';
		$comment_form_extras .= '<label class="subscribe-label" id="subscribe-label" for="subscribe" style="display: inline;">' . esc_html__( 'Notify me of new comments via email.', 'wporg' ) . '</label>';
		$comment_form_extras .= '</p>';

		return $comment_form_extras;
	}

	/**
	 * Adds O2 options.
	 *
	 * @param array $options O2 options.
	 * @return array
	 */
	public function subscribe_add_o2_options( $options ) {
		$options['options']['followingBlog']        = false;
		$options['options']['followingAllComments'] = false;

		return $options;
	}

	/**
	 * Adds post fragment.
	 *
	 * @param array $fragment Fragments. Default: Empty array.
	 * @param bool  $post_id Post ID. Default: null.
	 *
	 * @return array
	 */
	public function subscribe_add_o2_post_fragment( $fragment = [], $post_id = null ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return $fragment;
		}

		if ( ! array_key_exists( 'postMeta', $fragment ) ) {
			$fragment['postMeta'] = [];
		}

		$subscribed_ids                      = (array) get_user_meta( wp_get_current_user()->ID, self::USER_META_KEY, true );
		$fragment['postMeta']['isFollowing'] = in_array( $post_id, $subscribed_ids, true );

		return $fragment;
	}

	/**
	 * Add 'subscribe' checkbox to o2 post form.
	 *
	 * @param string $post_form_extras Comment form extras. Default: Empty string.
	 * @return string
	 **/
	public function subscription_o2_post_form( $post_form_extras = '' ) {
		$label = esc_html__( 'Notify me of new comments via email.', 'wporg' );

		$post_form_extras .= '<p style="margin-top: 1.5em;" class="comment-subscription-form"><input type="checkbox" name="post_subscribe" id="post_subscribe" value="post_subscribe" style="margin-left: .5em;"/>';
		$post_form_extras .= '<label style="font-size: 1.2em; margin-bottom: .5em;" id="post_subscribe_label" for="post_subscribe"><small>' . $label . '</small></label>';
		$post_form_extras .= '</p>';

		return $post_form_extras;
	}

	/**
	 * Registers post actions.
	 */
	public function subscribe_o2_register_post_action_states() {
		if ( function_exists( 'o2_register_post_action_states' ) ) {
			o2_register_post_action_states( 'follow', [
				'normal'     => [
					'shortText' => __( 'Follow', 'wporg' ),
					'title'     => __( 'Follow comments', 'wporg' ),
					'classes'   => [],
					'genericon' => 'genericon-subscribe',
					'nextState' => 'subscribed',
				],
				'subscribed' => [
					'shortText' => __( 'Following', 'wporg' ),
					'title'     => __( 'Unfollow comments', 'wporg' ),
					'classes'   => [ 'post-comments-subscribed' ],
					'genericon' => 'genericon-unsubscribe',
					'nextState' => 'normal',
				],
			] );
		}
	}

	/**
	 * Adds a subscriber link.
	 *
	 * @param array $actions Actions.
	 * @param bool  $post_id Post ID. Default: null.
	 * @return array
	 */
	public function subscription_o2_add_comment_subscriber_link( $actions, $post_id = null ) {
		global $post;

		$post_id = $post_id ?: $post->ID;

		if ( comments_open( $post_id ) ) {
			$initial_state = 'normal';
			$query_args    = [
				'post-id' => $post_id,
				'action'  => 'post-comment-subscribe',
			];

			$subscribed_ids = (array) get_user_meta( wp_get_current_user()->ID, self::USER_META_KEY, true );

			if ( in_array( $post_id, $subscribed_ids, true ) ) {
				$query_args['action'] = 'post-comment-unsubscribe';
				$initial_state        = 'subscribed';
			}

			$link = add_query_arg( $query_args, home_url( '/', is_ssl() ? 'https' : 'http' ) );
			$link = wp_nonce_url( $link, 'post-comment-subscribe' );

			$actions[31] = [
				'action'       => 'follow',
				'href'         => $link,
				'classes'      => [ 'subscription-link', 'o2-follow' ],
				'rel'          => false,
				'initialState' => $initial_state,
			];
		}

		return $actions;
	}

	/**
	 * Checks post for subscription.
	 *
	 * @param int    $post_id Post ID..
	 * @param object $message Message.
	 */
	public function check_post_for_subscription( $post_id, $message ) {
		if ( ! empty( $message->isFollowing ) ) {
			$this->subscribe_to_comments( $post_id );
		}
	}

	/**
	 * Handles action.
	 */
	public function handle_following_action() {
		// Bail if the action isn't ours.
		if ( ! isset( $_GET['post-id'], $_GET['action'], $_GET['_wpnonce'] ) ) {
			return;
		}

		$post_id = intval( $_GET['post-id'] );

		$post = get_post( $post_id );
		if ( ! $post ) {
			die;
		}

		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'post-comment-subscribe' ) ) {
			die;
		}

		if ( ! comments_open( $post_id ) ) {
			die;
		}

		if ( 'post-comment-subscribe' === $_GET['action'] ) {
			$this->subscribe_to_comments( $post_id );
		}

		// Echo success if this was an AJAX request, otherwise redirect.
		if ( isset( $_GET['ajax'] ) ) {
			echo '1';
		} else {
			wp_safe_redirect( get_permalink( $post_id ) );
		}
		die;
	}

	/**
	 * Subscribes the current user to comments.
	 *
	 * @param int $post_id Post ID.
	 */
	public function subscribe_to_comments( $post_id ) {
		$current_user          = wp_get_current_user();
		$jetpack_subscriptions = Jetpack_Subscriptions::init();

		$response = $jetpack_subscriptions->subscribe(
			$current_user->user_email,
			array( $post_id ),
			true,
			array(
				'source'         => 'widget',
				'widget-in-use'  => is_active_widget( false, false, 'blog_subscription', true ) ? 'yes' : 'no',
				'comment_status' => '',
				'server_data'    => jetpack_subscriptions_cherry_pick_server_data(),
			)
		);

		// todo: This only checks that the data passed was valid, not that the remote request was successful. The
		// code below counts the post as being followed even if the remote request failed. That's necessary
		// because it's done async. Maybe refactor to be synchronous and update this to check all error
		// conditions.
		if ( is_wp_error( $response ) ) {
			return;
		}

		$subscribed_ids   = (array) get_user_meta( $current_user->ID, self::USER_META_KEY, true );
		$subscribed_ids[] = $post_id;
		$subscribed_ids   = array_unique( $subscribed_ids );

		/*
		 * Warning: Be careful when using this data in any other context. It's not indexed by blog ID, so there's
		 * no way to know which post it actually refers to.
		 *
		 * For example, if you were to use it to email comment notifications to followers of a private post, you
		 * would also be emailing followers of posts on other sites which happened to have the same post ID, and
		 * would expose any sensitive information in those comments to random people who otherwise wouldn't have
		 * access to it.
		 */
		update_user_meta( $current_user->ID, self::USER_META_KEY, $subscribed_ids );
	}
}
