<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;
use WordPressdotorg\Plugin_Directory\Template;
use function WordPressdotorg\MainTheme\_esc_html_e;
use function WordPressdotorg\Two_Factor\get_onboarding_account_url;

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
					$plugin->status = __( "Being Reviewed &#8212; We've got your email. This plugin is currently waiting on action from our plugins team.", 'wporg-plugins' );
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
		// In the rest-api, just return the shortcode tag, so it can be rendered properly in the app or other consumers.
		if ( wp_is_serving_rest_request() ) {
			return '[wporg-plugin-upload]';
		}

		if ( ! is_user_logged_in() ) {
			return '<div class="notice notice-error notice-alt"><p>' . sprintf(
				/* translators: Login URL */
				__( 'Before you can upload a new plugin, <a href="%s">please log in</a>.', 'wporg-plugins' ),
				esc_url( wp_login_url() )
			) . '</p></div>';
		}

		// Check 2FA before proceeding.
		$preconditions = Upload_Handler::accepting_uploads();
		if ( is_wp_error( $preconditions ) && '2fa_required' === $preconditions->get_error_code() ) {
			return '<div class="notice notice-error notice-alt"><p>' . sprintf(
				/* translators: Setup 2FA url */
				__( 'Before you can upload a new plugin, <a href="%s">please enable two-factor authentication</a>.', 'wporg-plugins' ),
				esc_url( function_exists( 'WordPressdotorg\Two_Factor\get_onboarding_account_url' ) ? get_onboarding_account_url() : 'https://profiles.wordpress.org/me/profile/security' )
			) . '</p></div>';
		}

		ob_start();

		include_once ABSPATH . 'wp-admin/includes/template.php';

		$uploader      = new Upload_Handler();
		$upload_result = false;

		list( $submitted_plugins, $submitted_counts ) = self::get_submitted_plugins();
		$can_submit_new_plugin                        = ! is_wp_error( $preconditions );

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

				if ( function_exists( 'bump_stats_extra' ) && 'production' === wp_get_environment_type() ) {
					bump_stats_extra( 'plugin-upload-source', 'webform' );
				}
			}

			// Refresh the lists.
			list( $submitted_plugins, $submitted_counts ) = self::get_submitted_plugins();
			$can_submit_new_plugin                        = ! is_wp_error( Upload_Handler::has_queue_capacity() );

			if ( ! empty( $message ) ) {
				echo "<div class='notice notice-{$type} notice-alt'><p>{$message}</p></div>\n";
			}
		}

		if ( ! is_wp_error( $upload_result ) || $submitted_counts->total /* has a plugin in the review queue */ ) :
			$plugins       = wp_count_posts( 'plugin', 'readable' );
			$oldest_plugin = get_posts( [ 'post_type' => 'plugin', 'post_status' => 'new', 'order' => 'ASC', 'orderby' => 'post_date_gmt', 'numberposts' => 1 ] );
			$queue_length_in_days = 0;
			$queue_oldest_new_plugin_date_with_offset = '';
			if ( ! empty( $oldest_plugin ) ) {
				$queue_length_in_days = floor( ( time() - strtotime( $oldest_plugin[0]->post_date_gmt ?? 'now' ) ) / DAY_IN_SECONDS );
				// It adds a 36 hours offset to avoid confusion related to timezones and for it to be at least the previous day of the latest submission.
				$queue_oldest_new_plugin_date_with_offset = date_i18n(
					get_option( 'date_format' ),
					strtotime( $oldest_plugin[0]->post_date_gmt ?? 'now' ) - ( 36 * HOUR_IN_SECONDS )
				);
			}
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

				if ( $plugins->new > 0 && ! empty( $queue_oldest_new_plugin_date_with_offset ) ) {
					echo '</p><p>';
					printf(
						/* translators: %s: Date of the oldest new plugin in the queue, with a 36 hours offset. */
						esc_html( __(
							'All plugins that have been submitted before %s have received an initial response by email to their submission.',
							'wporg-plugins'
						) ),
						'<strong>' . esc_html( $queue_oldest_new_plugin_date_with_offset ) . '</strong>'
					);

					// If the queue is currently beyond 10 days, display a warning to that effect.
					if ( $queue_length_in_days >= 10 ) {
						echo ' ' . __( 'The review queue is currently longer than normal, we apologize for the delays and ask for patience.', 'wporg-plugins' );
					}
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
						<?php _e( 'Please review the Plugin Check results for your plugin, and fix any significant problems. This will help streamline the preview process and reduce delays by ensuring your plugin already meets the required standards when the Plugins Team examines it.', 'wporg-plugins' ); ?>
					</p>

					<ul>
					<?php
					// List of all plugins in progress.
					foreach ( $submitted_plugins as $plugin ) {
						$can_change_slug   = ( 'new' === $plugin->post_status && ! $plugin->{'_wporg_plugin_original_slug'} );
						$can_upload_extras = in_array( $plugin->post_status, array( 'new', 'pending' ), true );

						echo '<li class="plugin-submission-item">';
							echo '<div class="plugin-submission-name">' . esc_html( $plugin->post_title ) . '</div>';
							printf(
								'<div class="plugin-submission-submited-date">%s</div>',
								sprintf(
									__( 'Submitted on: %s', 'wporg-plugins' ),
									esc_html( wp_date( get_option( 'date_format' ), strtotime( $plugin->post_date_gmt ) ) )
								)
							);
							printf(
								'<div class="plugin-submission-status">%s</div>',
								sprintf(
									__( 'Review status: %s', 'wporg-plugins' ),
									$plugin->status
								)
							);
							if (
								'pending' === $plugin->post_status &&
								! empty( $plugin->review_email ) &&
								! empty( $plugin->review_email->subject ) &&
								! empty( $plugin->review_email->created )
							) {
								printf(
									'<div class="plugin-submission-email">✉️✔️ %s</div>',
									sprintf(
										__( 'Our team emailed you on <strong>%s</strong> regarding your submission. The subject line is: "<strong>%s</strong>".', 'wporg-plugins' ),
										esc_html( wp_date( get_option( 'date_format' ), strtotime( $plugin->review_email->created ) ) ),
										esc_html( $plugin->review_email->subject )
									)
								);
								echo '<div class="plugin-submission-email-clarification">';
								esc_html_e( 'Please follow the instructions in that email to continue the review process. If you don’t see it in your inbox, check your spam or junk folder. You may also want to add plugins@wordpress.org to your email whitelist to ensure you receive future updates.', 'wporg-plugins' );
								echo '</div>';
							}
							if ( 'new' === $plugin->post_status ) {
								printf(
									'<div class="plugin-submission-email">✉️⏳ %s</div>',
									sprintf(
										__( 'Please be patient and wait for the review email. It will be sent to your email address, <strong>%s</strong>, with the subject line: "<strong>%s</strong>".', 'wporg-plugins' ),
										esc_html( get_userdata( $plugin->post_author )->user_email ),
										'[WordPress Plugin Directory] Review in Progress: ' . $plugin->post_title
									)
								);
								echo '<div class="plugin-submission-email-clarification">';
								esc_html_e( 'Be sure to check your spam folder, and consider adding plugins@wordpress.org to your email whitelist to ensure the message does not get missed.', 'wporg-plugins' );
								echo '</div>';
							}
							echo '<div class="plugin-submission-assigned-slug">';
							printf(
								__( 'Current assigned slug: %s', 'wporg-plugins' ),
								'<code>' . esc_html( $plugin->post_name ) . '</code>'
							);
							?>
							<?php if ( $can_change_slug ) : ?>
								<a href="#" class="hide-if-no-js" onclick="event.preventDefault(); this.nextElementSibling.showModal()"><?php _e( 'change', 'wporg-plugins' ); ?></a>
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
							echo '</div>';

							add_filter( 'get_attached_media_args', $get_attached_media_args = function( $args ) {
								$args['orderby'] = 'post_date';
								$args['order']   = 'DESC';
								return $args;
							} );
							$attached_media = get_attached_media( 'application/zip', $plugin );
							remove_filter( 'get_attached_media_args', $get_attached_media_args );

							if ( $can_upload_extras ) {
								echo '<div class="plugin-submission-update-code wp-block-button is-small">';
								echo '<a href="#" class="show-upload-additional hide-if-no-js wp-block-button__link">' . sprintf( __( 'Upload updated "%s" plugin for review.', 'wporg-plugins' ), esc_html( $plugin->post_title ) ) . '</a>';

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
								echo '</div>';
							}

							echo '<div class="plugin-submission-submitted-files">';
							echo '<strong>' . __( 'Submitted files:', 'wporg-plugins' ) . '</strong>';
							foreach ( $attached_media as $attachment_post_id => $upload ) {
								echo '<div class="plugin-submission-file">';
								echo '<table class="plugin-submission-file__meta">';
								echo '<tbody>';

								echo '<tr>';
								echo '<th scope="row">' . esc_html__( 'Upload Date:', 'wporg-plugins' ) . '</th>';
								echo '<td>' . esc_html( wp_date( get_option( 'date_format' ), strtotime( $upload->post_date ) ) ) . '</td>';
								echo '</tr>';

								echo '<tr>';
								echo '<th scope="row">' . esc_html__( 'File:', 'wporg-plugins' ) . '</th>';
								echo '<td><code>' . esc_html( $upload->submitted_name ) . '</code></td>';
								echo '</tr>';

								echo '<tr>';
								echo '<th scope="row">' . esc_html__( 'Version:', 'wporg-plugins' ) . '</th>';
								echo '<td><code>' . esc_html( $upload->version ) . '</code></td>';
								echo '</tr>';

								echo '</tbody>';
								echo '</table>';

								if ( $upload->post_content ) {
									echo '<div class="plugin-submission-file__notes">' . nl2br( wp_kses_post( $upload->post_content ) ) . '</div>';
								}

								if ( array_key_first( $attached_media ) === $attachment_post_id ) {
									printf(
										'<div class="plugin-submission-file__pcp wp-block-button is-small"><a href="%s" class="%s" target="_blank">%s</a></div>',
										esc_url( Template::preview_link_zip( $plugin->post_name, $upload->ID, 'pcp' ) ),
										'wp-block-button__link',
										__( 'Check with Plugin Check', 'wporg-plugins' )
									);
								}
								echo '</div>';
							}

							echo '</div>';
						echo "</li>\n";
					}
					?>
					</ul>
				</div>

			<?php endif; // $submitted_counts->total ?>

		<?php endif; // ! is_wp_error( $upload_result )

		if ( is_wp_error( $preconditions ) && 'submissions_paused' === $preconditions->get_error_code() ) {
			printf(
				'<div class="notice notice-error notice-alt"><p>%s</p></div>',
				sprintf(
					__( 'New plugin submissions are currently disabled. Please check back after the <a href="%s">holiday break.</a>', 'wporg-plugins' ),
					'https://wordpress.org/news/2024/12/holiday-break/'
				)
			);
		} else if ( is_wp_error( $preconditions ) && 'unsafe_email' === $preconditions->get_error_code() ) {
			echo '<div class="notice notice-error notice-alt"><p>' .
				sprintf(
					/* translators: %s: Profile edit url. */
					__( 'Your email host has email deliverability problems. Please <a href="%s">Update your email address</a> first.', 'wporg-plugins'),
					esc_url( 'https://wordpress.org/support/users/' . wp_get_current_user()->user_nicename . '/edit' )
					) .
					"</p></div>\n";

		} else if ( $can_submit_new_plugin && ( ! $upload_result || is_wp_error( $upload_result ) ) ) :

			?>
			<form id="upload_form" class="plugin-upload-form" enctype="multipart/form-data" method="POST" action="">
				<?php wp_nonce_field( 'wporg-plugins-upload' ); ?>
				<input type="hidden" name="action" value="upload"/>

				<h2><?php esc_html_e( 'Step 1: Before you submit', 'wporg-plugins' ); ?></h2>
				<p><?php esc_html_e( 'Please, read and confirm the following.', 'wporg-plugins' ); ?></p>
				<label>
					<input type="checkbox" name="requirements[faq]" required="required">
					<?php
					printf( wp_kses_post( __( 'I have read the <a href="%s">Frequently Asked Questions</a>.', 'wporg-plugins' ) ),'https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/' );
					?>
				</label>
				<br>
				<label>
					<input type="checkbox" name="requirements[guidelines]" required="required">
					<?php
					printf( wp_kses_post( __( 'I have read and make sure that this plugin complies with <strong>all</strong> of the <a href="%s">Plugins Directory Guidelines</a>.', 'wporg-plugins' ) ),	'https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/'	);
					?>
				</label>
				<br>
				<label>
					<input type="checkbox" name="requirements[plugin-check]" required="required" />
					<?php
					printf(
						/* Translators: URL to plugin-check plugin */
						wp_kses_post( __( 'I confirm that the plugin has been tested with the <a href="%s">Plugin Check</a> plugin, and all indicated issues resolved (apart from what I believe to be false-positives).', 'wporg-plugins' )),
						esc_url( home_url( '/plugin-check/' ) )
					);
					?>
				</label>

				<h2><?php esc_html_e( 'Step 2: Common reasons plugins are rejected', 'wporg-plugins' ); ?></h2>
				<h3>🏷️
					<?php
					esc_html_e( 'Naming and ownership', 'wporg-plugins' );
					echo " - <i>".esc_html( 'Guideline 17', 'wporg-plugins' )."</i>";
					?>
				</h3>
				<p><?php echo wp_kses_post( __( 'Plugin names must be <strong>distinctive</strong> and should not be <strong>confusingly similar</strong> to existing plugins, projects, products, organizations, or trademarks that are not owned by you.', 'wporg-plugins' ) ); ?></p>
				<p><?php echo wp_kses_post( __( '<strong>Choosing a distinctive name</strong>: Generic names such as "AI Writer" or "Image Optimization" and/or names similar to existing plugins are unlikely to be accepted, even if the plugin slug is available. Make your plugin stand out by using a unique identifier such as your name, brand, organization, or project name (e.g., “<u>Acme</u> AI Writer”, “<u>WriteralAI</u> – AI Writter”)', 'wporg-plugins' ) ); ?></p>
				<p><?php printf(
					/* translators: 1: User profile URL. */
							wp_kses_post(
								__( '<strong>Demonstrating ownership</strong>: Names that begin with a company, project, organization, or trademark name may only be submitted by the verified owner. Ownership is typically verified using the <a href="%1$s" target="_blank">email address associated with your WordPress.org account</a>. If necessary, update your profile before submitting.', 'wporg-plugins' )
							),
							esc_url( 'https://profiles.wordpress.org/profile/edit' )
					); ?>
				</p>
				<p><?php echo wp_kses_post( __( '<strong>If you are not the owner</strong>: Simply do not imply affiliation with a company, project, or trademark that you do not own. You can do so placing their name at the end and making the distinction clear (e.g., “WriteralAI – AI Writer <u>for Acme</u>”)', 'wporg-plugins' ) ); ?></p>
				<label>
					<input type="checkbox" name="requirements[naming]" required="required">
					<?php _e( 'I have chosen a plugin name that is not confusingly similar to existing plugins, projects, organizations, or trademarks. I searched on internet for similar names and found nothing similar.', 'wporg-plugins' ); ?>
				</label>
				<br>
				<label>
					<input type="checkbox" name="requirements[author]" required="required">
					<?php
					printf(
					/* translators: %s: Current user's email address. */
							wp_kses_post( __( 'I have permission to upload this plugin to WordPress.org for others to use and share and I am using a WordPress.org account that accurately represents the plugin owner: %s.', 'wporg-plugins' )),
							'<strong>' . esc_html( wp_get_current_user()->user_email ) . '</strong>'
					);
					?>
				</label>

				<h3>🔓
					<?php
					esc_html_e( 'Trialware', 'wporg-plugins' );
					echo " - <i>".esc_html( 'Guidelines 5 and 6', 'wporg-plugins' )."</i>";
					?>
				</h3>
				<p><?php echo wp_kses_post( __( 'Plugins hosted in the WordPress.org Plugin Directory <strong>may not artificially restrict functionality that is included in the plugin itself</strong>.', 'wporg-plugins' ) ); ?></p>
				<p><?php echo wp_kses_post( __( 'This includes but is not limited to: paywalls, license/feature gating, time-limited trials, usage cutoffs, or any other mechanism that restricts, disables, or conditions access to <u>features implemented in the plugin code and/or that the plugin itself can do</u>.', 'wporg-plugins' ) ); ?></p>
				<p><?php echo wp_kses_post( __( 'Please bear in mind that this is an <strong>open source directory for free to use plugins</strong>. Building a business around your plugin is fine, and there are compliant ways of doing so; artificial limitations in built-in code are not one of them.', 'wporg-plugins' ) ); ?></p>
				<label>
					<input type="checkbox" name="requirements[trialware]" required="required">
					<?php esc_html_e( 'I confirm that my plugin code does not include artificial limitations to the included functionality. I acknowledge that I must comply with this in future, and that my plugin and account could be suspended indefinitely if I fail to do so.', 'wporg-plugins' ); ?>
				</label>

				<h3>🚫 <?php esc_html_e( 'Not accepted plugins', 'wporg-plugins' ); ?></h3>
				<p><?php esc_html_e( 'There are some kind of plugins that aren\'t accepted in the directory, please do not submit them.', 'wporg-plugins' ); ?></p>
				<ul>
					<li><?php echo wp_kses_post( __( 'Plugins that allow arbitrary code insertion/execution are no longer accepted. This includes but is not limited to: PHP, JavaScript editors, File Managers, and AI tools creating code that is executed on the site.  HTML is fine as long as it is escaped correctly. <i>Reason: Security</i>.', 'wporg-plugins' ) ); ?></li>
					<li><?php echo wp_kses_post( __( 'Plugins downloading executable code from external sources. <i>Reasons: Security and Guideline 8</i>.', 'wporg-plugins' ) ); ?></li>
				</ul>

				<h2><?php esc_html_e( 'Step 3: Submission acknowledgement', 'wporg-plugins' ); ?></h2>
				<p><?php esc_html_e( 'Please confirm that you understand the following:', 'wporg-plugins' ); ?></p>
				<label>
					<input type="checkbox" name="requirements[confirmation1]" required="required">
					<?php esc_html_e( 'I understand that submissions that do not follow the Plugin Directory Guidelines may be rejected. Repeated or serious violations may result in further restrictions', 'wporg-plugins' ); ?>
				</label>
				<br>
				<label>
					<input type="checkbox" name="requirements[confirmation3]" required="required">
					<?php esc_html_e( 'I understand that hosting in the WordPress.org Plugin Directory is provided subject to continued compliance with the Plugin Directory Guidelines.', 'wporg-plugins' ); ?>
				</label>
				<br>

				<h3><?php esc_html_e( 'Are you ready? Upload the .zip file for your plugin', 'wporg-plugins' ); ?></h3>

				<?php
				if ( ! empty( $_REQUEST['upload_token'] ) ) {
					printf(
						'<input type="hidden" name="upload_token" value="%s"/>',
						esc_attr( $_REQUEST['upload_token'] )
					);

					if ( ! $uploader->has_valid_upload_token() ) {
						printf(
							'<div class="notice notice-error notice-alt"><p>%s</p></div>',
							esc_html__( 'The token provided is invalid for this user.', 'wporg-plugins' )
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

				<div>
					<small>
						<?php
						printf(
							/* translators: Maximum allowed file size. */
							esc_html__( 'Maximum allowed file size: %s', 'wporg-plugins' ),
							esc_html( size_format( wp_max_upload_size() ) )
						);
						?>
					</small>
				</div>

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

				<input id="upload_button" class="wp-block-button__link" type="submit" value="<?php esc_attr_e( 'Upload', 'wporg-plugins' ); ?>"/>
			</form>
		<?php endif; // $can_submit_new_plugin

		return ob_get_clean();
	}

}
