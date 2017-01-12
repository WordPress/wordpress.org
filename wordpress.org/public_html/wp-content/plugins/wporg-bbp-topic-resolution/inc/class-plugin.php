<?php

namespace WordPressdotorg\Forums\Topic_Resolution;

class Plugin {

	/**
	 * @var Plugin The singleton instance.
	 */
	private static $instance;

	const META_KEY = 'topic_resolved';

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
	 * Instantiates a new Plugin object.
	 */
	private function __construct() {
		add_action( 'bbp_loaded', array( $this, 'bbp_loaded' ) );
	}

	/**
	 * Initializes the plugin.
	 */
	public function bbp_loaded() {
		// Change the topic title when resolved.
		add_filter( 'bbp_get_topic_title', array( $this, 'get_topic_title' ), 10, 2 );

		// Add resolved indicator to single topic view.
		add_action( 'bbp_theme_before_topic_author_details', array( $this, 'single_topic_resolved_indicator' ) );

		// Display the form for new/edit topics.
		add_action( 'bbp_theme_before_topic_form_content', array( $this, 'form_topic_resolution_dropdown' ) );
		add_action( 'bbp_theme_before_topic_form_subscriptions', array( $this, 'form_topic_resolution_checkbox' ) );

		// Process field submission for topics.
		add_action( 'bbp_new_topic_post_extras', array( $this, 'topic_post_extras' ) );
		add_action( 'bbp_edit_topic_post_extras', array( $this, 'topic_post_extras' ) );

		// Add form for moderator/user topic resolution update.
		// @todo Add AJAX processing to front-end topic resolution updates.
		add_action( 'bbp_post_request', array( $this, 'topic_resolution_handler' ) );

		// Register views.
		add_action( 'bbp_register_views', array( $this, 'register_views' ) );

		// Indicate if the forum is a support forum.
		add_filter( 'manage_forum_posts_columns', array( $this, 'add_forum_topic_resolution_column' ), 11 );
		add_action( 'manage_forum_posts_custom_column', array( $this, 'add_forum_topic_resolution_value' ), 10, 2 );

		// Process changes to a forums support status.
		// @todo Bulk actions aren't filterable, so this might be hacky.
	}

	/**
	 * Add "Resolved" status to title.
	 */
	public function get_topic_title( $title, $topic_id ) {
		// Only run when enabled on a topic's forum.
		if ( bbp_is_single_topic() || ! $this->is_enabled_on_forum( bbp_get_topic_forum_id( $topic_id ) ) ) {
			return $title;
		}

		if ( 'yes' == $this->get_topic_resolution( array( 'id' => $topic_id ) ) ) {
			$title = sprintf(
				'<span class="resolved" aria-label="%s" title="%s"></span>',
				esc_attr__( 'Resolved', 'wporg-forums' ),
				esc_attr__( 'Topic is resolved.', 'wporg-forums' )
			) . $title;
		}

		return $title;
	}

	/**
	 * Outputs resolved topic indicator.
	 */
	public function single_topic_resolved_indicator() {
		$topic_id = bbp_get_topic_id();

		if (
			// Must be single topic view
			! bbp_is_single_topic()
			||
			// Must be enabled on the forum
			! $this->is_enabled_on_forum( bbp_get_topic_forum_id( $topic_id ) )
			||
			// Must be a resolved topic
			'yes' !== $this->get_topic_resolution( array( 'id' => $topic_id ) )
		) {
			return;
		}

		echo '<span class="topic-resolved-indicator">' . __( 'Answered', 'wporg-forums' ) . '</span>';
	}

	/**
	 * Output topic resolution selection dropdown.
	 */
	public function form_topic_resolution_dropdown() {
		// Only display on forums where this is enabled
		if ( ! $this->is_enabled_on_forum() ) {
			return;
		}

		// Only display on topic edit.
		if ( ! bbp_is_topic_edit() ) {
			return;
		}

		$resolutions = $this->get_topic_resolutions();

		// Post value passed
		if ( bbp_is_topic_form_post_request() && isset( $_POST[ self::META_KEY ] ) ) {
			$resolution = $this->sanitize_topic_resolution( $_POST[ self::META_KEY ] );

		// No post value passed
		} else if ( bbp_is_single_topic() || bbp_is_topic_edit() ) {
			$resolution = $this->get_topic_resolution( array( 'id' => bbp_get_topic_id() ) );
		}

		if ( empty( $resolution ) ) {
			$resolution = $this->get_default_topic_resolution();
		}
		?>
		<p><label for="topic-resolved"><?php esc_html_e( 'Topic Status:', 'wporg-forums' ); ?></label><br />

		<select name="<?php echo esc_attr( self::META_KEY ); ?>" id="topic-resolved">

		<?php foreach ( $resolutions as $key => $label ) : ?>

			<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $resolution ); ?>><?php echo esc_html( $label ); ?></option>

