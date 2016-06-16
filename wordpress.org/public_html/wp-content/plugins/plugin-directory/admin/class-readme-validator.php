<?php
namespace WordPressdotorg\Plugin_Directory\Admin;
use \WordPressdotorg\Plugin_Directory\Readme_Parser;
use WordPressdotorg\Plugin_Directory\Tools\Filesystem;

/**
 * A wp-admin interface to validate readme files.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin
 */
class Readme_Validator {

	/**
	 * Fetch the instance of the Readme_Validator class.
	 *
	 * @static
	 */
	public static function instance() {
		static $instance = null;

		return ! is_null( $instance ) ? $instance : $instance = new Readme_Validator();
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugin_page_readme_validator', array( $this, 'add_form_fields' ) );
		add_action( 'plugin_page_readme_validator', array( $this, 'validate' ) );
	}

	/**
	 * Displays a for to validate readme.txt files and blobs of text.
	 */
	public function display() {
		?>
		<div class="wrap">
			<h2><?php _e( 'WordPress Plugin readme.txt Validator', 'wporg-plugins' ); ?></h2>
			<form method="post" action="<?php echo esc_url( add_query_arg( array( 'post_type' => 'plugin', 'page' => 'readme_validator' ), admin_url( 'edit.php' ) ) ); ?>">
				<?php
				wp_nonce_field( 'validate-readme' );
				do_settings_sections( 'readme_validator' );
				submit_button( __( 'Validate', 'wporg-plugins' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Registers form fields for this admin page.
	 */
	public function add_form_fields() {
		add_settings_section( 'default', '', array( $this, 'section_description' ), 'readme_validator' );

		add_settings_field( 'readme_url', __( 'URL to readme.txt file', 'wporg-plugins' ), array( $this, 'url_input' ), 'readme_validator', 'default', array(
			'label_for' => 'readme_url',
		) );
		add_settings_field( 'readme_text', __( 'Text of readme.txt', 'wporg-plugins' ),    array( $this, 'textarea' ),  'readme_validator', 'default', array(
			'label_for' => 'readme_contents',
		) );
	}

	/**
	 * Validates readme.txt contents and adds feedback.
	 */
	public function validate() {
		check_admin_referer( 'validate-readme' );

		$readme    = '';
		$temp_file = Filesystem::temp_directory() . '/readme.txt';
		$warnings  = array();
		$notes     = array();

		if ( ! empty( $_REQUEST['readme_url'] ) ) {
			$url = esc_url_raw( $_REQUEST['readme_url'] );

			if ( strtolower( substr( $url, - 10, 10 ) ) != 'readme.txt' ) {
				add_settings_error( 'wporg-plugins', 'readme-validator', __( 'URL must end in <code>readme.txt</code>!', 'wporg-plugins' ) );
				return;
			}

			if ( ! $readme = @file_get_contents( $url ) ) {
				add_settings_error( 'wporg-plugins', 'readme-validator', __( 'Invalid readme.txt URL.', 'wporg-plugins' ) );
				return;
			}

		} elseif ( ! empty( $_REQUEST['readme_contents'] ) && is_string( $_REQUEST['readme_contents'] ) ) {
			$readme = wp_unslash( $_REQUEST['readme_contents'] );
		}

		if ( empty( $readme ) ) {
			return;
		}

		file_put_contents( $temp_file, $readme );
		$readme = new Readme_Parser( $temp_file );

		// Fatal errors.
		if ( empty( $readme->name ) ) {
			add_settings_error( 'wporg-plugins', 'readme-validator', __( "Fatal Error:\nNo plugin name detected. Plugin names look like: <code>=== Plugin Name ===</code>", 'wporg-plugins' ) );
			return;
		}

		// Warnings.
		if ( empty( $readme->requires_at_least ) ) {
			$warnings[] = __( '<code>Requires at least</code> is missing.', 'wporg-plugins' );
		}
		if ( empty( $readme->tested_up_to ) ) {
			$warnings[] = __( '<code>Tested up to</code> is missing.', 'wporg-plugins' );
		}
		if ( empty( $readme->stable_tag ) ) {
			$warnings[] = __( '<code>Stable tag</code> is missing.  Hint: If you treat <code>/trunk/</code> as stable, put <code>Stable tag: trunk</code>.', 'wporg-plugins' );
		}
		if ( ! count( $readme->contributors ) ) {
			$warnings[] = __( 'No <code>Contributors</code> listed.', 'wporg-plugins' );
		}
		if ( ! count( $readme->tags ) ) {
			$warnings[] = __( 'No <code>Tags</code> specified', 'wporg-plugins' );
		}
		if ( ! empty( $readme->is_excerpt ) ) {
			$warnings[] = __( 'No <code>== Description ==</code> section was found... your short description section will be used instead.', 'wporg-plugins' );
		}
		if ( ! empty( $readme->is_truncated ) ) {
			$warnings[] = __( 'Your short description exceeds the 150 character limit.', 'wporg-plugins' );
		}

		if ( $warnings ) {
			$message = __( 'Warnings:', 'wporg-plugins' ) . "\n<ul class='warning error'>\n";
			foreach ( $warnings as $warning ) {
				$message .= "<li>$warning</li>\n";
			}
			$message .= "</ul>\n</div>";

			add_settings_error( 'wporg-plugins', 'readme-validator', $message, 'notice-warning' );
			return;
		}

		// Notes.
		if ( empty( $readme->license ) ) {
			$notes[] = __( 'No <code>License</code> is specified. WordPress is licensed under &#8220;GPLv2 or later&#8221;', 'wporg-plugins' );
		}
		if ( empty( $readme->sections['installation'] ) ) {
			$notes[] = __( 'No <code>== Installation ==</code> section was found', 'wporg-plugins' );
		}
		if ( empty( $readme->sections['frequently_asked_questions'] ) ) {
			$notes[] = __( 'No <code>== Frequently Asked Questions ==</code> section was found', 'wporg-plugins' );
		}
		if ( empty( $readme->sections['changelog'] ) ) {
			$notes[] = __( 'No <code>== Changelog ==</code> section was found', 'wporg-plugins' );
		}
		if ( empty( $readme->upgrade_notice ) ) {
			$notes[] = __( 'No <code>== Upgrade Notice ==</code> section was found', 'wporg-plugins' );
		}
		if ( empty( $readme->sections['screenshots'] ) ) {
			$notes[] = __( 'No <code>== Screenshots ==</code> section was found', 'wporg-plugins' );
		}
		if ( empty( $readme->donate_link ) ) {
			$notes[] = __( 'No donate link was found', 'wporg-plugins' );
		}

		if ( $notes ) {
			$message = __( 'Notes:' ) . "\n<ul class='note error'>\n";
			foreach ( $notes as $note ) {
				$message .= "<li>$note</li>\n";
			}
			$message .= "</ul>\n</div>";

			add_settings_error( 'wporg-plugins', 'readme-validator', $message, 'notice-info' );
			return;
		}

		add_settings_error( 'wporg-plugins', 'readme-validator', __( 'Your <code>readme.txt</code> rocks.  Seriously.  Flying colors.', 'wporg-plugins' ), 'updated' );
	}

	/**
	 * Help text for the form following after it.
	 */
	public function section_description() {
		echo '<p>' . __( 'Enter the URL to your <code>readme.txt</code> file or paste its content below.' ) . '</p>';
	}

	/**
	 * Displays an input field for the readme.txt URL.
	 */
	public function url_input() {
		$url = empty( $_REQUEST['readme_url'] ) ? '' : $_REQUEST['readme_url'];
		?>
		<label>
			<input type="url" id="readme_url" name="readme_url" size="70" value="<?php echo esc_url( $url ); ?>" />
		</label>
		<?php
	}

	/**
	 * Displays a textarea for readme.txt blobs.
	 */
	public function textarea() {
		$text = empty( $_REQUEST['readme_contents'] ) ? '' : $_REQUEST['readme_contents'];
		?>
		<label>
			<textarea type="text" id="readme_contents" class="large-text" name="readme_contents" cols="50" rows="10"><?php echo esc_textarea( $text ); ?></textarea>
		</label>
		<?php
	}
}
