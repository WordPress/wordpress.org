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
			'post_status'    => array( 'new', 'pending', 'approved' ),
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

		$uploader = new Upload_Handler();

		if ( is_user_logged_in() ) :
			include_once ABSPATH . 'wp-admin/includes/template.php';

			$submitted_plugins = self::get_submitted_plugins();
			$submitted_counts  = (object) array_fill_keys( array( 'new', 'pending', 'approved' ), 0 );

			$submitted_counts->total = count( $submitted_plugins );

			foreach ( $submitted_plugins as $key => $plugin ) {
				if ( 'new' === $plugin->post_status ) {
					$submitted_plugins[ $key ]->status = __( 'Awaiting Review', 'wporg-plugins' );
					$submitted_counts->new++;
				} elseif ( 'pending' === $plugin->post_status ) {
					$submitted_plugins[ $key ]->status = __( 'Being Reviewed', 'wporg-plugins' );
					$submitted_counts->pending++;
				} elseif ( 'approved' === $plugin->post_status ) {
					$submitted_plugins[ $key ]->status = __( 'Approved', 'wporg-plugins' );
					$submitted_counts->approved++;
				}
			}

			$upload_result = false;

			if (
				! empty( $_POST['_wpnonce'] )
				&& wp_verify_nonce( $_POST['_wpnonce'], 'wporg-plugins-upload' )
				&& 'upload' === $_POST['action']
				&& ! $submitted_counts->total
			) :
				if ( UPLOAD_ERR_OK === $_FILES['zip_file']['error'] ) :
					$upload_result = $uploader->process_upload();

					if ( is_wp_error( $upload_result ) ) {
						$message = $upload_result->get_error_message();
					} else {
						$message = $upload_result;
					}
				else :
					$message = __( 'Error in file upload.', 'wporg-plugins' );
				endif;

				if ( ! empty( $message ) ) :
					echo "<div class='notice notice-warning notice-alt'><p>{$message}</p></div>\n";
				endif;

			else :
				$plugins       = wp_count_posts( 'plugin', 'readable' );
				$oldest_plugin = get_posts( [ 'post_type' => 'plugin', 'post_status' => 'new', 'order' => 'ASC', 'orderby' => 'post_date_gmt', 'numberposts' => 1 ] );
				$queue_length  = floor( ( time() - strtotime( $oldest_plugin[0]->post_date_gmt ?? 'now' ) ) / DAY_IN_SECONDS );
				?>

				<div class="plugin-queue-message notice notice-info notice-alt">
					<p>
					<?php
					if ( 1 === (int) $plugins->new ) {
						esc_html_e( 'Currently there is 1 plugin awaiting review.', 'wporg-plugins' );
					} else {
						printf(
							/* translators: %s: Amount of plugins awaiting review. */
							esc_html( _n(
								'Currently there is %s plugin awaiting review.',
								'Currently there are %s plugins awaiting review.',
								$plugins->new,
								'wporg-plugins'
							) ),
							'<strong>' . number_format_i18n( $plugins->new ) . '</strong>'
						);
					}

					// If the queue is currently beyond 10 days, display a warning to that effect.
					if ( $queue_length > 10 ) {
						echo '</p><p>';
						esc_html_e( 'The review queue is currently longer than normal, we apologize for the delays and ask for patience.', 'wporg-plugins' );

						echo '</p><p>';
						printf(
							/* translators: %s: Number of days. Only displayed if > 10 */
							esc_html( _n(
								'The current wait for an initial review is at least %s day.',
								'The current wait for an initial review is at least %s days.',
								$queue_length,
								'wporg-plugins'
							) ),
							'<strong>' . number_format_i18n( $queue_length ) . '</strong>'
						);
					}
					?>
					</p>
				</div>

				<?php if ( $submitted_counts->total ) : ?>

					<div class="plugin-queue-message notice notice-warning notice-alt">
						<p>
						<?php
						if ( 0 !== $submitted_counts->approved ) {
							printf(
								/* translators: 1: Amount of approved plugins; 2: URL on how to use SVN */
								wp_kses_post( _n(
									'You have %1$s approved plugin that has not yet been used. We require developers to use the hosting we provide. Please upload your plugin via <a href="%2$s">SVN</a>.',
									'You have %1$s approved plugins that have not yet been used. We require developers to use the hosting we provide. Please upload your plugins via <a href="%2$s">SVN</a>.',
									$submitted_counts->approved,
									'wporg-plugins'
								) ),
								'<strong>' . $submitted_counts->approved . '</strong>',
								'https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/'
							);
						} elseif ( 0 !== $submitted_counts->pending ) {
							printf(
								/* translators: %s: Amount of pending plugins. */
								esc_html( _n(
									'You have %s plugin being actively reviewed and have been sent an email regarding issues. You must complete this review before you can submit another plugin. Please reply to that email with your corrected code attached or linked in order to proceed with the review.',
									'You have %s plugins being actively reviewed and have been sent emails regarding issues. You must complete their reviews before you can submit another plugin. Please reply to the emails with your corrected code attached or linked in order to proceed with each review.',
									$submitted_counts->pending,
									'wporg-plugins'
								) ),
								'<strong>' . $submitted_counts->pending . '</strong>'
							);
						} elseif ( 0 !== $submitted_counts->new ) {
							printf(
								/* translators: %s: Amount of new plugins. */
								esc_html( _n(
									'You have %s plugin that has been recently submitted but not yet reviewed. Please wait for your plugin to be reviewed and approved before submitting another.',
									'You have %s plugins already submitted but not yet reviewed. Please wait for them to be reviewed and approved before submitting another plugin.',
									$submitted_counts->new,
									'wporg-plugins'
								) ),
								'<strong>' . $submitted_counts->new . '</strong>'
							);
						}
						?>
						</p>

						<ul>
						<?php
						// List of all plugins in progress.
						foreach ( $submitted_plugins as $plugin ) {
							echo '<li>' . esc_html( $plugin->post_title ) . ' &#8212; ' . $plugin->status . "</li>\n";
						}
						?>
						</ul>

						<p>
						<?php
							printf(
								/* translators: plugins@wordpress.org */
								__( 'Please wait at least 7 business days before asking for an update status from <a href="mailto:%1$s">%1$s</a>.', 'wporg-plugins' ),
								'plugins@wordpress.org'
							);
						?>
						</p>
					</div>

				<?php endif; // $submitted_counts->total ?>

			<?php endif; // wp_verify_nonce() && 'upload' === $_POST['action'] ?>

			<?php
			if ( is_email_address_unsafe( wp_get_current_user()->user_email ) ) {
				echo '<div class="notice notice-error notice-alt"><p>' .
					sprintf(
						/* translators: %s: Profile edit url. */
						__( 'Your email host has email deliverability problems. Please <a href="%s">Update your email address</a> first.', 'wporg-plugins'),
						esc_url( 'https://wordpress.org/support/users/' . wp_get_current_user()->user_nicename . '/edit' )
					 ) .
					 "</p></div>\n";

			} else if ( ! $submitted_counts->total && ( ! $upload_result || is_wp_error( $upload_result ) ) ) : ?>

				<form id="upload_form" class="plugin-upload-form" enctype="multipart/form-data" method="POST" action="">
					<?php wp_nonce_field( 'wporg-plugins-upload' ); ?>
					<input type="hidden" name="action" value="upload"/>
					<?php
					if ( ! empty( $_REQUEST['upload_token'] ) ) {
						printf(
							'<input type="hidden" name="upload_token" value="%s"/>',
							esc_attr( $_REQUEST['upload_token'] )
						);

						if ( ! $uploader->has_valid_upload_token() ) {
							printf(
								'<div class="notice notice-error notice-alt"><p>%s</p></div>',
								esc_html__( 'The token provided is invalid for this user.', 'wporg-plugins')
							);
						}
					}
					?>
					<?php
					/*
					<fieldset>
						<legend><?php _e( 'Select categories (up to 3)', 'wporg-plugins' ); ?></legend>
						<ul class="category-checklist">
							<?php wp_terms_checklist( 0, array( 'taxonomy' => 'plugin_category' ) ); ?>
						</ul>
					</fieldset> */
