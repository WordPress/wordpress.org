<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;

class Upload {

	/**
	 * Retrieves plugins in the queue submitted by the current user.
	 *
	 * @return array An array of user's plugins, and counts of it.
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

		$counts        = (object) array_fill_keys( array( 'new', 'pending', 'approved' ), 0 );
		$counts->total = count( $plugins );

		// Set the status text for each type.
		foreach ( $plugins as &$plugin ) {
			$counts->{ $plugin->post_status }++;

			if ( 'new' === $plugin->post_status ) {
				$plugin->status = __( 'Awaiting Review &#8212; This plugin has not yet been reviewed.', 'wporg-plugins' );
			} elseif ( 'pending' === $plugin->post_status ) {
				$plugin->status = __( 'Being Reviewed &#8212; This plugin is currently waiting on action from you. Please check your email for details.', 'wporg-plugins' );
			} elseif ( 'approved' === $plugin->post_status ) {
				$plugin->status = __( 'Approved &#8212; Please check your email for instructions on uploading your plugin.', 'wporg-plugins' );
			}
		}

		return [ $plugins, $counts ];
	}

	/**
	 * Renders the upload shortcode.
	 */
	public static function display() {
		if ( ! is_user_logged_in() ) {
			return '<div class="notice notice-error notice-alt"><p>' . sprintf(
				/* translators: Login URL */
				__( 'Before you can upload a new plugin, <a href="%s">please log in</a>.', 'wporg-plugins' ),
				esc_url( wp_login_url() )
			) . '</p></div>';
		}

		ob_start();

		$uploader = new Upload_Handler();

		include_once ABSPATH . 'wp-admin/includes/template.php';

		list( $submitted_plugins, $submitted_counts ) = self::get_submitted_plugins();

		$upload_result = false;

		if (
			! empty( $_POST['_wpnonce'] )
			&& wp_verify_nonce( $_POST['_wpnonce'], 'wporg-plugins-upload' )
			&& 'upload' === $_POST['action']
			&& ! $submitted_counts->total
			&& ! empty( $_FILES['zip_file'] )
		) {
			$upload_result = $uploader->process_upload();

			if ( is_wp_error( $upload_result ) ) {
				$message = $upload_result->get_error_message();
			} else {
				$message = $upload_result;
			}

			// Refresh the lists.
			list( $submitted_plugins, $submitted_counts ) = self::get_submitted_plugins();

			if ( ! empty( $message ) ) {
				echo "<div class='notice notice-warning notice-alt'><p>{$message}</p></div>\n";
			}
		}

		if ( ! is_wp_error( $upload_result ) ) :
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
				} else {
					echo '</p><p>';
					printf(
						/* translators: plugins@wordpress.org */
						__( 'Please wait at least 7 business days before asking for an update status from <a href="mailto:%1$s">%1$s</a>.', 'wporg-plugins' ),
						'plugins@wordpress.org'
					);
					echo '</p>';
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
						$can_change_slug = ( 'new' === $plugin->post_status && ! $plugin->{'_wporg_plugin_original_slug'} );

						echo '<li>';
							echo '<strong>' . esc_html( $plugin->post_title ) . '</strong>';
							echo '<ul>';
							printf(
								'<li>%s</li>',
								sprintf(
									__( 'Review status: %s', 'wporg-plugins' ),
									$plugin->status
								)
							);
							echo '<li>';
							printf(
								__( 'Current assigned slug: %s', 'wporg-plugins' ),
								'<code>' . esc_html( $plugin->post_name ) . '</code>'
							);
							?>
							<?php if ( $can_change_slug ) : ?>
								<a href="#" class="hide-if-no-js" onclick="this.nextElementSibling.showModal()"><?php _e( 'change', 'wporg-plugins' ); ?></a>
								<dialog class="slug-change hide-if-no-js">
									<a onclick="this.parentNode.close()" class="close dashicons dashicons-no-alt"></a>
									<strong><?php _e( 'Request to change your plugin slug', 'wporg-plugins' ); ?></strong>
									<form>
										<input type="hidden" name="action" value="request-slug-change" />
										<input type="hidden" name="id" value="<?php echo esc_attr( $plugin->ID ); ?>" />

										<div class="notice notice-info notice-alt">
											<p><?php _e( 'Your chosen slug cannot be guaranteed, and is subject to change based on the results of your review.', 'wporg-plugins' ); ?></p>
											<p><?php
												printf(
													/* Translators: URL */
													__( "Your slug is used to generate your plugins URL. Currently it's %s", 'wporg-plugins' ),
													'<code>' . esc_url( home_url( $plugin->post_name ) . '/' ) . '</code>'
												);
											?></p>
											<p><?php _e( 'Your slug (aka permalink) cannot be changed once your review is completed. Please choose carefully.', 'wporg-plugins' ); ?></p>
										</div>
										<div class="notice notice-error notice-alt hidden"><p></p></div>
										<p>
											<label>
												<strong><?php _e( 'Plugin Name', 'wporg-plugins' ); ?></strong><br>
												<?php echo esc_html( $plugin->post_title ); ?>
											</label>
										</p>
										<p>
											<label>
												<strong><?php _e( 'Desired Slug', 'wporg-plugins' ); ?></strong><br>
												<input type="text" name="post_name" required maxlength="200" pattern="[a-z0-9-]*" value="<?php echo esc_attr( $plugin->post_name ); ?>" />
											</label>
										</p>

										<p>
											<label>
												<input type="checkbox" name="confirm" required />
												<?php
													printf(
														/* Translators: URL to plugin guidelines */
														__( 'I confirm that my slug choice <a href="%s">meets the guidelines for plugin slugs</a>.', 'wporg-plugins' ),
														'https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#17-plugins-must-respect-trademarks-copyrights-and-project-names'
													);
												?>
											</label>
										</p>
										<p>
											<input class="button button-primary" type="submit" value="<?php
												/* translators: Request slug-change button */
												esc_attr_e( 'Request', 'wporg-plugins' );
											?>" />
										</p>
									</form>
								</dialog>
							<?php
							endif; // $can_change_slug
							echo '</li>';
							echo '</ul>';
						
						echo "</li>\n";
					}
					?>
					</ul>
				</div>

			<?php endif; // $submitted_counts->total ?>

		<?php endif; // ! is_wp_error( $upload_result )

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

		<?php endif; // ! $submitted_counts->total

		return ob_get_clean();
	}

}
