<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Readme\Validator;

class Readme_Validator {

	/**
	 * Displays a form to validate readme.txt files and blobs of text.
	 */
	public static function display() {
		$readme_url      = '';
		$readme_contents = '';
		if ( ! empty( $_REQUEST['readme'] ) && is_string( $_REQUEST['readme'] ) ) {
			$readme_url = wp_unslash( $_REQUEST['readme'] );

			// If it's a slug..
			if ( $readme_url === sanitize_title_with_dashes( $readme_url ) ) {
				$readme_url = 'https://wordpress.org/plugins/' . $readme_url . '/';
			}
		}

		if ( ! empty( $_POST['readme_contents'] ) && is_string( $_POST['readme_contents'] ) ) {
			$readme_contents = base64_decode( wp_unslash( $_POST['readme_contents'] ), true );
		}

		// If the user has specified a plugin URL, validate the stable tags readme (Well, try to, we don't know it's exact filename).
		if ( $readme_url && preg_match( '!^https?://([^./]+\.)?wordpress.org/plugins/(?P<slug>[^/]+)!i', $readme_url, $m ) ) {
			$plugin = Plugin_Directory::get_plugin_post( $m['slug'] );

			if ( $plugin ) {
				$readme_url         = 'https://plugins.svn.wordpress.org/' . $plugin->post_name . '/' . ( ( $plugin->stable_tag && 'trunk' != $plugin->stable_tag ) ? 'tags/' . $plugin->stable_tag : 'trunk' ) . '/readme.txt';
				$_REQUEST['readme'] = $readme_url;
			}
		}

		ob_start();
		?>
		<div class="wrap">
			<?php
			if ( $readme_contents || $readme_url ) {
				self::validate_readme( $readme_contents ?: $readme_url );

				$readme_contents = Validator::instance()->last_content ?: $readme_contents;
			}
			?>

			<form method="get" action="">
				<p>
					<input type="text" name="readme" size="70" placeholder="https://" value="<?php echo esc_attr( $readme_url ); ?>" />
					<input type="submit" class="button button-secondary" value="<?php esc_attr_e( 'Validate!', 'wporg-plugins' ); ?>" />
				</p>
			</form>

			<p><?php _e( '... or paste your <code>readme.txt</code> here:', 'wporg-plugins' ); ?></p>
				<textarea rows="20" cols="100" name="readme_visible" placeholder="=== Plugin Name ==="><?php echo esc_textarea( $readme_contents ); ?></textarea>
				<form id="readme-data" method="post" action="">
					<input type="hidden" name="readme" value="" />
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
		return ob_get_clean();
	}

	/**
	 * Validates readme.txt contents and adds feedback.
	 *
	 * @param string $readme_url_or_contents URL or contents of the readme.txt file.
	 * @return void
	 */
	protected static function validate_readme( $readme_url_or_contents = '' ) {

		if ( str_starts_with( $readme_url_or_contents, 'http://' ) || str_starts_with( $readme_url_or_contents, 'https://' ) ) {
			$errors = Validator::instance()->validate_url( $readme_url_or_contents );

		} elseif ( $readme_url_or_contents ) {
			$errors = Validator::instance()->validate_content( $readme_url_or_contents );

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
