<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;

class Upload {

	/**
	 * Renders the upload shortcode.
	 */
	public static function display() {
		ob_start();

		if ( is_user_logged_in() ) :

			if ( ! empty( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'wporg-plugins-upload' ) && 'upload' === $_POST['action'] ) :
				if ( UPLOAD_ERR_OK === $_FILES['zip_file']['error'] ) :
					$uploader = new Upload_Handler;
					$message  = $uploader->process_upload();
				else :
					$message = __( 'Error in file upload.', 'wporg-plugins' );
				endif;

				if ( ! empty( $message ) ) :
					echo "<div class='notice notice-warning notice-alt'><p>{$message}</p></div>\n";
				endif;

			else :
				$plugins = wp_count_posts( 'plugin', 'readable' );
				?>

				<div class="plugin-queue-message notice notice-info notice-alt">
					<p>
						<?php
						printf( _n( 'Currently there is %1$s plugin in the review queue.', 'Currently there are %1$s plugins in the review queue, %2$s of which are awaiting their initial review.', ( $plugins->draft + $plugins->pending ), 'wporg-plugins' ),
							'<strong>' . ( $plugins->draft + $plugins->pending ) . '</strong>',
							'<strong>' . $plugins->draft . '</strong>'
						);
						?>
					</p>
				</div>

			<?php endif; ?>

			<form enctype="multipart/form-data" id="upload_form" method="POST" action="">
				<?php wp_nonce_field( 'wporg-plugins-upload' ); ?>
				<input type="hidden" name="action" value="upload"/>
				<input type="file" id="zip_file" name="zip_file" size="25"/>
				<input id="upload_button" class="button" type="submit" value="<?php esc_attr_e( 'Upload', 'wporg-plugins' ); ?>"/>

				<p>
					<small><?php printf( __( 'Maximum allowed file size: %s', 'wporg-plugins' ), esc_html( self::get_max_allowed_file_size() ) ); ?></small>
				</p>
			</form>

		<?php else : ?>

			<p><?php printf( __( 'Before you can upload a new plugin, <a href="%s">please log in</a>.', 'wporg-plugins' ), esc_url( 'https://login.wordpress.org/' ) ); ?></p>

		<?php endif;

		return ob_get_clean();
	}

	/**
	 * Returns a human readable version of the max allowed upload size.
	 *
	 * @return string The allowed file size.
	 */
	public static function get_max_allowed_file_size() {
		$upload_size_unit = wp_max_upload_size();
		$byte_sizes       = array( 'KB', 'MB', 'GB' );

		for ( $unit = - 1; $upload_size_unit > 1024 && $unit < count( $byte_sizes ) - 1; $unit ++ ) {
			$upload_size_unit /= 1024;
		}

		if ( $unit < 0 ) {
			$upload_size_unit = $unit = 0;
		} else {
			$upload_size_unit = (int) $upload_size_unit;
		}

		return $upload_size_unit . $byte_sizes[ $unit ];
	}
}
