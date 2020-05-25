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
			<?php
			if ( $_POST ) {
				self::validate_readme();
			}

			$readme_url      = $_POST['readme_url'] ?? '';
			$readme_contents = $_POST['readme_contents'] ?? '';
			$readme_contents = base64_decode( wp_unslash( $readme_contents ) );
			?>

			<form method="post" action="">
				<p>
					<input type="text" name="readme_url" size="70" placeholder="https://" value="<?php echo esc_attr( $readme_url ); ?>" />
					<input type="submit" class="button button-secondary" value="<?php esc_attr_e( 'Validate!', 'wporg-plugins' ); ?>" />
				</p>
			</form>

			<p><?php _e( '... or paste your <code>readme.txt</code> here:', 'wporg-plugins' ); ?></p>
				<textarea rows="20" cols="100" name="readme_visible" placeholder="=== Plugin Name ==="><?php echo esc_textarea( $readme_contents ); ?></textarea>
				<form id="readme-data" method="post" action="">
					<textarea class="screen-reader-text" rows="20" cols="100" name="readme_contents"><?php echo esc_textarea( $readme_contents ); ?></textarea>
				<p><input type="submit" class="button button-secondary" value="<?php esc_attr_e( 'Validate!', 'wporg-plugins' ); ?>" /></p>
			</form>
			<script>
				document.getElementById( 'readme-data' ).addEventListener( 'submit', function() {
					var readmeInputs = document.getElementsByTagName( 'textarea' );

					readmeInputs[1].value = window.btoa( encodeURIComponent( readmeInputs[0].value ).replace( /%([0-9A-F]{2})/g,
						function toSolidBytes( match, p1 ) {
							return String.fromCharCode( '0x' + p1 );
						})
					);

					return true;
				} );
			</script>
		</div>
		<?php
	}

	/**
	 * Validates readme.txt contents and adds feedback.
	 */
	protected static function validate_readme() {
		if ( ! empty( $_POST['readme_url'] ) ) {
			$errors = Validator::instance()->validate_url( wp_unslash( $_POST['readme_url'] ) );

		} elseif ( ! empty( $_POST['readme_contents'] ) ) {
			$errors = Validator::instance()->validate_content( base64_decode( wp_unslash( $_REQUEST['readme_contents'] ) ) );

		} else {
			return;
		}

		$output = '';

		$error_types = array(
			'errors'   => __( 'Fatal Errors:', 'wporg-plugins' ),
			'warnings' => __( 'Warnings:', 'wporg-plugins' ),
			'notes'    => __( 'Notes:', 'wporg-plugins' ),
		);
		foreach ( $error_types as $field => $warning_label ) {
			if ( ! empty( $errors[ $field ] ) ) {
				if ( 'errors' === $field ) {
					$class = 'error';
				} elseif ( 'warnings' === $field ) {
					$class = 'warning';
				} else {
					$class = 'info';
				}

				$output .= "<h3>{$warning_label}</h3>\n";
				$output .= "<div class='notice notice-{$class} notice-alt'>\n";
				$output .= "<ul class='{$field}'>\n";
				foreach ( $errors[ $field ] as $notice ) {
					$output .= "<li>{$notice}</li>\n";
				}
				$output .= "</ul>\n";
				$output .= "</div>\n";
			}
		}

		if ( empty( $output ) ) {
			$output .= '<div class="notice notice-success notice-alt">';
			$output .= '<p>' . __( 'Congratulations! No errors found.', 'wporg-plugins' ) . '</p>';
			$output .= '</div>';
		}

		echo $output;
	}
}
