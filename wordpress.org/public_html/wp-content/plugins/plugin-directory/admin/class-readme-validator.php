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
			<?php settings_errors( 'wporg-plugins-readme' ); ?>
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
		add_settings_field( 'readme_text', __( 'Text of readme.txt', 'wporg-plugins' ), array( $this, 'textarea' ),  'readme_validator', 'default', array(
			'label_for' => 'readme_contents',
		) );
	}

	/**
	 * Validates readme.txt contents and adds feedback.
	 */
	public function validate() {
		if ( ! isset( $_REQUEST['_wpnonce'] ) ) {
			return;
		}
		check_admin_referer( 'validate-readme' );

		$readme    = '';
		$temp_file = Filesystem::temp_directory() . '/readme.txt';
		$warnings  = array();
		$notes     = array();
		if ( ! empty( $_REQUEST['readme_url'] ) ) {
			$url = esc_url_raw( $_REQUEST['readme_url'] );

			if ( strtolower( substr( $url, -10 ) ) != 'readme.txt' ) {
				/* Translators: File name; */
				add_settings_error( 'wporg-plugins-readme', 'readme-validator', sprintf( __( 'URL must end in %s!', 'wporg-plugins' ), '<code>readme.txt</code>' ) );
				return;
			}

			if ( ! $readme = @file_get_contents( $url ) ) {
				add_settings_error( 'wporg-plugins-readme', 'readme-validator', __( 'Invalid readme.txt URL.', 'wporg-plugins' ) );
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
			/* Translators: Plugin header tag; */
			add_settings_error( 'wporg-plugins-readme', 'readme-validator', sprintf( __( "Fatal Error:\nNo plugin name detected. Plugin names look like: %s", 'wporg-plugins' ), '<code>=== Plugin Name ===</code>' ) );
			return;
		}

		// Warnings.
		if ( empty( $readme->requires_at_least ) ) {
			/* Translators: Plugin header tag; */
			$warnings[] = sprintf( __( '%s is missing.', 'wporg-plugins' ), '<code>Requires at least</code>' );
		}
		if ( empty( $readme->tested_up_to ) ) {
			/* Translators: Plugin header tag; */
			$warnings[] = sprintf( __( '%s is missing.', 'wporg-plugins' ), '<code>Tested up to</code>' );
		}
		if ( empty( $readme->stable_tag ) ) {
			/* Translators: 1: Plugin header tag; 2: SVN directory; 3: Plugin header tag; */
			$warnings[] = sprintf( __( '%1$s is missing.  Hint: If you treat %2$s as stable, put %3$s.', 'wporg-plugins' ), '<code>Stable tag</code>', '<code>/trunk/</code>', '<code>Stable tag: trunk</code>' );
		}
		if ( ! count( $readme->contributors ) ) {
			/* Translators: Plugin header tag; */
			$warnings[] = sprintf( __( 'No %s listed.', 'wporg-plugins' ), '<code>Contributors</code>' );
		}
		if ( ! count( $readme->tags ) ) {
			/* Translators: Plugin header tag; */
			$warnings[] = sprintf( __( 'No %s specified', 'wporg-plugins' ), '<code>Tags</code>' );
		}
		if ( ! empty( $readme->is_excerpt ) ) {
			/* Translators: Plugin header tag; */
			$warnings[] = sprintf( __( 'No %s section was found... your short description section will be used instead.', 'wporg-plugins' ), '<code>== Description ==</code>' );
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

			add_settings_error( 'wporg-plugins-readme', 'readme-validator', $message, 'notice-warning' );
			return;
		}

		// Notes.
		if ( empty( $readme->license ) ) {
			/* Translators: Plugin header tag; */
			$notes[] = sprintf( __( 'No %s is specified. WordPress is licensed under &#8220;GPLv2 or later&#8221;', 'wporg-plugins' ), '<code>License</code>' );
		}
		if ( empty( $readme->sections['installation'] ) ) {
			/* Translators: Plugin header tag; */
			$notes[] = sprintf( __( 'No %s section was found', 'wporg-plugins' ), '<code>== Installation ==</code>' );
		}
		if ( empty( $readme->sections['frequently_asked_questions'] ) ) {
			/* Translators: Plugin header tag; */
			$notes[] = sprintf( __( 'No %s section was found', 'wporg-plugins' ), '<code>== Frequently Asked Questions ==</code>' );
		}
		if ( empty( $readme->sections['changelog'] ) ) {
			/* Translators: Plugin header tag; */
			$notes[] = sprintf( __( 'No %s section was found', 'wporg-plugins' ), '<code>== Changelog ==</code>' );
		}
		if ( empty( $readme->upgrade_notice ) ) {
			/* Translators: Plugin header tag; */
			$notes[] = sprintf( __( 'No %s section was found', 'wporg-plugins' ), '<code>== Upgrade Notice ==</code>' );
		}
		if ( empty( $readme->sections['screenshots'] ) ) {
			/* Translators: Plugin header tag; */
			$notes[] = sprintf( __( 'No %s section was found', 'wporg-plugins' ), '<code>== Screenshots ==</code>' );
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

			add_settings_error( 'wporg-plugins-readme', 'readme-validator', $message, 'notice-info' );
			return;
		}

		/* Translators: File name; */
		add_settings_error( 'wporg-plugins-readme', 'readme-validator', sprintf( __( 'Your %s rocks.  Seriously.  Flying colors.', 'wporg-plugins' ), '<code>readme.txt</code>' ), 'updated' );
	}

	/**
	 * Help text for the form following after it.
	 */
	public function section_description() {
		/* Translators: File name; */
		echo '<p>' . sprintf( __( 'Enter the URL to your %s file or paste its content below.' ), '<code>readme.txt</code>' ) . '</p>';
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
		$text = empty( $_REQUEST['readme_contents'] ) ? '' : wp_unslash( $_REQUEST['readme_contents'] );
		?>
		<label>
			<textarea type="text" id="readme_contents" class="large-text" name="readme_contents" cols="50" rows="10"><?php echo esc_textarea( $text ); ?></textarea>
		</label>
		<?php
	}
}
