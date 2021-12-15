<?php
/**
 * Admin functionality of the plugin.
 *
 * @package HelpHub_Contributors
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * HelpHub Contributors Admin Class
 *
 * The admin functionality of the plugin.
 *
 * @since 1.0.0
 */
class Helphub_Contributors_Admin {
	/**
	 * Unique ID of plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string  $helphub_contributors Unique ID of plugin.
	 */
	private $helphub_contributors;

	/**
	 * The version of plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string  $version The current version of plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 * @param string  $helphub_contributors  The name of the plugin.
	 * @param string  $version               The version of plugin.
	 */
	public function __construct( $helphub_contributors, $version ) {

		$this->helphub_contributors = $helphub_contributors;
		$this->version              = $version;

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'post_submitbox_misc_actions', array( $this, 'add_contributors' ) );
		add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );

		// Show contributors for all post types with edit UI.
		// Easily hide it with post_type_supports()
		$post_types = get_post_types( array( 'show_ui' => true ) );
		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $post_type ) {
				add_post_type_support( $post_type, 'helphub-contributors' );
				add_action( "manage_edit-{$post_type}_columns", array( $this, 'add_column' ) );
				add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'show_column' ), 10, 2 );
			}
		}
	}

	/**
	 * Enqueue assets for the admin area.
	 *
	 * @since 1.0.0
	 */
	public function admin_enqueue_scripts() {
		// Styles.
		wp_enqueue_style( 'select2', plugin_dir_url( __FILE__ ) . 'css/select2.min.css', array(), '1.0.0' );
		// Scripts.
		wp_enqueue_script( 'select2', plugin_dir_url( __FILE__ ) . 'js/select2.min.js', array( 'jquery' ), '1.0.0', true );
		wp_enqueue_script( $this->helphub_contributors, plugin_dir_url( __FILE__ ) . 'js/helphub-contributors-admin.js', array( 'jquery' ), $this->version, false );
	}
	/**
	 * Add select field to Publish metabox.
	 * Attached to 'post_submitbox_misc_actions' action hook.
	 */
	public function add_contributors() {
		$post = get_post();

		if ( ! $post ) {
			return;
		}

		if ( ! post_type_supports( $post->post_type, 'helphub-contributors' ) ) {
			return;
		}
		// Set nonce.
		wp_nonce_field( 'helphub-contributors-save', 'helphub_contributors_nonce' );
		// Get existing contributors.
		$contributors = get_post_meta( $post->ID, 'helphub_contributors' ); ?>

		<div class="misc-pub-section helphub-contributors">
			<label><?php esc_html_e( 'Contributors', 'wporg-forums' ); ?>
				<select id="helphub-contributors" class="widefat" multiple name="helphub_contributors[]">
					<?php foreach ( $contributors[0] as $contributor ) : ?>
						<option value="<?php echo esc_attr( $contributor ); ?>" selected="selected"><?php echo esc_html( $contributor ); ?></option>
					<?php endforeach; ?>
				</select><!-- #helphub-contributors -->
			</label>
			<p class="description"><?php esc_html_e( 'Type wp.org username for contributor', 'wporg-forums' ); ?></p>
		</div><!-- misc-pub-section helphub-contributors -->
		<?php
	}

	/**
	 * Save contributors as post meta on save post.
	 * Attached to 'save_post' action hook.
	 *
	 * @param int     $post_id  Post id
	 * @param WP_Post $post     Post object
	 */
	public function save_post( $post_id, $post ) {
		// Verify nonce.
		if ( ! isset( $_POST['helphub_contributors_nonce'] ) || ! wp_verify_nonce( $_POST['helphub_contributors_nonce'], 'helphub-contributors-save' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['helphub_contributors'] ) || empty( $_POST['helphub_contributors'] ) ) {
			return;
		}

		$contributors = $_POST['helphub_contributors'];

		update_post_meta( $post_id, 'helphub_contributors', $contributors );
	}

	/**
	 * Add Contributors column on edit.php screen.
	 * Attached to "manage_edit-{$post_type}_columns" action hook.
	 *
	 * @param array $columns Array of columns
	 */
	public function add_column( $columns ) {
		$columns['helphub_contributors'] = esc_html__( 'Contributors', 'wporg-forums' );
		return $columns;
	}

	/**
	 * Show Contributors column on edit.php screen.
	 * Attached to "manage_{$post_type}_posts_custom_column" action hook.
	 *
	 * @param string $column  Column ID
	 * @param int    $post_id Post id
	 */
	public function show_column( $column, $post_id ) {
		if ( 'helphub_contributors' !== $column ) {
			return;
		}

		$contributors = get_post_meta( $post_id, 'helphub_contributors', true );

		if ( empty( $contributors ) ) {
			return;
		}

		if ( is_array( $contributors ) ) :
			foreach ( $contributors as $contributor ) {
				$contributor_link = '<a href="https://profiles.wordpress.org/' . esc_html( $contributor ) . '/">@' . esc_html( $contributor ) . '</a>';

				if ( end( $contributors ) == $contributor ) {
					$contributor_link .= esc_html__( '.', 'wporg-forums' );
				} else {
					$contributor_link .= esc_html__( ', ', 'wporg-forums' );
				}

				echo $contributor_link;
			}
		endif;
	}
}
