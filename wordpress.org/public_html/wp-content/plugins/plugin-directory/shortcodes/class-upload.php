<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;

class Upload {

	/**
	 * Retrieves plugins in the queue submitted by the current user.
	 *
	 * @return array An array of user's plugins.
	 */
	public static function get_submitted_plugins() {
		$plugins = get_posts( array(
			'post_type'      => 'plugin',
			'post_status'    => array( 'new', 'pending' ),
			'author'         => get_current_user_id(),
			'orderby'        => 'title',
			'order'          => 'ASC',
			'posts_per_page' => -1,
		) );

		return $plugins;
	}

	/**
	 * Renders the upload shortcode.
	 */
	public static function display() {
		ob_start();

		if ( is_user_logged_in() ) :
			include_once ABSPATH . 'wp-admin/includes/template.php';

			$submitted_plugins = self::get_submitted_plugins();
			$submitted_counts  = (object) array_fill_keys( array( 'new', 'pending' ), 0 );

			$submitted_counts->total = count( $submitted_plugins );

			foreach ( $submitted_plugins as $key => $plugin ) {
				if ( 'new' === $plugin->post_status ) {
					$submitted_plugins[ $key ]->status = __( 'Awaiting Review', 'wporg-plugins' );
					$submitted_counts->new++;
				} elseif ( 'pending' === $plugin->post_status ) {
					$submitted_plugins[ $key ]->status = __( 'Being Reviewed', 'wporg-plugins' );
					$submitted_counts->pending++;
				}
			}

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
					<p><?php
						if ( 1 === ( $plugins->new + $plugins->pending ) ) {
							 _e( 'Currently there is 1 plugin in the review queue.', 'wporg-plugins' );
						} else {
							printf(
								_n(
									'Currently there are %1$s plugins in the review queue, %2$s of which is awaiting its initial review.',
									'Currently there are %1$s plugins in the review queue, %2$s of which are awaiting their initial review.',
									$plugins->new,
									'wporg-plugins'
								),
								'<strong>' . ( $plugins->new + $plugins->pending ) . '</strong>',
								'<strong>' . $plugins->new . '</strong>'
							);
						}
					?></p>
				</div>

				<?php if ( $submitted_counts->total ) : ?>

					<div class="plugin-queue-message notice notice-warning notice-alt">
						<p><?php
							if ( 1 === $submitted_counts->total ) {
								_e( 'You have 1 plugin in the review queue. Please wait for it to be approved before submitting any more.', 'wporg-plugins' );
							} else {
								printf(
									_n(
										'You have %1$s plugins in the review queue, %2$s is being actively reviewed. Please wait for them to be approved before submitting any more.',
										'You have %1$s plugins in the review queue, %2$s are being actively reviewed. Please wait for them to be approved before submitting any more.',
										$submitted_counts->pending,
										'wporg-plugins'
									),
									'<strong>' . $submitted_counts->total . '</strong>',
									'<strong>' . $submitted_counts->pending . '</strong>'
								);
							}
						?></p>

						<ul><?php
							foreach ( $submitted_plugins as $plugin ) {
								echo '<li>' . esc_html( $plugin->post_title ) . ' &#8212; ' . $plugin->status . "</li>\n";
							}
						?></ul>

						<p><?php
							/* translators: %s: plugins@wordpress.org */
							printf( __( 'Please wait at least 7 business days before asking for an update status from <a href="mailto:%1$s">%1$s</a>.', 'wporg-plugins' ),
								'plugins@wordpress.org'
							);
						?></p>
					</div>

				<?php endif; // $submitted_counts->total ?>

			<?php endif; // wp_verify_nonce() && 'upload' === $_POST['action'] ?>

			<?php if ( ! $submitted_counts->total ) : ?>

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

			<?php endif; // ! $submitted_counts->total ?>

		<?php else : ?>

			<p><?php printf( __( 'Before you can upload a new plugin, <a href="%s">please log in</a>.', 'wporg-plugins' ), esc_url( 'https://login.wordpress.org/' ) ); ?></p>

		<?php endif; // is_user_logged_in()

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
