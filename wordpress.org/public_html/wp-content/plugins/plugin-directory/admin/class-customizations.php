<?php
namespace WordPressdotorg\Plugin_Directory\Admin;

/**
 * All functionality related to the Administration interface.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin
 */
class Customizations {

	/**
	 * Fetch the instance of the Plugin_Directory class.
	 */
	public static function instance() {
		static $instance = null;

		return ! is_null( $instance ) ? $instance : $instance = new Customizations();
	}

	/**
	 *
	 */
	private function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_admin_metaboxes' ) );
		add_action( 'edit_form_after_title', array( $this, 'edit_form_after_title' ) );
	}

	/**
	 * Register the Admin metaboxes for the plugin management screens.
	 */
	public function register_admin_metaboxes() {
		add_meta_box(
			'plugin-committers',
			__( 'Plugin Committers', 'wporg-plugins' ),
			array( __NAMESPACE__ . '\\Metabox\\Committers', 'display' ),
			'plugin'
		);
	}

	/**
	 * Displays a link to the plugins zip file.
	 *
	 * @param \WP_Post $post
	 */
	public function edit_form_after_title( $post ) {
		$zip_files = get_attached_media( 'application/zip', $post );
		$zip_file  = current( $zip_files );

		if ( $zip_file ) :
			?>

			<p style="padding: 0 10px;">
				<?php printf( __( '<strong>Zip file:</strong> %s' ), sprintf( '<a href="%s">%s</a>', esc_url( $zip_file->guid ), $zip_file->guid ) ); ?>
			</p>

		<?php
		endif;
	}
}
