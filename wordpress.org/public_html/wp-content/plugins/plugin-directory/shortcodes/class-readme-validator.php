<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;
use WordPressdotorg\Plugin_Directory\Readme\Validator;

class Readme_Validator {

	/**
	 * Displays a form to validate readme.txt files and blobs of text.
	 */
	public static function display() {
		?>
		<div class="wrap">
			<h2><?php _e( 'WordPress Plugin readme.txt Validator', 'wporg-plugins' ); ?></h2>

			<?php
			if ( $_POST ) {
				self::validate_readme();
			}
			?>

			<form method="post" action="">
				<p>
					<input type="text" name="readme_url" size="70" placeholder="https://" value="<?php if ( isset( $_POST['readme_url'] ) ) { echo esc_attr( $_POST['readme_url'] ); } ?>" />
					<input type="submit" value="<?php esc_attr_e( 'Validate!', 'wporg-plugins' ); ?>" />
				</p>
			</form>

			<p><?php _e( '... or paste your <code>readme.txt</code> here:', 'wporg-plugins' ); ?></p>
			<form method="post" action="">
				<textarea rows="20" cols="100" name="readme_contents" placeholder="=== Plugin Name ==="><?php
					if ( isset( $_POST['readme_contents'] ) ) {
						echo esc_textarea( wp_unslash( $_POST['readme_contents'] ) );
					}
				?></textarea>
				<p><input type="submit" value="<?php esc_attr_e( 'Validate!', 'wporg-plugins' ); ?>" /></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Validates readme.txt contents and adds feedback.
	 */
	protected static function validate_readme() {
		if (  !empty( $_POST['readme_url'] ) ) {
			$errors = Validator::instance()->validate_url( wp_unslash( $_POST['readme_url'] ) );

		} elseif ( !empty( $_POST['readme_contents'] ) ) {
			$errors = Validator::instance()->validate_content( wp_unslash( $_REQUEST['readme_contents'] ) );

		} else {
			return;
		}

		$error_types = array(
			'errors'   => __( 'Fatal Errors:', 'wporg-plugins' ),
			'warnings' => __( 'Warnings:', 'wporg-plugins' ),
			'notes'    => __( 'Notes:', 'wporg-plugins' )
		);
		foreach ( $error_types as $field => $warning_label ) {
			if ( !empty( $errors[ $field ] ) ) {
				echo "{$warning_label}\n<ul class='{$field} error'>\n";
				foreach ( $errors[ $field ] as $notice ) {
					echo "<li>{$notice}</li>\n";
				}
				echo "</ul>\n";
			}
		}
	}
}
