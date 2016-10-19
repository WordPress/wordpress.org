<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;
use WordPressdotorg\Plugin_Directory\Readme\Validator;

class Readme_Validator {

	private $errors = array();

	/**
	 * Fetch the instance of the Readme_Validator class.
	 *
	 * @static
	 */
	public static function instance() {
		static $instance = null;

		return ! is_null( $instance ) ? $instance : $instance = new Readme_Validator();
	}

	private function __construct() {
	}

	private function display_errors( $errors ) {
		if ( is_array( $errors ) && !empty( $errors ) ) {
			$message = __( 'Warnings:', 'wporg-plugins' ) . "\n<ul class='warning error'>\n";
			foreach ( $errors as $error ) {
				$message .= "<li>" . esc_html($error) . "</li>\n";
			}
			$message .= "</ul>\n</div>";

			echo $message;
		}

	}

	/**
	 * Displays a form to validate readme.txt files and blobs of text.
	 */
	public function display() {

		$message = null;
		if ( !empty( $_POST ) ) {
			$message = self::validate();
		}

		?>
		<div class="wrap">
			<h2><?php _e( 'WordPress Plugin readme.txt Validator', 'wporg-plugins' ); ?></h2>
			<?php if ( $message ) echo $message; ?>
			<form method="post" action="<?php echo esc_url( add_query_arg( array() ) ); ?>">
				<input type="hidden" name="url" value="1" />
				<p>http://<input type="text" name="readme_url" size="70" /> <input type="submit" value="Validate!" /></p>
				<?php
				wp_nonce_field( 'validate-readme-url' );
				?>
			</form>
			<p><?php _e( '... or paste your <code>readme.txt</code> here:', 'wporg-plugins' ); ?></p>
			<form method="post" action="<?php echo esc_url( add_query_arg( array() ) ); ?>">
				<input type="hidden" name="text" value="1" />
				<textarea rows="20" cols="100" name="readme_contents"></textarea>
				<p><input type="submit" value="Validate!" /></p>
				<?php
				wp_nonce_field( 'validate-readme-text' );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Validates readme.txt contents and adds feedback.
	 */
	public function validate() {

		$readme    = '';

		if ( !empty( $_POST['url'] ) 
			&& !empty( $_POST['readme_url'] ) 
			&& !empty( $_POST['_wpnonce'] ) 
			&& wp_verify_nonce( $_POST['_wpnonce'], 'validate-readme-url' ) ) {
				$url = esc_url_raw( $_POST['readme_url'] );

				if ( strtolower( substr( $url, -10 ) ) != 'readme.txt' ) {
					/* Translators: File name; */
					self::instance()->errors[] = sprintf( __( 'URL must end in %s!', 'wporg-plugins' ), '<code>readme.txt</code>' );
					return;
				}

				if ( ! $readme = @file_get_contents( $url ) ) {
					self::instance()->errors[] = __( 'Invalid readme.txt URL.', 'wporg-plugins' );
					return;
				}
			}
		elseif ( !empty( $_POST['text'] ) 
			&& !empty( $_POST['readme_contents'] ) 
			&& !empty( $_POST['_wpnonce'] ) 
			&& wp_verify_nonce( $_POST['_wpnonce'], 'validate-readme-text' ) ) {
				$readme = wp_unslash( $_REQUEST['readme_contents'] );
			}

		if ( empty( $readme ) ) {
			return;
		}

		return Validator::instance()->validate_content( $readme );

	}
}
