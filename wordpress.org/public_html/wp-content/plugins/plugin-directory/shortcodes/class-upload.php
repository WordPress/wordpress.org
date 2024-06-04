<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;
use WordPressdotorg\Plugin_Directory\Template;

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
				$plugin->status       = __( 'Being Reviewed &#8212; This plugin is currently waiting on action from you. Please check your email for details.', 'wporg-plugins' );
				$plugin->review_email = Upload_Handler::find_review_email( $plugin );

				if ( $plugin->review_email && 'closed' !== $plugin->review_email->status ) {
					$plugin->status = __( "Being Reviewed &#8212; We've got your email. This plugin is currently waiting on action from our review team.", 'wporg-plugins' );
				}
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

		include_once ABSPATH . 'wp-admin/includes/template.php';

		$uploader      = new Upload_Handler();
		$upload_result = false;

		/*
		 * Determine the maximum number of plugins a user can have in the queue.
		 *
		 * Plugin owners with more than 1m active installs can have up to 10 plugins in the queue.
		 *
		 * @see https://meta.trac.wordpress.org/ticket/76641
		 */
		$maximum_plugins_in_queue = 1;
		$user_active_installs     = array_sum(
			wp_list_pluck(
				get_posts( [
					'author'      => get_current_user_id(),
					'post_type'   => 'plugin',
					'post_status' => 'publish', // Only count published plugins.
					'numberposts' => -1
				] ),
				'_active_installs'
			)
		);
		if ( $user_active_installs > 1000000 /* 1m+ */ ) {
			$maximum_plugins_in_queue = 10;
		}

		list( $submitted_plugins, $submitted_counts ) = self::get_submitted_plugins();
		$can_submit_new_plugin                        = $submitted_counts->total < $maximum_plugins_in_queue;

		if (
			! empty( $_POST['_wpnonce'] ) &&
			! empty( $_FILES['zip_file'] ) &&
			(
				// New submission.
				empty( $_POST['plugin_id'] ) &&
				'upload' === $_POST['action'] &&
				$can_submit_new_plugin &&
				wp_verify_nonce( $_POST['_wpnonce'], 'wporg-plugins-upload' )
			) || (
				// Existing submission
				! empty( $_POST['plugin_id'] ) &&
				'upload-additional' === $_POST['action'] &&
				wp_verify_nonce( $_POST['_wpnonce'], 'wporg-plugins-upload-' . $_POST['plugin_id'] )
			)
		) {
			$for_plugin    = absint( $_POST['plugin_id'] ?? 0 );
			$upload_result = $uploader->process_upload( $for_plugin );

			if ( is_wp_error( $upload_result ) ) {
				$type    = 'error';
				$message = $upload_result->get_error_message();
			} else {
				$type    = 'success';
				$message = $upload_result;
			}

			// Refresh the lists.
			list( $submitted_plugins, $submitted_counts ) = self::get_submitted_plugins();
			$can_submit_new_plugin                        = $submitted_counts->total < $maximum_plugins_in_queue;

			if ( ! empty( $message ) ) {
				echo "<div class='notice notice-{$type} notice-alt'><p>{$message}</p></div>\n";
			}
		}

		if ( ! is_wp_error( $upload_result ) || $submitted_counts->total /* has a plugin in the review queue */ ) :
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
								'You have %s plugin being actively reviewed and have been sent an email regarding issues. You must complete this review before you can submit another plugin. Please continue the review process by following the steps indicated in that email.',
								'You have %s plugins being actively reviewed and have been sent emails regarding issues. You must complete their reviews before you can submit another plugin. Please continue the review process by following the steps indicated in that email.',
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

					<p>
						<?php _e( 'Please review the Plugin Check results for your plugin, and fix any significant problems. This will help streamline the preview process and reduce delays by ensuring your plugin already meets the required standards when the plugin review team examines it.', 'wporg-plugins' ); ?>
					</p>

					<ul>
					<?php
					// List of all plugins in progress.
					foreach ( $submitted_plugins as $plugin ) {
						$can_change_slug   = ( 'new' === $plugin->post_status && ! $plugin->{'_wporg_plugin_original_slug'} );
						$can_upload_extras = in_array( $plugin->post_status, array( 'new', 'pending' ), true );

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
											<input class="wp-block-button__link" type="submit" value="<?php
												/* translators: Request slug-change button */
												esc_attr_e( 'Request', 'wporg-plugins' );
											?>" />
										</p>
									</form>
								</dialog>
							<?php
							endif; // $can_change_slug
							echo '</li>';

							add_filter( 'get_attached_media_args', $get_attached_media_args = function( $args ) {
								$args['orderby'] = 'post_date';
								$args['order']   = 'DESC';
								return $args;
							} );
							$attached_media = get_attached_media( 'application/zip', $plugin );
							remove_filter( 'get_attached_media_args', $get_attached_media_args );

							if ( $can_upload_extras ) {
								echo '<li>';
								echo '<a href="#" class="show-upload-additional hide-if-no-js">' . sprintf( __( 'Upload new version of %s for review.', 'wporg-plugins' ), esc_html( $plugin->post_title ) ) . '</a>';

								?>
								<form class="plugin-upload-form hidden" enctype="multipart/form-data" method="POST" action="">
									<?php wp_nonce_field( 'wporg-plugins-upload-' . $plugin->ID ); ?>
									<input type="hidden" name="action" value="upload-additional"/>
									<input type="hidden" name="plugin_id" value="<?php echo esc_attr( $plugin->ID ); ?>" />

									<label>
										<?php _e( 'Additional Information', 'wporg-plugins' ); ?><br>
										<textarea name="comment" rows="3" cols="80"></textarea>
									</label>
									<br>

									<label class="wp-block-button__link zip-file">
										<input type="file" class="plugin-file" name="zip_file" size="25" accept=".zip" required data-maxbytes="<?php echo esc_attr( wp_max_upload_size() ); ?>" />
										<span><?php _e( 'Select File', 'wporg-plugins' ); ?></span>
									</label>

									<input class="upload-button wp-block-button__link" type="submit" value="<?php esc_attr_e( 'Upload', 'wporg-plugins' ) ?>"/>
								</form>
								<?php
								echo '</li>';
							}

							echo '<li>';
							echo '<strong>' . __( 'Submitted files:', 'wporg-plugins' ) . '</strong><ol>';
							foreach ( $attached_media as $attachment_post_id => $upload ) {
								echo '<li><ul>';
								echo '<li><code>' . esc_html( $upload->submitted_name ) . '</code></li>';
								echo '<li>' . sprintf( __( 'Version: %s', 'wporg-plugins' ), '<code>' . esc_html( $upload->version ) . '</code>' ) . '</li>';
								echo '<li>' . sprintf( __( 'Upload Date: %s', 'wporg-plugins' ), date_i18n( get_option( 'date_format' ), strtotime( $upload->post_date ) ) ) . '</li>';
								if ( $upload->post_content ) {
									echo '<li>' . nl2br( wp_kses_post( $upload->post_content ) ) . '</li>';
								}
								if ( array_key_first( $attached_media) === $attachment_post_id ) {
									printf(
										'<li class="wp-block-button is-small"><a href="%s" class="%s" target="_blank">%s</a></li>',
										esc_url( Template::preview_link_zip( $plugin->post_name, $upload->ID, 'pcp' ) ),
										'wp-block-button__link',
										__( 'Check with Plugin Check', 'wporg-plugins' )
									);
								}
								echo '</ul></li>';
							}
							echo '</ol>';

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

		} else if ( $can_submit_new_plugin && ( ! $upload_result || is_wp_error( $upload_result ) ) ) :
			if ( $maximum_plugins_in_queue > 1 && $submitted_counts->total ) {
				printf(
					'<div class="notice notice-info notice-alt"><p>%s</p></div>',
					sprintf(
						/* translators: %s: Maximum number of plugins in the queue. */
						__( 'You can have up to %s plugins in the queue at a time. You may submit an additional plugin for review below.', 'wporg-plugins' ),
						'<strong>' . number_format_i18n( $maximum_plugins_in_queue ) . '</strong>'
					)
				);
			}

			?>
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

				<label class="wp-block-button__link zip-file">
					<input type="file" class="plugin-file" name="zip_file" size="25" accept=".zip" required data-maxbytes="<?php echo esc_attr( wp_max_upload_size() ); ?>" />
					<span><?php _e( 'Select File', 'wporg-plugins' ); ?></span>
				</label>

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
						<?php _e( 'Additional Information', 'wporg-plugins' ); ?><br>
						<textarea name="comment" rows="3" cols="80"><?php
							if ( ! empty( $_REQUEST['comment'] ) ) {
								echo esc_textarea( $_REQUEST['comment'] );
							}
						?></textarea>
					</label>
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
					<br>
					<label>
						<input type="checkbox" name="requirements[plugin-check]" required="required" />
						<?php
							printf(
								/* Translators: URL to plugin-check plugin */
								__( 'I confirm that the plugin has been tested with the <a href="%s">Plugin Check</a> plugin, and all indicated issues resolved (apart from what I believe to be false-positives).', 'wporg-plugins' ),
								home_url( '/plugins/plugin-check/' )
							);
						?>
					</label>
				</p>

				<input id="upload_button" class="wp-block-button__link" type="submit" value="<?php esc_attr_e( 'Upload', 'wporg-plugins' ); ?>"/>
			</form>
		<?php endif; // $can_submit_new_plugin

		return ob_get_clean();
	}

}