?>

					<input type="file" id="zip_file" class="plugin-file" name="zip_file" size="25" accept=".zip"/>
					<label class="button button-secondary" for="zip_file"><?php _e( 'Select File', 'wporg-plugins' ); ?></label>

					<p>
						<small>
							<?php
							printf(
								/* translators: Maximum allowed file size. */
								esc_html__( 'Maximum allowed file size: %s', 'wporg-plugins' ),
								esc_html( size_format( wp_max_upload_size() ) )
							);
							?>
						</small>
					</p>

					<p>
						<label>
							<input type="checkbox" name="requirements[faq]" required="required">
							<?php
								printf(
									__( 'I have read the <a href="%s">Frequently Asked Questions</a>.', 'wporg-plugins' ),
									'https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/'
								);
							?>
						</label>
						<br>
						<label>
							<input type="checkbox" name="requirements[guidelines]" required="required">
							<?php
								printf(
									__( 'This plugin complies with all of the <a href="%s">Plugin Developer Guidelines</a>.', 'wporg-plugins' ),
									'https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/'
								);
							?>
						</label>
						<br>
						<label>
							<input type="checkbox" name="requirements[author]" required="required">
							<?php _e( 'I have permission to upload this plugin to WordPress.org for others to use and share.', 'wporg-plugins' ); ?>
						</label>
						<br>
						<label>
							<input type="checkbox" name="requirements[license]" required="required">
							<?php _e( 'This plugin, all included libraries, and any other included assets are licenced as GPL or are under a GPL compatible license.', 'wporg-plugins' ); ?>
						</label>
					</p>

					<input id="upload_button" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Upload', 'wporg-plugins' ); ?>"/>
				</form>

				<?php
				$upload_script = '
					( function ( $ ) {
						var $label = $( "label.button" ),
							labelText = $label.text();
						$( "#zip_file" )
							.on( "change", function( event ) {
								var fileName = event.target.value.split( "\\\\" ).pop();
								fileName ? $label.text( fileName ) : $label.text( labelText );
							} )
							.on( "focus", function() { $label.addClass( "focus" ); } )
							.on( "blur", function() { $label.removeClass( "focus" ); } );
					} ( window.jQuery ) );';

				if ( ! wp_script_is( 'jquery', 'done' ) ) {
					wp_enqueue_script( 'jquery' );
					wp_add_inline_script( 'jquery-migrate', $upload_script );
				} else {
					printf( '<script>%s</script>', $upload_script );
				}
				?>

			<?php endif; // ! $submitted_counts->total ?>

		<?php else : ?>

			<p>
			<?php
			printf(
				/* translators: Login URL */
				__( 'Before you can upload a new plugin, <a href="%s">please log in</a>.', 'wporg-plugins' ),
				esc_url( wp_login_url() )
			);
			?>
			</p>

		<?php
		endif; // is_user_logged_in()

		return ob_get_clean();
	}

}
