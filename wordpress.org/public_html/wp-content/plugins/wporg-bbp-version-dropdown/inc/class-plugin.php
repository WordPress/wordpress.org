<?php

namespace WordPressdotorg\Forums\Version_Dropdown;

class Plugin {

	/**
	 * @var Plugin The singleton instance.
	 */
	private static $instance;

	const META_KEY = 'wp_version';

	/**
	 * Returns always the same instance of this plugin.
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
		// Display the form
		add_action( 'bbp_theme_before_topic_form_content', array( $this, 'form_topic_version_dropdown' ) );

		// Process field submission
		add_action( 'bbp_new_topic_post_extras', array( $this, 'topic_post_extras' ) );
		add_action( 'bbp_edit_topic_post_extras', array( $this, 'topic_post_extras' ) );

		// Scripts and styles
		add_action( 'bbp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// WordPress.org integration
		if ( function_exists( 'wporg_get_versions' ) ) {
			add_filter( 'wporg_bbp_get_wp_versions', 'wporg_get_versions' );
		}
	}

	/**
	 * Output value topic version dropdown
	 */
	public function form_topic_version_dropdown() {
		$version = 0;
		$other_version = '';
		$versions = $this->get_wp_versions();

		// Post value passed
		if ( bbp_is_topic_form_post_request() && isset( $_POST[ self::META_KEY ] ) ) {
			$version = $this->sanitize_wp_version( $_POST[ self::META_KEY ] );

		// No post value passed
		} else if ( bbp_is_single_topic() || bbp_is_topic_edit() ) {
			$version = $this->get_topic_version( array( 'id' => bbp_get_topic_id() ) );
			if ( false === $version ) {
				$version = 0;
			}
		}

		if ( ! empty( $version ) && ! array_key_exists( $version, $versions ) ) {
			$other_version = $version;
			$version = 'other';
		}
		?>
		<p>
			<label for="wp-version"><?php esc_html_e( 'Version:', 'wporg-forums' ); ?></label><br />
			<em><?php esc_html_e( 'Select the version of WordPress you are using.', 'wporg-forums' ); ?></em><br />

			<select name="<?php echo esc_attr( self::META_KEY ); ?>" id="wp-version">
				<?php foreach ( $versions as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $version ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>

			<label for="wp-other-version"><?php esc_html_e( 'Enter a different WordPress version here:', 'wporg-forums' ); ?></label>
			<input type="text" name="wp_other_version" id="wp-other-version" value="<?php echo esc_attr( $other_version ); ?>">
		</p>
		<?php
	}

	public function topic_post_extras( $topic_id ) {
		$version = false;
		$versions = $this->get_wp_versions();
		if ( ( $_POST[ self::META_KEY ] ) && in_array( $_POST[ self::META_KEY ], $versions ) ) {
			$version = $this->sanitize_wp_version( $_POST[ self::META_KEY ] );
		} else if ( isset( $_POST['wp_other_version'] ) ) {
			$version = $this->sanitize_wp_version( $_POST['wp_other_version'] );
		}

		if ( false !== $version ) {
			$this->set_topic_version( array(
				'id' => $topic_id,
				'version' => $version
			) );
		}
	}

	public function enqueue_scripts() {
		if ( bbp_is_single_forum() || bbp_is_single_topic() || bbp_is_topic_edit() ) {
			wp_enqueue_script( 'wporg-bbp-version-dropdown', plugins_url( 'wporg-bbp-version-dropdown.js', __DIR__ ), array( 'jquery' ), '20160729', true );
		}
	}

	public static function get_topic_version( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'id' => 0,
		), 'get_topic_version' );

		$topic = bbp_get_topic( $r['id'] );

		if ( empty( $topic ) ) {
			return;
		}

		$retval = get_post_meta( $topic->ID, self::META_KEY, true );
		return apply_filters( 'get_topic_version', $retval, $r, $args );
	}

	public function set_topic_version( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'id'      => 0,
			'version' => '',
		), 'set_topic_version' );

		$topic = bbp_get_topic( $r['id'] );

		if ( empty( $topic ) ) {
			return;
		}

		if ( empty( $r['version'] ) ) {
			delete_post_meta( $r['id'], self::META_KEY );
		} else {
			update_post_meta( $r['id'], self::META_KEY, $r['version'] );
		}
	}

	public function get_wp_versions() {
		$versions = array_merge(
			array( '0' => '' ),
			apply_filters( 'wporg_bbp_get_wp_versions', array() ),
			array( 'other' => __( 'Other:', 'wporg-forums' ) )
		);
		return $versions;
	}

	public function sanitize_wp_version( $version ) {
		return preg_replace( '#[^0-9a-z.-]#i', '', $version );
	}
}
