<?php

namespace WordPressdotorg\Forums\Topic_Resolution;

class Plugin {

	/**
	 * @todo Edit enabled forums - will need to be updateable from the admin interface for administrators during forum setup
	 * Decisions: default topic resolution is 'no'
	 */

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

		// Display the form for new/edited topics.
		add_action( 'bbp_theme_before_topic_form_content', array( $this, 'form_topic_resolution_dropdown' ) );
		add_action( 'bbp_theme_before_topic_form_subscriptions', array( $this, 'form_topic_resolution_checkbox' ) );

		// Process field submission for topics.
		add_action( 'bbp_new_topic_post_extras', array( $this, 'topic_post_extras' ) );
		add_action( 'bbp_edit_topic_post_extras', array( $this, 'topic_post_extras' ) );

		// Register views.
		add_action( 'bbp_register_views', array( $this, 'register_views' ) );

		// Indicate if the forum is a support forum.
		add_filter( 'manage_forum_posts_columns', array( $this, 'add_forum_topic_resolution_column' ), 11 );
		add_action( 'manage_forum_posts_custom_column', array( $this, 'add_forum_topic_resolution_value' ), 10, 2 );

		// Process field submission
		// @todo Bulk actions aren't filterable, so this might be hacky.
	}

	/**
	 * Add "Resolved" status to title.
	 */
	public function get_topic_title( $title, $topic_id ) {
		$resolved = __( 'Resolved', 'wporg-forums' );
		if ( 'yes' == $this->get_topic_resolution( array( 'id' => $topic_id ) ) ) {
		   return sprintf( esc_html( '[%s]: %s' ), $resolved, $title );
		}
		return $title;
	}

	/**
	 * Output topic resolution selection dropdown.
	 */
	public function form_topic_resolution_dropdown() {
		// Only display on forums where this is enabled
		if ( ! $this->is_enabled_on_forum() ) {
			return;
		}

		// Only display on topic edits
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
		// @todo Make this actually check the option; for testing, 'true' is okay
		return true;

		$forum = bbp_get_forum( $forum_id );
		if ( empty( $forum ) ) {
			return;
		}

		$enabled = $this->get_enabled_forums();
		if ( $enabled && in_array( $forum_id, $enabled ) ) {
			return true;
		}
		return false;
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

		update_post_meta( $r['id'], self::META_KEY, $resolution );
	}

	public function get_topic_resolutions() {
		return apply_filters( 'wporg_bbp_get_topic_resolutions', array(
				'no'  => __( 'not resolved', 'wporg-forums' ),
				'yes' => __( 'resolved', 'wporg-forums' ),
				'mu'  => __( 'not a support question', 'wporg-forums' ),
		) );
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
}
