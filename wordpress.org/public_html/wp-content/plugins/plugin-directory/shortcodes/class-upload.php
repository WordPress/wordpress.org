<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;

class Upload {

	/**
	 * Renders the upload shortcode.
	 */
	public static function display() {
		ob_start();

		if ( is_user_logged_in() ) :
			include_once ABSPATH . 'wp-admin/includes/template.php';

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
				<?php
				if ( '/plugins-wp' === parse_url( home_url(), PHP_URL_PATH ) ) {
					echo '<div class="notice notice-error notice-alt">
						<p>Please submit all new plugin requests through the existing form <a href="https://wordpress.org/plugins/add/">available here</a>, You\'re currently viewing the beta version of the upcoming plugin directory, and this form is only for testing purposes.</p>
					</div>';
				}
				?>

			<?php endif; ?>

			<form id="upload_form" class="plugin-upload-form" enctype="multipart/form-data" method="POST" action="">
				<?php wp_nonce_field( 'wporg-plugins-upload' ); ?>
				<input type="hidden" name="action" value="upload"/>
				<?php /* <fieldset>
					<legend><?php _e( 'Select categories (up to 3)', 'wporg-plugins' ); ?></legend>
					<ul class="category-checklist">
						<?php wp_terms_checklist( 0, array( 'taxonomy' => 'plugin_category' ) ); ?>
					</ul>
				</fieldset> */ ?>

				<input type="file" id="zip_file" class="plugin-file" name="zip_file" size="25" accept=".zip"/>
				<label class="button button-secondary" for="zip_file"><?php _e( 'Select File', 'wporg-plugins' ); ?></label>

				<input id="upload_button" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Upload', 'wporg-plugins' ); ?>"/>

				<p>
					<small><?php printf( __( 'Maximum allowed file size: %s', 'wporg-plugins' ), esc_html( self::get_max_allowed_file_size() ) ); ?></small>
				</p>
			</form>
			<script>
				( function ( $ ) {
					var $label    = $( 'label.button' ),
						labelText = $label.text();

					$( '#zip_file' )
						.on( 'change', function( event ) {
							var fileName = event.target.value.split( '\\' ).pop();

							fileName ? $label.text( fileName ) : $label.text( labelText );
						} )
						.on( 'focus', function() { $label.addClass( 'focus' ); } )
						.on( 'blur', function() { $label.removeClass( 'focus' ); } );
				} ( window.jQuery ) );
			</script>

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