		<?php endforeach; ?>

		</select></p>
		<?php
	}

	/**
	 * Output topic resolution checkbox for non-support topics.
	 */
	public function form_topic_resolution_checkbox() {
		// Only display on forums where this is enabled
		if ( ! $this->is_enabled_on_forum() ) {
			return;
		}

		// Only display on new topics
		if ( bbp_is_topic_edit() ) {
			return;
		}
		?>
		<p><label for="topic-resolved"><input type="checkbox" name="<?php echo esc_attr( self::META_KEY ); ?>" id="topic-resolved" value="mu"> <?php esc_html_e( 'This topic is not a support question', 'wporg-forums' ); ?></label></p>
		<?php
	}

	/**
	 * Process topic form submission.
	 */
	public function topic_post_extras( $topic_id ) {
		// Only set a topic resolution on forums where this is enabled
		if ( ! $this->is_enabled_on_forum( bbp_get_topic_forum_id( $topic_id ) ) ) {
			return;
		}

		$resolution = $this->get_default_topic_resolution();
		if ( isset( $_POST[ self::META_KEY ] ) ) {
			$resolution = $this->sanitize_topic_resolution( $_POST[ self::META_KEY ] );
		}

		$this->set_topic_resolution( array(
			'id'         => $topic_id,
			'resolution' => $resolution,
		) );
	}

	/**
	 * Output topic resolution as a string or a selection dropdown.
	 */
	public function get_topic_resolution_form() {
		// Only display on forums where this is enabled
		if ( ! $this->is_enabled_on_forum() ) {
			return;
		}

		// Only display on single topic.
		if ( ! bbp_is_single_topic() && ! bbp_is_topic_edit() ) {
			return;
		}

		// Get topic and user data.
		$topic_id = intval( bbp_get_topic_id() );
		$topic = bbp_get_topic( $topic_id );
		if ( ! $topic_id || ! $topic ) {
			return;
		}

		// Get the current resolution of this topic.
		$resolution = $this->get_topic_resolution( array( 'id' => bbp_get_topic_id() ) );
		if ( empty( $resolution ) ) {
			$resolution = $this->get_default_topic_resolution();
		}
		$resolutions = $this->get_topic_resolutions();

		// Display the current topic resolution if the user can't update it.
		$user_id = get_current_user_id();
		if ( bbp_is_topic_edit() || ! $this->user_can_resolve( $user_id, $topic_id ) ) {
			printf( esc_html__( 'Status: %s', 'wporg-forums' ), $resolutions[ $resolution ] );

		// Display the form to update the topic resolution.
		} else {
			?>
			<form method="POST">
			<input type="hidden" name="action" value="wporg_bbp_topic_resolution" />
			<input type="hidden" name="topic_id" value="<?php echo esc_attr( $topic->ID ); ?>" />
			<?php wp_nonce_field( 'toggle-topic-resolution_' . $topic->ID ); ?>
			<label for="topic-resolved"><?php esc_html_e( 'Status:', 'wporg-forums' ); ?></label>

			<select name="<?php echo esc_attr( self::META_KEY ); ?>" id="topic-resolved">

			<?php foreach ( $resolutions as $key => $label ) : ?>

				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $resolution ); ?>><?php echo esc_html( $label ); ?></option>

			<?php endforeach; ?>

			</select>
			<input type="submit" name="submit" value="<?php esc_attr_e( 'Update', 'wporg-forums' ); ?>" />
			</form></span>
			<?php
		}
	}

	/**
	 * Handle a user setting the topic resolution on a given topic.
	 *
	 * @param string $action The requested action to compare this function to
	 */
	public function topic_resolution_handler( $action = '' ) {
		if ( ! $this->is_enabled_on_forum() ) {
			return false;
		}

		// Bail if the action isn't meant for this function.
		if ( $action != 'wporg_bbp_topic_resolution' ) {
			return;
		}

		// Bail if no topic id or resolution is passed.
		if ( empty( $_POST['topic_id'] ) || empty( $_POST[ self::META_KEY ] ) ) {
			return;
		}

		// Get required data.
		$topic_id   = intval( $_POST['topic_id'] );
		$topic      = bbp_get_topic( $topic_id );
		$user_id    = get_current_user_id();
		$resolution = $_POST[ self::META_KEY ];

		// Check for empty topic id.
		if ( empty( $topic_id ) || ! $topic ) {
			bbp_add_error( 'wporg_bbp_topic_resolution_topic_id', __( '<strong>ERROR</strong>: No topic was found!', 'wporg-forums' ) );

		// Check valid resolution.
		} elseif ( ! $this->is_valid_topic_resolution( $resolution ) ) {
			bbp_add_error( 'wporg_bbp_topic_resolution_invalid', __( '<strong>ERROR</strong>: That is not a valid topic resolution!', 'wporg-forums' ) );

		// Check user permissions.
		} elseif ( ! $this->user_can_resolve( $user_id, $topic->ID ) ) {
			bbp_add_error( 'wporg_bbp_topic_resolution_permissions', __( '<strong>ERROR</strong>: You don\'t have permission to do this!', 'wporg-forums' ) );

		// Check nonce.
		} elseif ( ! bbp_verify_nonce_request( 'toggle-topic-resolution_' . $topic->ID ) ) {
			bbp_add_error( 'wporg_bbp_topic_resolution_nonce', __( '<strong>ERROR</strong>: Are you sure you wanted to do that?', 'wporg-forums' ) );
		}

		if ( bbp_has_errors() ) {
			return;
		}

		// Update the topic resolution.
		$this->set_topic_resolution( array(
			'id'         => $topic->ID,
			'resolution' => $resolution,
		) );

		bbp_redirect( get_permalink( $topic->ID ) );
	}

	/**
	 * Register resolution state views.
	 */
	public function register_views() {
		// Not a support topic.
		bbp_register_view(
			'support-forum-mu',
			__( 'Non-support topics', 'wporg-forums' ),
			apply_filters( 'wporg_bbp_topic_resolution_view_non_support', array(
				'meta_key'      => 'topic_resolved',
				'meta_type'     => 'CHAR',
				'meta_value'    => 'mu',
				'meta_compare'  => '=',
				'orderby'       => '',
				'show_stickies' => false,
			) )
		);

		// Resolved.
		bbp_register_view(
			'support-forum-yes',
			__( 'Resolved topics', 'wporg-forums' ),
			apply_filters( 'wporg_bbp_topic_resolution_view_resolved', array(
				'meta_key'      => 'topic_resolved',
				'meta_type'     => 'CHAR',
				'meta_value'    => 'yes',
				'meta_compare'  => '=',
				'orderby'       => '',
				'show_stickies' => false,
			) )
		);

		// Unresolved.
		bbp_register_view(
			'support-forum-no',
			__( 'Unresolved topics', 'wporg-forums' ),
			apply_filters( 'wporg_bbp_topic_resolution_view_unresolved', array(
				'meta_key'      => 'topic_resolved',
				'meta_type'     => 'CHAR',
				'meta_value'    => 'no',
				'meta_compare'  => '=',
				'orderby'       => '',
				'show_stickies' => false,
			) )
		);
	}

	public function add_forum_topic_resolution_column( $columns ) {
		return array_merge( $columns, array(
			'bbp_topic_resolution' => __( 'Support', 'wporg-forums' ),
		) );
	}

	public function add_forum_topic_resolution_value( $column, $forum_id ) {
		if ( $column === 'bbp_topic_resolution' && $this->is_enabled_on_forum( $forum_id ) ) {
			?>
			<span class="dashicons dashicons-yes"></span>
			<?php
		}
	}

	public function is_enabled_on_forum( $forum_id = 0 ) {
		return apply_filters( 'wporg_bbp_topic_resolution_is_enabled_on_forum', true, $forum_id );
	}

	public function get_enabled_forums() {
		$retval = get_option( '_bbp_topic_resolution_enabled', array() );
		return apply_filters( 'wporg_bbp_get_enabled_forums', $retval );
	}

	public function enable_on_forum( $forum_id = 0 ) {
		$forum = bbp_get_forum( $forum_id );
		if ( empty( $forum ) ) {
			return;
		}

		if ( ! $this->is_enabled_on_forum( $forum_id ) ) {
			$enabled = $this->get_enabled_forums();
			$enabled[] = $forum_id;
			update_option( '_bbp_topic_resolution_enabled', array_values( $enabled ) );
		}
	}

	public function disable_on_forum( $forum_id = 0 ) {
		$forum = bbp_get_forum( $forum_id );
		if ( empty( $forum ) ) {
			return;
		}

		if ( $this->is_enabled_on_forum( $forum_id ) ) {
			$enabled = $this->get_enabled_forums();
			$enabled = array_diff( $enabled, array( $forum_id ) );
			update_option( '_bbp_topic_resolution_enabled', array_values( $enabled ) );
		}
	}

	public function get_topic_resolution( $args = array() ) {
		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'id' => 0,
		), 'get_topic_resolution' );

		$topic = bbp_get_topic( $r['id'] );

		if ( empty( $topic ) ) {
			return;
		}

		// Only return a value on forums where this is enabled
		if ( ! $this->is_enabled_on_forum( bbp_get_topic_forum_id( $topic->ID ) ) ) {
			return;
		}

		$retval = get_post_meta( $topic->ID, self::META_KEY, true );
		return apply_filters( 'wporg_bbp_get_topic_resolution', $retval, $r, $args );
	}

	public function set_topic_resolution( $args = array() ) {
		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'id'         => 0,
			'resolution' => '',
		), 'set_topic_resolution' );

		$topic = bbp_get_topic( $r['id'] );

		if ( empty( $topic ) ) {
			return;
		}

		// Only run this on forums where this is enabled
		if ( ! $this->is_enabled_on_forum( bbp_get_topic_forum_id( $topic->ID ) ) ) {
			return;
		}

		$resolution = $this->sanitize_topic_resolution( $r['resolution'] );

		update_post_meta( $topic->ID, self::META_KEY, $resolution );
		wp_cache_delete( $topic->ID, 'post_meta' );
	}

	public function get_topic_resolutions() {
		return apply_filters( 'wporg_bbp_get_topic_resolutions', array(
			'no'  => __( 'not resolved', 'wporg-forums' ),
			'yes' => __( 'resolved', 'wporg-forums' ),
			'mu'  => __( 'not a support question', 'wporg-forums' ),
		) );
	}

	/**
	 * Is a topic resolution string in the array of possible resolutions?
	 *
	 * @param string $resolution The resolution to check
	 * @return bool True if in the array, false if not
	 */
	public function is_valid_topic_resolution( $resolution ) {
		$resolutions = $this->get_topic_resolutions();
		$retval = in_array( $resolution, array_keys( $resolutions ) );
		return apply_filters( 'wporg_bbp_is_valid_topic_resolution', $retval, $resolution );
	}

	public function get_default_topic_resolution() {
		$retval = get_option( '_bbp_default_topic_resolution' );
		if ( false == $retval ) {
			$retval = 'no';
		}
		return apply_filters( 'wporg_bbp_default_topic_resolution', $retval );
	}

	public function sanitize_topic_resolution( $resolution ) {
		if ( array_key_exists( $resolution, $this->get_topic_resolutions() ) ) {
			$retval = $resolution;
		} else {
			$retval = $this->get_default_topic_resolution();
		}
		return apply_filters( 'wporg_bbp_sanitize_topic_resolution', $retval );
	}

	/**
	 * Is a given user is allowed to update the resolution of a topic?
	 *
	 * @param int $user_id The user id
	 * @param int $topic_id The topic id
	 * @return bool True if allowed, false if not
	 */
	public function user_can_resolve( $user_id, $topic_id ) {
		$topic_id = bbp_get_topic_id();
		if ( $topic_id ) {
			$post = get_post( $topic_id );
		}

		if ( $user_id && $post && ( user_can( $user_id, 'moderate' ) || $user_id == $post->post_author ) ) {
			$retval = true;
		} else {
			$retval = false;
		}
		return apply_filters( 'wporg_bbp_user_can_resolve', $retval, $user_id, $topic_id );
	}
}
